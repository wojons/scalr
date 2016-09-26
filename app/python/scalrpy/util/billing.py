from gevent import monkey
monkey.patch_all()

import os
import sys
import re
import shutil
import gevent
import csv
import zipfile
import boto
import boto.s3
import boto.s3.key
import datetime
import requests
import json
import uuid
import time
import threading

from scalrpy.util import analytics
from scalrpy.util import helper
from scalrpy.util import cryptotool
from scalrpy import exceptions
from scalrpy import LOG


helper.patch_gevent()

days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]

insert_chunk_size = 100


def get_default_aws_csv_file_name(aws_account_id, date=None):
    date = date or datetime.datetime.utcnow().date()
    template = '{}-aws-billing-detailed-line-items-with-resources-and-tags-{:4d}-{:02d}.csv.zip'
    return template.format(aws_account_id, date.year, date.month)


def get_aws_csv_file_name_tmplate(aws_account_id, date=None):
    date = date or datetime.datetime.utcnow().date()
    template = r'{}-aws-billing-detailed-line-items-with-resources-and-tags(.*)-{:4d}-{:02d}.csv.zip'
    return template.format(aws_account_id, date.year, date.month)


class Billing(object):

    _farm_usage_d_dates = {}

    def __init__(self, analytics, config, pool_size=100):
        self.analytics = analytics
        self.config = config
        self.pool = helper.GPool(pool_size=pool_size)
        if self.analytics not in Billing._farm_usage_d_dates:
            Billing._farm_usage_d_dates[self.analytics] = set()

    @property
    def farm_usage_d_dates(self):
        return Billing._farm_usage_d_dates[self.analytics]

    @farm_usage_d_dates.setter
    def farm_usage_d_dates(self, value):
        Billing._farm_usage_d_dates[self.analytics] = value

    def load_records_data(self, records):
        self.analytics.load_servers_data(records)
        return records

    def get_records(self, rows):
        records = []
        for row in rows:
            record = self.get_record(row)
            if record:
                records.append(record)
        records = self.analytics.remove_existing_records_with_record_id(records)
        return records

    def fill_farm_usage_d(self, platform=None, force=False):
        with self.analytics.farm_usage_d_lock:
            try:
                for date in sorted(list(self.farm_usage_d_dates)):
                    if date == datetime.datetime.utcnow().date():
                        hour = (datetime.datetime.utcnow() - datetime.timedelta(hours=1)).hour
                    else:
                        hour = 23
                    try:
                        if not force:
                            utcnow = datetime.datetime.utcnow()
                            two_weeks_ago = (utcnow + datetime.timedelta(days=-14)).date()
                            if date < two_weeks_ago:
                                raise Exception('dtime-from more than two weeks ago')
                        msg = 'fill_farm_usage_d date: {}'.format(date)
                        LOG.debug(msg)
                        self.analytics.fill_farm_usage_d(date, hour, platform=platform)
                    except:
                        msg = 'Unable to fill farm_usage_d table for date {}, hour {}'
                        msg = msg.format(date, hour)
                        helper.handle_error(message=msg)
            finally:
                self.farm_usage_d_dates = set()

    def on_insert_records(self, records):
        with self.analytics.farm_usage_d_lock:
            for record in records:
                self.farm_usage_d_dates.add(record['dtime'].date())


