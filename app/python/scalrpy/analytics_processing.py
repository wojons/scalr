# vim: tabstop=4 shiftwidth=4 softtabstop=4
#
# Copyright 2013, 2014, 2015 Scalr Inc.
#
# Licensed under the Apache License, Version 2.0 (the "License"); you may
# not use this file except in compliance with the License. You may obtain
# a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.

from gevent import monkey
monkey.patch_all()

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.join(cwd, '..')
sys.path.insert(0, scalrpy_dir)

import re
import shutil
import gevent
import gevent.lock
import csv
import zipfile
import tempfile
import socket
import boto
import boto.s3.key
import datetime

from scalrpy.util import helper
from scalrpy.util import analytics
from scalrpy.util import dbmanager
from scalrpy.util import exceptions
from scalrpy.util import application
from scalrpy.util import cryptotool
from scalrpy.util.analytics import platforms

from scalrpy import LOG


helper.patch_gevent()


app = None
days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]


def get_s3_conn(cred):
    access_key = cryptotool.decrypt_scalr(app.crypto_key, cred['access_key'])
    secret_key = cryptotool.decrypt_scalr(app.crypto_key, cred['secret_key'])
    kwds = {
        'aws_access_key_id': access_key,
        'aws_secret_access_key': secret_key
    }
    use_proxy = app.scalr_config.get('aws', {}).get('use_proxy', False)
    use_on = app.scalr_config['connections'].get('proxy', {}).get('use_on', 'both')
    if use_proxy in [True, 'yes'] and use_on in ['both', 'scalr']:
        kwds['proxy'] = app.scalr_config['connections']['proxy']['host']
        kwds['proxy_port'] = app.scalr_config['connections']['proxy']['port']
        kwds['proxy_user'] = app.scalr_config['connections']['proxy']['user']
        kwds['proxy_pass'] = app.scalr_config['connections']['proxy']['pass']
    conn = boto.connect_s3(**kwds)
    return conn


def get_aws_csv_file_name(aws_account_id, date=None):
    date = date or datetime.datetime.utcnow().date()
    template = '{}-aws-billing-detailed-line-items-with-resources-and-tags-{:4d}-{:02d}.csv.zip'
    return template.format(aws_account_id, date.year, date.month)