class AWSBilling(Billing):

    period = 60 * 60
    timeout = 60 * 60 * 6

    usage_start_dtime_format = "%Y-%m-%d %H:%M:%S"
    last_modified_format = "%a, %d %b %Y %H:%M:%S %Z"

    def __init__(self, *args, **kwds):
        kwds['pool_size'] = kwds.get('pool_size', 100)
        super(AWSBilling, self).__init__(*args, **kwds)
        self.cache_dir = '/tmp/scalr-aws-billing-cache'
        self.downloading_locks = {}
        self._downloading_lock = gevent.lock.RLock()
        self.max_simultaneously_envs = 10
        self.pool_factor = 5 / 10.0

    def _create_cache_dir(self):
        assert re.match(r'^/tmp/.+', self.cache_dir)
        if not os.path.exists(self.cache_dir):
            os.makedirs(self.cache_dir, 0o0700)

    def _remove_cache_dir(self):
        assert re.match(r'^/tmp/.+', self.cache_dir)
        if os.path.exists(self.cache_dir):
            shutil.rmtree(self.cache_dir)

    def download_billing_file(self, env, date=None, force=False):
        date = date or datetime.datetime.utcnow().date()
        bucket_name = env['ec2.detailed_billing.bucket']
        if env.get('ec2.detailed_billing.payer_account'):
            envs = self.analytics.load_aws_accounts_ids_envs([env['ec2.detailed_billing.payer_account']])
            self.analytics.load_env_credentials(envs, platform='ec2')
            for e in envs:
                if e['client_id'] == env['client_id']:
                    credentials_env = e
                    break
            else:
                msg = 'Can not found AWS credentials for PayerAccount {}'
                msg = msg.format(env['ec2.detailed_billing.payer_account'])
                raise Exception(msg)
        else:
            credentials_env = env.copy()
        kwds = {
            'aws_access_key_id': cryptotool.decrypt_scalr(self.config['crypto_key'],
                                                          credentials_env['ec2.access_key']),
            'aws_secret_access_key': cryptotool.decrypt_scalr(self.config['crypto_key'],
                                                              credentials_env['ec2.secret_key']),
            'proxy': self.config['proxy'].get('aws', {}).get('host'),
            'proxy_port': self.config['proxy'].get('aws', {}).get('port'),
            'proxy_user': self.config['proxy'].get('aws', {}).get('user'),
            'proxy_pass': self.config['proxy'].get('aws', {}).get('pass'),
        }
        default_region_map = {
            'regular': 'us-east-1',
            'gov-cloud': 'us-gov-west-1',
            'cn-cloud': 'cn-north-1',
        }
        default_region = default_region_map[env.get('account_type', 'regular')]
        region = env.get('ec2.detailed_billing.region', default_region)
        conn = boto.s3.connect_to_region(region, **kwds)
        bucket = conn.get_bucket(bucket_name)
        default_file_name = get_default_aws_csv_file_name(credentials_env['ec2.account_id'], date)
        file_name_tmplate = get_aws_csv_file_name_tmplate(credentials_env['ec2.account_id'], date)
        files_in_bucket = [key.name for key in bucket.list()
                           if re.match(file_name_tmplate, key.name)]
        if not files_in_bucket:
            utcnow = datetime.datetime.utcnow()
            if date.month == utcnow.month and utcnow.day < 2:
                return None
            else:
                msg = "Not found any valid files({}, {}) in bucket '{}'"
                msg = msg.format(default_file_name, file_name_tmplate, bucket_name)
                raise exceptions.FileNotFoundError(msg)
        if default_file_name not in files_in_bucket:
            file_name = files_in_bucket[0]  # use first valid file
            msg = "Default AWS detailed billing statistics file '{}' not found in bucket '{}', available {}, use '{}'"
            msg = msg.format(default_file_name, bucket_name, files_in_bucket, file_name)
            LOG.warning(msg)
        else:
            file_name = default_file_name
        try:
            key = bucket.get_key(file_name)
            last_modified_dt = datetime.datetime.strptime(key.last_modified, self.last_modified_format)
            utcnow = datetime.datetime.utcnow()

            seconds_from_last_modified_dt = (utcnow - last_modified_dt).seconds
            if hasattr(self, 'task_info') and self.task_info.get('period') > 300:
                delta = datetime.timedelta(seconds=self.task_info['period'] - 90)
            else:
                delta = datetime.timedelta(seconds=210)
            condition1 = utcnow > last_modified_dt and utcnow < last_modified_dt + delta
            condition2 = seconds_from_last_modified_dt > 3600
            condition3 = (seconds_from_last_modified_dt / 3600) % 24 == 0

            if force or condition1 or (condition2 and condition3):
                with self._downloading_lock:
                    self.downloading_locks.setdefault(file_name, gevent.lock.RLock())
                with self.downloading_locks[file_name]:
                    csv_zip_file = os.path.join(self.cache_dir, file_name)
                    csv_file = csv_zip_file.rstrip('.zip')
                    if os.path.exists(csv_file):
                        msg = "'{}' already exists in cache directory, use it"
                        msg = msg.format(os.path.basename(csv_file))
                        LOG.debug(msg)
                        return csv_file
                    while key.size * 3 > helper.get_free_space(self.cache_dir):
                        LOG.error('Disk is full, waiting 60 sec')
                        gevent.sleep(60)
                    LOG.debug("Downloading '{}' for environment {}".format(file_name, env['id']))
                    attempts = 2
                    downloading_start_time = time.time()
                    while True:
                        try:
                            key.get_contents_to_filename(csv_zip_file)
                            assert os.path.isfile(csv_zip_file), os.listdir(self.cache_dir)
                            downloading_end_time = time.time()
                            break
                        except:
                            attempts -= 1
                            if not attempts:
                                raise
                    downloading_time = downloading_end_time - downloading_start_time
                    msg = "Downloading '{0}' done in {1:.1f} seconds".format(file_name, downloading_time)
                    LOG.info(msg)
                    LOG.debug('Unzipping to {}'.format(csv_file))
                    while os.path.getsize(csv_zip_file) * 1 > helper.get_free_space(self.cache_dir):
                        LOG.error('Disk is full, waiting 60 sec')
                        gevent.sleep(60)
                    with zipfile.ZipFile(csv_zip_file, 'r') as f:
                        f.extract(f.infolist()[0], self.cache_dir)
                    os.remove(csv_zip_file)
                    return csv_file
            else:
                msg = "Skipping AWS billing file '{}' for environment {}"
                msg = msg.format(file_name, env['id'])
                LOG.debug(msg)
                return None
        except:
            msg = "File '{}', bucket '{}', reason: {}"
            msg = msg.format(file_name, bucket_name, helper.exc_info())
            raise Exception, Exception(msg), sys.exc_info()[2]

    def filter_compute(self, rows):
        rows = [row for row in rows
                if ('BoxUsage' in row['UsageType'] or 'HeavyUsage' in row['UsageType'])
                and 'RunInstances' in row['Operation']]
        return rows

    def restore_scalr_meta(self, rows):
        instances_ids = list(set([str(row['ResourceId']) for row in self.filter_compute(rows)]))
        if not instances_ids:
            return
        query = (
            "SELECT sh.env_id, sh.farm_id, sh.farm_roleid farm_role_id, sh.server_id, "
            " sh.cloud_server_id instance_id "
            "FROM servers_history sh "
            "WHERE sh.platform='ec2' "
            "AND sh.cloud_server_id IN ({instances_ids}) "
        ).format(instances_ids=str(instances_ids)[1:-1])
        results = self.analytics.scalr_db.execute(query, retries=1)
        scalr_meta = {
            r['instance_id']: 'v1:{env_id}:{farm_id}:{farm_role_id}:{server_id}'.format(**r)
            for r in results
        }
        for row in rows:
            if row['ResourceId'] in scalr_meta:
                row['user:scalr-meta'] = scalr_meta[row['ResourceId']]

    def csv_reader(self, csv_file, envs, dtime_from=None, dtime_to=None):
        envs_ids = [int(env['id']) for env in envs]
        aws_account_id = envs[0]['ec2.account_id']
        dtime_to = dtime_to or datetime.datetime.utcnow()

        def check_quantity(row):
            try:
                return float(row['UsageQuantity']) != 0.0
            except:
                return False

        with open(csv_file, 'r') as f:

            def reader(chunk_size=1000):
                rows = []
                for row in csv.DictReader(f):
                    rows.append(row)
                    if len(rows) >= chunk_size:
                        yield rows
                        rows = []
                if rows:
                    yield rows

            for rows in reader():
                rows = [row for row in rows
                        if check_quantity(row) and row['RecordType'] == 'LineItem']
                if rows and 'user:scalr-meta' not in rows[0]:
                    self.restore_scalr_meta(rows)
                rows_for_yield = []
                for row in rows:
                    try:
                        if not row.get('user:scalr-meta'):
                            continue
                        row['scalr_meta'] = helper.get_scalr_meta(row['user:scalr-meta'])
                        if envs_ids and row['scalr_meta'].get('env_id'):
                            if row['scalr_meta']['env_id'] not in envs_ids:
                                continue
                        if aws_account_id and row['LinkedAccountId'] != aws_account_id:
                            continue
                        start_dtime = datetime.datetime.strptime(row['UsageStartDate'],
                                                                 self.usage_start_dtime_format)
                        if dtime_from and start_dtime < dtime_from:
                            continue
                        if start_dtime > dtime_to:
                            break
                        rows_for_yield.append(row)
                    except:
                        helper.handle_error(message='CSV reader error')
                if rows_for_yield:
                    yield rows_for_yield

    aws_pattern_1 = re.compile(r"^.*Usage:(?P<item_name>.*)$")
    aws_pattern_2 = re.compile(r"^.*VolumeUsage.(?P<item_name>.*)$")
    aws_pattern_3_in = re.compile(r"^(?P<item_name>.*)-In-Bytes$")
    aws_pattern_3_out = re.compile(r"^(?P<item_name>.*)-Out-Bytes$")
    aws_pattern_3_reg = re.compile(r"^(?P<item_name>.*)-Regional-Bytes$")

    @classmethod
    def get_record(cls, row):
        record = row['scalr_meta']
        record['aws_record_id'] = row['RecordId']
        record['dtime'] = datetime.datetime.strptime(row['UsageStartDate'],
                                                     cls.usage_start_dtime_format)
        record['record_date'] = record['dtime'].date()
        record['instance_id'] = row['ResourceId']
        record['platform'] = 'ec2'
        record['url'] = ''
        try:
            record['cost'] = float(row['Cost'])
        except KeyError:
            record['cost'] = float(row['UnBlendedCost'])
        record['cost'] = round(record['cost'], 9)

        cost_distr_type = 4
        usage_type_name = 'Other'
        usage_item_name = ''

        # Compute
        if ('BoxUsage' in row['UsageType'] or 'HeavyUsage' in row['UsageType']) \
                and 'RunInstances' in row['Operation']:
            cost_distr_type = 1
            usage_type_name = 'BoxUsage'
            match = cls.aws_pattern_1.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')

        # Storage
        elif 'EBS:VolumeUsage' in row['UsageType'] and 'CreateVolume' in row['Operation']:
            cost_distr_type = 2
            usage_type_name = 'EBS'
            match = cls.aws_pattern_2.match(row['UsageType'])
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
            match = cls.aws_pattern_3_in.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')
        elif '-Out-Bytes' in row['UsageType']:
            cost_distr_type = 3
            usage_type_name = 'Out'
            match = cls.aws_pattern_3_out.match(row['UsageType'])
            if match:
                usage_item_name = match.group('item_name')
        elif 'DataTransfer-Regional-Bytes' in row['UsageType']:
            cost_distr_type = 3
            usage_type_name = 'Regional'
            match = cls.aws_pattern_3_reg.match(row['UsageType'])
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

        record['num'] = round(record['num'], 2)

        record['record_id'] = cls.get_record_id(record)
        return record

    @classmethod
    def get_record_id(cls, record):
        return record['aws_record_id']

    def fix_records_with_missing_server_id(self, records):
        incomplete_records = [record for record in records if not record['server_id']]
        self.analytics.get_server_id_by_instance_id(incomplete_records, 'ec2')

    def get_billing_interval(self):
        utcnow = datetime.datetime.utcnow()
        if self.config['dtime_from']:
            dtime_from = self.config['dtime_from']
        else:
            dtime_from = datetime.datetime.utcnow().replace(day=1,
                                                            hour=0,
                                                            minute=0,
                                                            second=0,
                                                            microsecond=0)
            if utcnow.day < 15:
                dtime_from = helper.previous_month(dtime_from)
        dtime_to = self.config['dtime_to'] or datetime.datetime.utcnow().replace(microsecond=999999)
        return dtime_from, dtime_to

    def _env_unique_key(self, env):
        unique_key = '{};{};{};{}'.format(env['ec2.access_key'],
                                          env['ec2.secret_key'],
                                          env.get('ec2.detailed_billing.bucket'),
                                          env.get('ec2.detailed_billing.payer_account'))
        return unique_key

    def csv_files(self, envs, date=None):
        downloaded_csv_files = []
        try:
            unique_envs_map = {}
            for env in envs:
                unique_envs_map.setdefault(self._env_unique_key(env), []).append(env)
            for unique_envs in unique_envs_map.values():
                try:
                    csv_file = self.download_billing_file(unique_envs[0], date=date,
                                                          force=bool(self.config['dtime_to']))
                    if csv_file:
                        downloaded_csv_files.append(csv_file)
                        yield csv_file, unique_envs
                except:
                    envs_ids = [env['id'] for env in unique_envs]
                    msg = 'AWS billing for environments {}, month {} failed'
                    msg = msg.format(envs_ids, date.month)
                    if isinstance(sys.exc_info()[1], exceptions.FileNotFoundError):
                        helper.handle_error(message=msg, level='warning')
                    else:
                        helper.handle_error(message=msg)
        finally:
            for csv_file in downloaded_csv_files:
                if os.path.exists(csv_file):
                    try:
                        os.remove(csv_file)
                    except:
                        msg = 'Unable to remove file {}'.format(csv_file)
                        helper.handle_error(message=msg)

    @helper.timeit
    def process_csv_file(self, csv_file, envs, dtime_from=None, dtime_to=None):
        envs_ids = list(set(int(env['id']) for env in envs))
        for rows in self.csv_reader(csv_file, envs, dtime_from=dtime_from, dtime_to=dtime_to):
            records = self.get_records(rows)
            self.fix_records_with_missing_server_id(records)
            records = [r for r in records if
                       r.get('server_id') and
                       not (r.get('env_id') and r['env_id'] not in envs_ids) and
                       not (r['cost_distr_type'] == 1 and self.analytics.record_exists(r))]
            self.load_records_data(records)
            records = [record for record in records if record['env_id'] in envs_ids]

            # remove duplicates record with same record_id
            records = {record['record_id']: record for record in records}.values()

            for chunk in helper.chunks(records, insert_chunk_size):
                self.pool.wait()
                self.pool.apply_async(self.analytics.insert_records, (chunk,),
                                      {'callback': self.on_insert_records})
                gevent.sleep(0)  # force switch

    def process_envs(self, envs, dtime_from, dtime_to):
        try:
            dtime = dtime_from
            while dtime <= dtime_to:
                for csv_file, csv_file_envs in self.csv_files(envs, date=dtime.date()):
                    try:
                        self.process_csv_file(csv_file, csv_file_envs, dtime_from=dtime, dtime_to=dtime_to)
                    except:
                        msg = 'Processing CSV file: {}, environments: {} failed'
                        msg = msg.format(csv_file, [env['id'] for env in csv_file_envs])
                        helper.handle_error(message=msg)
                dtime = helper.next_month(dtime)
        except:
            msg = 'AWS billing for environments {} failed'
            msg = msg.format([env['id'] for env in envs])
            helper.handle_error(message=msg)

    def _wait_pool(self):
        wait_size = min(self.max_simultaneously_envs, self.config['pool_size'] * self.pool_factor)
        while len(self.pool) > wait_size:
            gevent.sleep(0.1)

    def __call__(self):
        try:
            dtime_from, dtime_to = self.get_billing_interval()
            msg = 'AWS billing interval: {} - {}'.format(dtime_from, dtime_to)
            LOG.info(msg)

            self._create_cache_dir()

            aws_accounts_ids = self.analytics.load_aws_accounts_ids()
            for chunk in helper.chunks(aws_accounts_ids, 100):
                envs = self.analytics.load_aws_accounts_ids_envs(chunk)
                self.analytics.load_env_credentials(envs, platform='ec2')
                envs = [env for env in envs if
                        env.get('ec2.detailed_billing.enabled', '0') == '1' and
                        env.get('ec2.detailed_billing.payer_account') in (None, '')]
                if not envs:
                    continue
                self._wait_pool()
                self.pool.apply_async(self.process_envs, args=(envs, dtime_from, dtime_to))

            aws_payers_accounts = self.analytics.load_aws_payers_accounts()
            for chunk in helper.chunks(aws_payers_accounts, 100):
                envs = self.analytics.load_aws_payers_accounts_envs(chunk)
                self.analytics.load_env_credentials(envs, platform='ec2')
                envs = [env for env in envs if
                        env.get('ec2.detailed_billing.enabled', '0') == '1']
                if not envs:
                    continue
                self._wait_pool()
                self.pool.apply_async(self.process_envs, args=(envs, dtime_from, dtime_to))

            self.pool.join()
        except:
            self.pool.kill()
            helper.handle_error(message='AWS billing failed')
            raise
        finally:
            self.downloading_locks = {}
            try:
                self._remove_cache_dir()
            except:
                msg = 'Unable to remove cache dir {}'
                msg = msg.format(self.cache_dir)
                helper.handle_error(message=msg, level='error')


class RecalculateAWSBilling(AWSBilling):

    timeout = 60 * 60 * 12

    def __init__(self, *args, **kwds):
        super(RecalculateAWSBilling, self).__init__(*args, **kwds)
        self.delete_lock = threading.Lock()

    def delete_data(self, csv_file, envs, period):
        envs_ids = list(set(int(env['id']) for env in envs))
        dtime_from, dtime_to = period

        msg = 'Deleting AWS detailed billing data for environments: {}, period: {} - {}'
        msg = msg.format(envs_ids, dtime_from, dtime_to)
        LOG.info(msg)

        with self.analytics.lock:
            self.analytics.analytics_db.autocommit(False)
            try:
                # aws_billing_records
                for rows in self.csv_reader(csv_file, envs, dtime_from=dtime_from, dtime_to=dtime_to):
                    records_ids = [row['RecordId'] for row in rows]
                    for chunk in helper.chunks(records_ids, 1000):
                        if chunk:
                            query = (
                                "DELETE FROM aws_billing_records "
                                "WHERE record_id IN ({record_id})"
                            ).format(record_id=str(chunk)[1:-1])
                            self.analytics.analytics_db.execute(query)

                _dtime_from = dtime_from
                step_days = 15
                while _dtime_from < dtime_to:
                    _dtime_to = min(_dtime_from + datetime.timedelta(days=step_days), dtime_to)

                    # usage_servers_h, usage_h
                    query = (
                        "DELETE uh, us "
                        "FROM usage_h uh "
                        "LEFT JOIN usage_servers_h us ON uh.usage_id=us.usage_id "
                        "WHERE uh.platform='ec2' "
                        "AND uh.dtime BETWEEN '{dtime_from}' AND '{dtime_to}' "
                        "AND uh.env_id IN ({env_id})"
                    ).format(env_id=str(envs_ids)[1:-1], dtime_from=_dtime_from, dtime_to=_dtime_to)
                    self.analytics.analytics_db.execute(query)

                    # usage_d
                    query = (
                        "DELETE FROM usage_d "
                        "WHERE platform='ec2' "
                        "AND date BETWEEN '{date_from}' AND '{date_to}' "
                        "AND env_id IN ({env_id})"
                    ).format(env_id=str(envs_ids)[1:-1], date_from=_dtime_from.date(), date_to=_dtime_to.date())
                    self.analytics.analytics_db.execute(query)

                    # farm_usage_d
                    query = (
                        "DELETE FROM farm_usage_d "
                        "WHERE platform='ec2' "
                        "AND date BETWEEN '{date_from}' AND '{date_to}' "
                        "AND env_id IN ({env_id})"
                    ).format(env_id=str(envs_ids)[1:-1], date_from=_dtime_from.date(), date_to=_dtime_to.date())
                    self.analytics.analytics_db.execute(query)
                    _dtime_from += datetime.timedelta(days=step_days)

                self.analytics.analytics_db.commit()
            except:
                self.analytics.analytics_db.rollback()
                raise
            finally:
                self.analytics.analytics_db.autocommit(True)

    def process_csv_file(self, csv_file, envs, dtime_from=None, dtime_to=None):
        assert dtime_from
        assert dtime_to
        dtime_to = min(dtime_to, dtime_from.replace(day=days_in_month[dtime_from.month],
                                                    hour=23, minute=59, second=59))
        period_for_deletion = (dtime_from.replace(microsecond=0), dtime_to.replace(microsecond=0))
        self.delete_data(csv_file, envs, period_for_deletion)
        super(RecalculateAWSBilling, self).process_csv_file(csv_file, envs,
                                                            dtime_from=dtime_from, dtime_to=dtime_to)

    def __call__(self):
        try:
            dtime_from, dtime_to = self.get_billing_interval()

            quarters_calendar = self.analytics.get_quarters_calendar()
            quarter_number = quarters_calendar.quarter_for_date(dtime_from.date())
            quarter_year = quarters_calendar.year_for_date(dtime_from.date())
            quarter_start_dtime, quarter_end_dtime = quarters_calendar.dtime_for_quarter(
                    quarter_number, year=quarter_year)

            if quarter_start_dtime < dtime_from:
                quarter_number, quarter_year = quarters_calendar.next_quarter(quarter_number, quarter_year)
                quarter_start_dtime, quarter_end_dtime = quarters_calendar.dtime_for_quarter(
                        quarter_number, year=quarter_year)

            while quarter_start_dtime < dtime_to:

                msg = 'Recalculate {} quarter ({} - {}) for year {}'
                msg = msg.format(quarter_number, quarter_start_dtime, quarter_end_dtime, quarter_year)
                LOG.info(msg)

                self.config['dtime_from'] = quarter_start_dtime
                self.config['dtime_to'] = min(quarter_end_dtime, dtime_to)

                super(RecalculateAWSBilling, self).__call__()
                self.fill_farm_usage_d(force=True)

                msg = 'Recalculate quarterly_budget'
                LOG.debug(msg)
                self.analytics.recalculate_quarterly_budget(quarter_year, quarter_number)

                quarter_number, quarter_year = quarters_calendar.next_quarter(quarter_number, quarter_year)
                quarter_start_dtime, quarter_end_dtime = quarters_calendar.dtime_for_quarter(
                        quarter_number, year=quarter_year)
        except:
            self.pool.kill()
            helper.handle_error(message='Recalculate AWS billing failed')
            raise