class AnalyticsProcessing(application.ScalrIterationApplication):

    usage_start_date_format = "%Y-%m-%d %H:%M:%S"
    last_modified_format = "%a, %d %b %Y %H:%M:%S %Z"

    def __init__(self, argv=None):
        self.description = "Scalr Cost Analytics application"

        options = ("  --recalculate                     recalculate data ")
        self.add_options(options)

        options = (
            """  --platform <platform>             platform to recalculate\n"""
            """\t\t\t\t\t[cloudstack, ec2, ecs, gce, idcf, openstack,\n"""
            """\t\t\t\t\trackspacenguk, rackspacengus, ocs, nebula,\n"""
            """\t\t\t\t\tmirantis, vio, verizon, cisco]""")
        self.add_options(options)

        options = "  --date-from <date>                start date to recalculate, 'YYYY-MM-DD' UTC"
        self.add_options(options)

        options = "  --date-to <date>                  end date to recalculate, 'YYYY-MM-DD' UTC"
        self.add_options(options)

        super(AnalyticsProcessing, self).__init__(argv=argv)

        self.config['connections'].update({
            'analytics': {
                'user': None,
                'pass': None,
                'host': None,
                'port': 3306,
                'name': None,
                'pool_size': 25,
                'timeout': 30,
            },
        })
        self.config.update({
            'interval': 1800,
            'dtime_from': False,
            'dtime_to': False,
            'platform': False,
            'pool_size': 25,
        })
        self.pool = None
        self._lock = gevent.lock.RLock()

        self.aws_billing_dtime_from = None

    def validate_args(self):
        application.ScalrIterationApplication.validate_args(self)
        if self.args['--recalculate']:
            assert_msg = "To recalculate data you should specify platform argument"
            assert self.args['--platform'], assert_msg

            assert_msg = "Platform %s is not supported" % self.args['--platform']
            assert self.args['--platform'] in platforms, assert_msg

            assert_msg = "To recalculate data you should specify date-from argument"
            assert self.args['--date-from'], assert_msg

        if self.args['--date-from']:
            assert_msg = "Wrong date format for --date-from option, use Y-m-d"
            try:
                datetime.datetime.strptime(self.args['--date-from'], '%Y-%m-%d')
            except:
                raise AssertionError(assert_msg)

        if self.args['--date-to']:
            assert_msg = "Wrong date format for --date-to option, use Y-m-d"
            try:
                datetime.datetime.strptime(self.args['--date-to'], '%Y-%m-%d')
            except:
                raise AssertionError(assert_msg)

    def configure(self):
        enabled = self.scalr_config.get('analytics', {}).get('enabled', False)
        if not enabled:
            sys.stdout.write('Analytics is disabled. Exit\n')
            sys.exit(0)

        # set date_from
        if self.args['--date-from']:
            dtime_from = datetime.datetime.strptime(self.args['--date-from'], '%Y-%m-%d')
            two_weeks_ago = datetime.datetime.utcnow() + datetime.timedelta(days=-14)
            assert_msg = 'Processing is not supported for dtime-from more than two weeks ago'
            assert dtime_from > two_weeks_ago, assert_msg
            self.config['dtime_from'] = dtime_from

        # set date_to
        if self.args['--date-to']:
            dtime_to = datetime.datetime.strptime(self.args['--date-to'], '%Y-%m-%d')
            dtime_to = dtime_to.replace(minute=59, second=59)
            self.config['dtime_to'] = dtime_to

        # update config
        helper.update_config(
            self.scalr_config.get('analytics', {}).get('connections', {}).get('scalr', {}),
            self.config['connections']['mysql'])

        helper.update_config(
            self.scalr_config.get('analytics', {}).get('connections', {}).get('analytics', {}),
            self.config['connections']['analytics'])

        helper.update_config(
            self.scalr_config.get('analytics', {}).get('processing', {}),
            self.config)

        self.config['platform'] = self.args['--platform'] or False

        helper.validate_config(self.config)

        self.iteration_timeout = self.config['interval'] - self.error_sleep

        crypto_key_path = os.path.join(os.path.dirname(self.args['--config']), '.cryptokey')
        self.crypto_key = cryptotool.read_key(crypto_key_path)

        self.scalr_db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self.analytics_db = dbmanager.ScalrDB(self.config['connections']['analytics'])
        self.analytics = analytics.Analytics(self.scalr_db, self.analytics_db)

        self.pool = helper.GPool(pool_size=self.config['pool_size'])

        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

    def download_aws_billing_file(self, cred, bucket_name, date=None):
        if date is None:
            date = datetime.datetime.utcnow().date()

        conn = get_s3_conn(cred)
        bucket = conn.get_bucket(bucket_name)
        account_id = cryptotool.decrypt_scalr(app.crypto_key, cred['account_id'])
        file_name = get_aws_csv_file_name(account_id, date)
        key = bucket.get_key(file_name)

        if not key:
            msg = "AWS detailed billing CSV file {0} wasn't found in bucket {1}"
            msg = msg.format(file_name, bucket_name)
            if datetime.datetime.utcnow().day == 1:
                LOG.warning(msg)
                return None
            else:
                raise Exception(msg)

        last_modified_dt = datetime.datetime.strptime(key.last_modified, self.last_modified_format)
        update_interval = self.config['interval']
        utcnow = datetime.datetime.utcnow()
        delta = datetime.timedelta(seconds=update_interval)
        condition1 = utcnow > last_modified_dt and utcnow < last_modified_dt + delta
        condition2 = ((utcnow - last_modified_dt).seconds / 3600) % 8 == 0
        if condition1 or condition2:
            local_file_path = os.path.join(self.tmp_dir, file_name)
            LOG.debug('Downloading {0}'.format(file_name))
            key.get_contents_to_filename(local_file_path)
            return local_file_path
        else:
            return None

    def csv_reader(self, csv_file, dtime_from=None):
        dtime_to = self.config['dtime_to'] or datetime.datetime.utcnow()

        chunk_size = 250
        with open(csv_file, 'r') as f:
            i = 0
            rows = []
            reader = csv.DictReader(f)
            for row in reader:
                if not row['user:scalr-meta']:
                    continue
                start_date = datetime.datetime.strptime(row['UsageStartDate'],
                                                        self.usage_start_date_format)
                if dtime_from and start_date < dtime_from:
                    continue
                if start_date > dtime_to:
                    break
                i += 1
                rows.append(row)
                if i >= chunk_size:
                    yield rows
                    i = 0
                    rows = []
            if rows:
                yield rows

    def get_scalr_meta(self, row):
        keys_map = {
            'v1': ['env_id', 'farm_id', 'farm_role_id', 'server_id'],
        }
        data = row['user:scalr-meta'].split(':')
        version = data[0]
        assert version in keys_map
        meta = dict(zip(keys_map[version], data[1:]))
        return meta

    pattern_1 = re.compile(r"^.*BoxUsage:(?P<item_name>.*)$")
    pattern_2 = re.compile(r"^.*VolumeUsage.(?P<item_name>.*)$")
    pattern_3_in = re.compile(r"^(?P<item_name>.*)-In-Bytes$")
    pattern_3_out = re.compile(r"^(?P<item_name>.*)-Out-Bytes$")
    pattern_3_reg = re.compile(r"^(?P<item_name>.*)-Regional-Bytes$")

    def get_aws_record(self, row):
        record = {}
        record.update(self.get_scalr_meta(row))
        record['dtime'] = datetime.datetime.strptime(row['UsageStartDate'],
                                                     self.usage_start_date_format)
        record['instance_id'] = row['ResourceId']
        record['platform'] = 'ec2'
        record['url'] = ''
        record['cost'] = float(row['Cost'])
        record['record_id'] = row['RecordId']

        cost_distr_type = 4
        usage_type_name = 'Other'
        usage_item_name = ''

        # Compute
        if 'BoxUsage' in row['UsageType'] and 'RunInstances' in row['Operation']:
            if self.analytics.record_exists(record):
                return
            cost_distr_type = 1
            usage_type_name = 'BoxUsage'
            match = self.pattern_1.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')

        # Storage
        elif 'EBS:VolumeUsage' in row['UsageType'] and 'CreateVolume' in row['Operation']:
            cost_distr_type = 2
            usage_type_name = 'EBS'
            match = self.pattern_2.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')
            else:
                usage_item_name = 'standard'
        elif 'EBS:VolumeIOUsage' in row['UsageType']:
            cost_distr_type = 2
            usage_type_name = 'EBS IO'
            usage_item_name = 'io'
        elif 'EBS:VolumeP-IOPS' in row['UsageType']:
            cost_distr_type = 2
            usage_type_name = 'EBS IOPS'
            usage_item_name = 'iops'

        # Bandwidth
        elif '-In-Bytes' in row['UsageType']:
            cost_distr_type = 3
            usage_type_name = 'In'
            match = self.pattern_3_in.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')
        elif '-Out-Bytes' in row['UsageType']:
            cost_distr_type = 3
            usage_type_name = 'Out'
            match = self.pattern_3_out.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')
        elif 'DataTransfer-Regional-Bytes' in row['UsageType']:
            cost_distr_type = 3
            usage_type_name = 'Regional'
            match = self.pattern_3_reg.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')
        # Unsupported type
        else:
            return

        if row['AvailabilityZone']:
            record['cloud_location'] = row['AvailabilityZone'][:-1]

        record['cost_distr_type'] = cost_distr_type
        record['usage_type_name'] = usage_type_name
        assert usage_item_name or (cost_distr_type in (1, 4))
        record['usage_item_name'] = usage_item_name

        if usage_type_name in ('EBS', 'EBS IOPS'):
            days = days_in_month[record['dtime'].month]
            record['num'] = float(row['UsageQuantity']) * days * 24
        else:
            record['num'] = float(row['UsageQuantity'])

        # GB -> MB
        if cost_distr_type == 3:
            record['num'] = record['num'] * 1000.0

        return record

    def get_aws_records(self, rows):
        records = []
        records_ids = list(set([row['RecordId'] for row in rows]))
        query = (
            """SELECT record_id """
            """FROM aws_billing_records """
            """WHERE record_id IN ({record_id})"""
        ).format(record_id=str(records_ids)[1:-1])
        results = self.analytics_db.execute(query, retries=1)
        existing_records = [result['record_id'] for result in results]

        for row in rows:
            if row['RecordId'] in existing_records:
                continue
            record = self.get_aws_record(row)
            if record:
                records.append(record)

        incomplete_records = [record for record in records if not record['server_id']]
        if incomplete_records:
            tmp = {}
            for record in incomplete_records:
                if 'cloud_location' not in record:
                    continue
                tmp.setdefault(record['cloud_location'], []).append(record)
            for cloud_location, records_group in tmp.iteritems():
                envs_ids = list(set([record['env_id'] for record in records_group]))
                self.analytics.get_server_id_by_instance_id(records_group, envs_ids, 'ec2',
                                                            cloud_location)
            for record in incomplete_records:
                if record['cost_distr_type'] == 1 and self.analytics.record_exists(record):
                    records.remove(record)

        self.analytics.load_servers_data(records)

        names = [
            'ec2.instance_type',
            'info.instance_type_name',
            'os_type',
            'farm.created_by_id',
            'farm.project_id',
            'env.cc_id',
            'role.id',
        ]
        self.analytics.load_server_properties(records, names)

        for record in records:
            if not record.get('cc_id', None):
                try:
                    self.analytics.load_cc_id(record)
                    assert record['cc_id']
                except:
                    msg = 'Unable to load cc_id for record: {0}, reason: {1}'
                    msg = msg.format(record, helper.exc_info(where=False))
                    LOG.warning(msg)
            if not record.get('role_id', None):
                try:
                    self.analytics.load_role_id(record)
                    assert record['role_id']
                except:
                    msg = 'Unable to load role_id for record: {0}, reason: {1}'
                    msg = msg.format(record, helper.exc_info(where=False))
                    LOG.warning(msg)

        return records

    def get_aws_billing_interval(self):
        utcnow = datetime.datetime.utcnow()
        delta = datetime.timedelta(seconds=self.config['interval'])
        if self.start_dtime + delta > utcnow and self.config['dtime_from']:
            # use config['dtime_from'] only for first iteration
            dtime_from = self.config['dtime_from']
        else:
            dtime_from = datetime.datetime.utcnow().replace(day=1,
                                                            hour=0,
                                                            minute=0,
                                                            second=0,
                                                            microsecond=0)
            if utcnow.day == 1:
                dtime_from -= datetime.timedelta(days=1)
        dtime_to = self.config['dtime_to'] or datetime.datetime.utcnow().replace(microsecond=0)

        return dtime_from, dtime_to

    def process_aws_account(self, data, dtime_from, dtime_to):
        try:
            # Iterate over months from dtime_from to dtime_to
            dtime = dtime_from
            while dtime.month <= dtime_to.month:
                try:
                    csv_zip_file = self.download_aws_billing_file(data['cred'],
                                                                  data['bucket_name'],
                                                                  date=dtime.date())
                    if csv_zip_file is None:
                        continue

                    with zipfile.ZipFile(csv_zip_file, 'r') as f:
                        f.extract(f.infolist()[0], self.tmp_dir)
                    csv_file = os.path.join(self.tmp_dir,
                                            os.path.basename(csv_zip_file.strip('.zip')))

                    for rows in self.csv_reader(csv_file, dtime_from=dtime):
                        records = self.get_aws_records(rows)
                        records = [rec for rec in records if int(rec['env_id']) in data['envs_ids']]
                        for record in records:
                            self.pool.wait()
                            if self.args['--recalculate']:
                                self.pool.apply_async(self.analytics.update_record, (record,))
                            else:
                                self.pool.apply_async(self.analytics.insert_record, (record,))
                            gevent.sleep(0)  # force switch
                except:
                    msg = 'AWS billing for environments {0}, month {1} failed'
                    msg = msg.format(data['envs_ids'], dtime.month)
                    LOG.exception(msg)
                finally:
                    dtime = helper.new_month(dtime)
        except:
            msg = 'AWS billing for environments {0} failed'
            msg = msg.format(data['envs_ids'])
            LOG.exception(msg)

    def process_aws_billing(self):
        if self.args['--recalculate']:
            return

        dtime_from, dtime_to = self.get_aws_billing_interval()
        msg = 'AWS billing interval: {0} - {1}'
        msg = msg.format(dtime_from, dtime_to)
        LOG.debug(msg)

        with self._lock:
            if not self.aws_billing_dtime_from:
                self.aws_billing_dtime_from = dtime_from
            else:
                self.aws_billing_dtime_from = min(self.aws_billing_dtime_from, dtime_from)

        for envs in self.analytics.load_envs():
            unique = {}
            for env in envs:
                if env.get('ec2.detailed_billing.enabled', '0') != '1':
                    continue
                bucket_name = env['ec2.detailed_billing.bucket']
                creds = self.analytics.get_creds([env])
                cred = next(cred for cred in creds if cred.platform == 'ec2')
                unique.setdefault(cred.unique,
                                  {'envs_ids': [], 'cred': cred, 'bucket_name': bucket_name})
                unique[cred.unique]['envs_ids'].append(env['id'])

            for data in unique.values():
                while len(self.pool) > self.config['pool_size'] * 5 / 10:
                    gevent.sleep(0.1)
                self.pool.apply_async(self.process_aws_account, args=(data, dtime_from, dtime_to))

        self.pool.join()

        if not self.aws_billing_dtime_from:
            return

        dtime_from = self.aws_billing_dtime_from

        if self.config['dtime_to']:
            dtime_to = self.config['dtime_to']
        else:
            dtime_hour_ago = datetime.datetime.utcnow() - datetime.timedelta(hours=1)
            dtime_to = dtime_hour_ago.replace(minute=59, second=59, microsecond=999999)

        # fill farm_usage_d
        dtime_cur = dtime_from
        while dtime_cur <= dtime_to:
            date, hour = dtime_cur.date(), dtime_cur.hour
            try:
                self.analytics.fill_farm_usage_d(date, hour)
            except:
                msg = 'Unable to fill farm_usage_d table for date {0}, hour {1}'.format(date, hour)
                LOG.exception(msg)
            dtime_cur += datetime.timedelta(hours=1)

    def get_poller_billing_interval(self):
        dtime_hour_ago = datetime.datetime.utcnow() - datetime.timedelta(hours=1)

        if self.config['dtime_from']:
            dtime_from = self.config['dtime_from']
        else:
            dtime_from = dtime_hour_ago.replace(minute=0, second=0, microsecond=0)

        if self.config['dtime_to']:
            dtime_to = self.config['dtime_to']
        else:
            dtime_to = dtime_hour_ago.replace(minute=59, second=59, microsecond=999999)

        return dtime_from, dtime_to

    def process_poller_billing(self):
        dtime_from, dtime_to = self.get_poller_billing_interval()
        LOG.debug('Poller billing interval: {0} - {1}'.format(dtime_from, dtime_to))

        # process poller_session table
        dtime_cur = dtime_from
        while dtime_cur <= dtime_to:
            date, hour = dtime_cur.date(), dtime_cur.hour
            try:
                msg = "Process poller data, date {0}, hour {1}".format(date, hour)
                LOG.info(msg)

                if self.args['--recalculate']:
                    platform = self.config['platform']
                    generator = self.analytics.get_records(date, hour, platform)
                else:
                    generator = self.analytics.get_servers(date, hour)

                for records in generator:

                    LOG.debug('Records for processing: %s' % len(records))

                    prices = self.analytics.get_prices(records)
                    for record in records:
                        cost = self.analytics.get_cost_from_prices(record, prices) or 0

                        self.pool.wait()
                        if self.args['--recalculate']:
                            record['cost'] = float(cost) * int(record['num'])
                            self.pool.apply_async(self.analytics.update_record, (record,))
                        else:
                            record['cost'] = cost
                            record['num'] = 1.0
                            record['cost_distr_type'] = 1
                            self.pool.apply_async(self.analytics.insert_record, (record,))

                        gevent.sleep(0)  # force switch

                self.pool.join()
            except:
                msg = "Unable to process date {0}, hour {1}".format(date, hour)
                LOG.exception(msg)

            dtime_cur += datetime.timedelta(hours=1)

        # fill farm_usage_d
        dtime_cur = dtime_from
        while dtime_cur <= dtime_to:
            date, hour = dtime_cur.date(), dtime_cur.hour
            try:
                self.analytics.fill_farm_usage_d(date, hour)
            except:
                msg = 'Unable to fill farm_usage_d table for date {0}, hour {1}'.format(date, hour)
                LOG.exception(msg)
            dtime_cur += datetime.timedelta(hours=1)

        if not self.args['--recalculate']:
            return

        # recalculate daily tables
        dtime_cur = dtime_from
        while dtime_cur <= dtime_to:
            date = dtime_cur.date()
            msg = "Recalculate daily tables for date {0}".format(date)
            LOG.debug(msg)
            try:
                self.analytics.recalculate_usage_d(date, self.config['platform'])
            except:
                msg = "Recalculate usage_d table for date {0} failed, error: {1}".format(
                        date, helper.exc_info())
                LOG.warning(msg)
            dtime_cur += datetime.timedelta(days=1)

        # recalculate quarters tables
        quarters_calendar = self.analytics.get_quarters_calendar()
        start_year = quarters_calendar.year_for_date(dtime_from.date())
        start_quarter = quarters_calendar.quarter_for_date(dtime_from.date())
        end_year = quarters_calendar.year_for_date(dtime_to.date())
        end_quarter = quarters_calendar.quarter_for_date(dtime_to.date())

        tmp = []
        cur_year = start_year
        while cur_year < end_year:
            for quarter in range(start_quarter, 5):
                tmp.append((cur_year, quarter))
            start_quarter = 1
            cur_year += 1

        for quarter in range(start_quarter, end_quarter + 1):
            tmp.append((end_year, quarter))

        for year, quarter in tmp:
            try:
                msg = "Recalculate quarterly_budget table for year {0}, quarter {1}"
                msg = msg.format(year, quarter)
                LOG.debug(msg)
                self.analytics.recalculate_quarterly_budget(year, quarter)
            except:
                msg = "Recalculate quarterly_budget table for year {0}, quarter {1} failed"
                msg = msg.format(year, quarter, helper.exc_info())
                LOG.exception(msg)

    def before_iteration(self):
        self.tmp_dir = tempfile.mkdtemp()

        gevent.sleep(0)  # XXX
        self.analytics.load_usage_types()
        self.analytics.load_usage_items()

        self.aws_billing_dtime_from = None

    def after_iteration(self):
        shutil.rmtree(self.tmp_dir)

    def do_iteration(self):
        try:
            self.process_poller_billing()
        except:
            msg = 'Unable to process poller_sessions table, reason: {0}'
            msg = msg.format(helper.exc_info(where=False))
            LOG.exception(msg)

        try:
            self.process_aws_billing()
        except:
            msg = 'Unable to process AWS billing information, reason: {0}'
            msg = msg.format(helper.exc_info(where=False))
            LOG.exception(msg)


        if self.args['--recalculate']:
            sys.exit(0)

    def on_iteration_error(self):
        self.pool.kill()
        if self.args['--recalculate']:
            sys.exit(1)


def main():
    global app
    app = AnalyticsProcessing()
    try:
        app.load_config()
        app.configure()
        app.run()
    except exceptions.AlreadyRunningError:
        LOG.info(helper.exc_info(where=False))
    except (SystemExit, KeyboardInterrupt):
        pass
    except:
        LOG.exception('Oops')


if __name__ == '__main__':
    main()