class PollerBilling(Billing):

    period = 60 * 30
    timeout = period - 5

    def get_billing_interval(self):
        dtime_hour_ago = datetime.datetime.utcnow() - datetime.timedelta(hours=1)
        dtime_from = self.config['dtime_from'] or dtime_hour_ago.replace(minute=0,
                                                                         second=0,
                                                                         microsecond=0)
        dtime_to = self.config['dtime_to'] or dtime_hour_ago.replace(minute=59,
                                                                     second=59,
                                                                     microsecond=999999)
        return dtime_from, dtime_to

    def __call__(self):
        try:
            dtime_from, dtime_to = self.get_billing_interval()
            LOG.info('Scalr Poller billing interval: {} - {}'.format(dtime_from, dtime_to))

            dtime_cur = dtime_from
            while dtime_cur <= dtime_to:
                date, hour = dtime_cur.date(), dtime_cur.hour
                for platform in self.config['platform']:
                    try:
                        msg = "Process Scalr Poller data, date {}, hour {}, platform '{}'"
                        msg = msg.format(date, hour, platform)
                        LOG.debug(msg)
                        for records in self.analytics.get_poller_servers(date, hour, platform=platform):
                            LOG.debug('Scalr Poller records for processing: {}'.format(len(records)))
                            prices = self.analytics.get_prices(records)
                            for record in records:
                                cost = self.analytics.get_cost_from_prices(record, prices) or 0
                                record['cost'] = cost
                                record['num'] = 1.0
                                record['cost_distr_type'] = 1
                            for chunk in helper.chunks(records, insert_chunk_size):
                                self.pool.wait()
                                self.pool.apply_async(self.analytics.insert_records, (chunk,),
                                                      {'callback': self.on_insert_records})
                                gevent.sleep(0)  # force switch
                    except:
                        msg = "Scalr Poller billing unable to process date {}, hour {}, platform '{}'"
                        msg = msg.format(date, hour, platform)
                        helper.handle_error(message=msg)
                self.pool.join()
                dtime_cur += datetime.timedelta(hours=1)
        except:
            self.pool.kill()
            helper.handle_error(message='Scalr Poller billing failed')
            raise


class RecalculatePollerBilling(PollerBilling):

    timeout = 60 * 60 * 6

    def __call__(self):
        try:
            dtime_from, dtime_to = self.get_billing_interval()
            LOG.info('Scalr Poller billing recalculate interval: {} - {}'.format(dtime_from, dtime_to))

            # process poller_session table
            dtime_cur = dtime_from
            while dtime_cur <= dtime_to:
                date, hour = dtime_cur.date(), dtime_cur.hour
                for platform in self.config['platform']:
                    try:
                        msg = "Recalculate Scalr Poller data, date {}, hour {}, platform '{}'"
                        msg = msg.format(date, hour, platform)
                        LOG.debug(msg)
                        for records in self.analytics.get_records(date, hour, platform):
                            LOG.debug('Scalr Poller records to recalculate: {}'.format(len(records)))
                            prices = self.analytics.get_prices(records)
                            for record in records:
                                cost = self.analytics.get_cost_from_prices(record, prices) or 0
                                self.pool.wait()
                                record['cost'] = float(cost) * int(record['num'])
                                self.pool.apply_async(self.analytics.update_record,
                                                          (record,),
                                                          {'callback': self.on_insert_records})
                                gevent.sleep(0)  # force switch
                    except:
                        msg = "Scalr Poller billing unable to recalculate date {}, hour {}, platform '{}'"
                        msg = msg.format(date, hour, platform)
                        helper.handle_error(message=msg)
                self.pool.join()
                dtime_cur += datetime.timedelta(hours=1)

            # recalculate daily tables
            dtime_cur = dtime_from
            while dtime_cur <= dtime_to:
                date = dtime_cur.date()
                for platform in self.config['platform']:
                    try:
                        msg = "Recalculate daily tables for date {}, platform '{}'"
                        msg = msg.format(date, platform)
                        LOG.debug(msg)
                        self.analytics.recalculate_usage_d(date, platform)
                    except:
                        msg = "Recalculate usage_d table for date {}, platform '{}' failed"
                        msg = msg.format(date, platform)
                        helper.handle_error(message=msg)
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
                    msg = "Recalculate quarterly_budget table for year {}, quarter {}"
                    msg = msg.format(year, quarter)
                    LOG.debug(msg)
                    self.analytics.recalculate_quarterly_budget(year, quarter)
                except:
                    msg = "Recalculate quarterly_budget table for year {}, quarter {} failed"
                    msg = msg.format(year, quarter, helper.exc_info(where=False))
                    helper.handle_error(message=msg)
        except:
            self.pool.kill()
            helper.handle_error(message='Recalculate Scalr Poller billing failde')


class AzureBilling(Billing):

    period = 60 * 60 * 2
    timeout = period - 5

    ratecard_url = 'https://management.azure.com/subscriptions/{subscription_id}/providers/Microsoft.Commerce/RateCard'
    usage_url = 'https://management.azure.com/subscriptions/{subscription_id}/providers/Microsoft.Commerce/UsageAggregates'
    token_url = 'https://login.windows.net/{tenant_id}/oauth2/token'
    api_version = '2015-06-01-preview'

    def __init__(self, *args, **kwds):
        kwds['pool_size'] = kwds.get('pool_size', 100)
        super(AzureBilling, self).__init__(*args, **kwds)
        self.meters_rates = {}
        self.max_simultaneously_envs = 50
        self.pool_factor = 5 / 10.0
        self.proxy = None
        if self.config['proxy'].get('azure'):
            self.proxy = {
                'http': self.config['proxy']['azure']['url'],
                'https': self.config['proxy']['azure']['url']
            }

    def load_access_token(self, env):
        headers = {'Content-Type': 'application/x-www-form-urlencoded'}
        tenant_id = cryptotool.decrypt_scalr(self.config['crypto_key'], env['azure.tenant_name'])
        url = self.token_url.format(tenant_id=tenant_id)
        data = {
                'grant_type': 'client_credentials',
                'client_id': self.config['azure_app_client_id'],
                'resource': 'https://management.azure.com/',
                'client_secret': self.config['azure_app_secret_key'],
        }
        resp = requests.post(url, headers=headers, data=data, proxies=self.proxy)
        resp.raise_for_status()
        env['azure.access_token'] = str(resp.json()['access_token'])

    def load_meters_rates(self, subscription_id, access_token):
        url = self.ratecard_url.format(subscription_id=subscription_id)
        offer_durable_id = 'MS-AZR-0111p'
        currency = 'USD'
        locale = 'en-US'
        region_info = 'US'
        filt = ("OfferDurableId eq '{offer_durable_id}' and Currency eq '{currency}' "
                "and Locale eq '{locale}' and RegionInfo eq '{region_info}'").format(**locals())
        params = {
            'api-version': self.api_version,
            '$filter': filt,
        }
        headers = {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer %s' % access_token,
        }
        resp = requests.get(url, params=params, headers=headers, proxies=self.proxy)
        resp.raise_for_status()
        for meter in resp.json()['Meters']:
            self.meters_rates[meter['MeterId']] = meter

    def get_meter_rate(self, subscription_id, access_token, meter_id):
        if meter_id not in self.meters_rates:
            self.load_meters_rates(subscription_id, access_token)
        assert_msg = "Unable to load price for subscription_id: {}, meter_id: {}".format(
            subscription_id, meter_id)
        assert meter_id in self.meters_rates, assert_msg
        return self.meters_rates[meter_id]

    def set_cost(self, record, subscription_id, access_token, meter_id_usage):
        meter_rate = self.get_meter_rate(subscription_id, access_token, record['meter_id'])
        meter_rate['MeterRates'] = {float(k): float(v) for k, v in meter_rate['MeterRates'].items()}

        remaining_free_quantity = meter_rate['IncludedQuantity'] - meter_id_usage
        remaining_free_quantity *= remaining_free_quantity > 0
        non_free_quantity = record['quantity'] - remaining_free_quantity
        non_free_quantity *= non_free_quantity > 0

        current_rate = meter_rate['MeterRates'][0.0]

        if len(meter_rate['MeterRates']) == 1:
            record['cost'] = non_free_quantity * current_rate
        else:
            record['cost'] = 0.0
            for rate_quantity, quantity_rate in sorted(meter_rate['MeterRates'].items())[1:]:
                if non_free_quantity < rate_quantity:
                    record['cost'] += non_free_quantity * current_rate
                    break
                else:
                    record['cost'] += (rate_quantity - 1) * current_rate
                    non_free_quantity -= (rate_quantity - 1)
                current_rate = quantity_rate

    @classmethod
    def get_record_id(cls, record):
        unique = '{dtime};{resource_id};{meter_id};{cost_distr_type};{usage_type_name}'
        unique = unique.format(**record)
        return uuid.uuid5(analytics.UUID, unique).hex

    @classmethod
    def get_record(cls, row):
        record = {}
        properties = row['properties']

        cost_distr_type = 4
        usage_type_name = 'Other'
        usage_item_name = ''

        instance_data = json.loads(properties['instanceData'])

        tags = instance_data['Microsoft.Resources'].get('tags')
        if not tags or 'scalr-meta' not in tags:
            return
        record.update(helper.get_scalr_meta(tags['scalr-meta']))

        additional_info = instance_data['Microsoft.Resources'].get('additionalInfo', {})

        meter_category = properties['meterCategory']
        meter_name = properties['meterName']

        if meter_category == 'Virtual Machines' and meter_name == 'Compute Hours':
            if additional_info.get('UsageType') == 'ComputeHR':
                cost_distr_type = 1
                usage_type_name = 'BoxUsage'
                usage_item_name = additional_info['ServiceType']
            elif additional_info.get('UsageType') == 'ComputeHR_SW':
                usage_type_name = 'Software'
                usage_item_name = properties['meterSubCategory']
            else:
                return
            record['instance_type'] = additional_info['ServiceType']
            record['resource_id'] = instance_data['Microsoft.Resources']['resourceUri'].split('/')[-1]
            record['instance_id'] = record['resource_id']
            record['num'] = 1.0
            if not record['server_id']:
                record['server_id'] = record['resource_id']
        else:
            return

        record['meter_id'] = properties['meterId']
        record['dtime'] = datetime.datetime.strptime(properties['usageStartTime'].split('+')[0],
                                                     '%Y-%m-%dT%H:%M:%S')
        record['platform'] = 'azure'
        record['cloud_location'] = instance_data['Microsoft.Resources']['location']
        record['cloud_location'] = record['cloud_location'].lower().replace(' ', '')
        record['url'] = ''
        record['cost_distr_type'] = cost_distr_type
        record['usage_type_name'] = usage_type_name
        record['usage_item_name'] = usage_item_name
        assert record['usage_item_name'] or (cost_distr_type in (1, 4))
        record['quantity'] = float(properties['quantity'])
        if 'num' not in record:
            record['num'] = record['quantity']
        record['record_date'] = row['reported_date']
        record['record_id'] = cls.get_record_id(record)

        return record

    def get_usage(self, subscription_id, access_token, dtime_from, dtime_to, resolution='Hourly'):
        url = self.usage_url.format(subscription_id=subscription_id)
        step = datetime.timedelta(hours=12)
        reported_dtime_from = dtime_from
        if resolution == 'Hourly':
            reported_dtime_to = min(reported_dtime_from + step, dtime_to)
        elif resolution == 'Daily':
            reported_dtime_to = dtime_to.replace(hour=0)
        if reported_dtime_from == reported_dtime_to:
            return
        while reported_dtime_to <= dtime_to:
            try:
                msg = 'Request Azure billing for subscription {}, reported dtime: {} - {}'
                msg = msg.format(subscription_id, reported_dtime_from, reported_dtime_to)
                LOG.debug(msg)
                params = {
                    'api-version': self.api_version,
                    'reportedStartTime': reported_dtime_from.strftime('%Y-%m-%dT%H:%S:%M+00:00'),
                    'reportedEndTime': reported_dtime_to.strftime('%Y-%m-%dT%H:%M:%S+00:00'),
                    'aggregationGranularity': resolution,
                    'showDetails': 'true',
                }
                headers = {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer %s' % access_token,
                }
                session = requests.Session()

                def request():
                    next_link = requests.Request(method='get', url=url, params=params).prepare().url

                    @helper.retry(2, 2, requests.exceptions.RequestException)
                    def get():
                        return session.get(next_link, headers=headers, proxies=self.proxy)

                    while next_link:
                        resp = get()
                        resp.raise_for_status()
                        jsoned_resp = resp.json()
                        yield jsoned_resp
                        next_link = jsoned_resp.get('nextLink')

                for response in request():
                    assert 'value' in response, response
                    rows = []
                    for row in response['value']:
                        properties = row['properties']
                        if 'instanceData' not in properties:
                            # skip old format
                            continue
                        if properties['subscriptionId'] != subscription_id:
                            continue
                        row['reported_date'] = reported_dtime_from.date()
                        rows.append(row)
                    if rows:
                        yield rows

            except:
                msg = 'Azure get usage failed, subscription: {}, dtime: {} - {}'
                msg = msg.format(subscription_id, dtime_from, dtime_to)
                helper.handle_error(message=msg)

            if reported_dtime_to == dtime_to or resolution == 'Daily':
                break
            reported_dtime_from = reported_dtime_to
            reported_dtime_to = min(reported_dtime_to + step, dtime_to)

    def get_meters_ids_usage(self, subscription_id, access_token, dtime_from, dtime_to):
        records = []
        for rows in self.get_usage(subscription_id, access_token, dtime_from, dtime_to,
                                   resolution='Daily'):
            for row in rows:
                record = self.get_record(row)
                if record:
                    records.append(record)
        for rows in self.get_usage(subscription_id, access_token, dtime_to.replace(hour=0), dtime_to,
                                   resolution='Hourly'):
            for row in rows:
                record = self.get_record(row)
                if record:
                    records.append(record)

        tmp = {}
        for record in records:
            tmp.setdefault(record['meter_id'], {}).setdefault(record['dtime'].month, 0.0)
            tmp[record['meter_id']][record['dtime'].month] += record['quantity']
        return tmp

    def get_billing_interval(self):
        utcnow = datetime.datetime.utcnow()
        if hasattr(self, 'task_info') and self.task_info.get('iteration_number') == 1:
            _dtime_from = utcnow - datetime.timedelta(days=14)
        else:
            _dtime_from = utcnow - datetime.timedelta(days=2)
        _dtime_to = utcnow - datetime.timedelta(hours=3)
        dtime_from = self.config['dtime_from'] or _dtime_from.replace(minute=0, second=0, microsecond=0)
        dtime_to = self.config['dtime_to'] or _dtime_to.replace(minute=0, second=0, microsecond=0)
        return dtime_from, dtime_to

    def process_envs(self, envs, dtime_from, dtime_to):
        envs_ids = list(set([env['id'] for env in envs]))
        try:
            self.load_access_token(envs[0])

            subscription_id = envs[0]['azure.subscription_id']
            access_token = envs[0]['azure.access_token']

            begin_of_month = dtime_from.replace(day=1, hour=0)
            meters_ids_usage = self.get_meters_ids_usage(subscription_id, access_token,
                                                         begin_of_month, dtime_from)

            for rows in self.get_usage(subscription_id, access_token, dtime_from, dtime_to):
                records = self.get_records(rows)
                records = [record for record in records
                           if not (record.get('env_id') and record['env_id'] not in envs_ids)]
                self.load_records_data(records)
                records = [record for record in records if record['env_id'] in envs_ids]
                for record in records:
                    meters_ids_usage.setdefault(record['meter_id'], {}).setdefault(record['dtime'].month, 0.0)
                    self.set_cost(record, subscription_id, access_token,
                                  meters_ids_usage[record['meter_id']][record['dtime'].month])
                    meters_ids_usage[record['meter_id']][record['dtime'].month] += record['quantity']

                    self.pool.wait()
                    self.pool.apply_async(self.analytics.insert_record,
                                          (record,),
                                          {'callback': self.on_insert_records})
                    gevent.sleep(0)  # force switch
        except:
            msg = 'Azure billing for environments {} failed'
            msg = msg.format(envs_ids)
            helper.handle_error(message=msg)

    def _wait_pool(self):
        wait_size = min(self.max_simultaneously_envs, self.config['pool_size'] * self.pool_factor)
        while len(self.pool) > wait_size:
            gevent.sleep(0.1)

    def __call__(self):
        try:
            dtime_from, dtime_to = self.get_billing_interval()
            msg = 'Azure billing interval: {} - {}'.format(dtime_from, dtime_to)
            LOG.info(msg)

            azure_subscriptions_ids = self.analytics.load_azure_subscriptions_ids()
            for chunk in helper.chunks(azure_subscriptions_ids, 100):
                envs = self.analytics.load_azure_subscriptions_ids_envs(chunk)
                self.analytics.load_env_credentials(envs, platform='azure')
                if not envs:
                    continue
                self._wait_pool()
                self.pool.apply_async(self.process_envs, args=(envs, dtime_from, dtime_to))

            self.pool.join()
        except:
            self.pool.kill()
            helper.handle_error(message='Azure billing failed')
            raise
