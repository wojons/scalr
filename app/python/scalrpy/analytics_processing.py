# vim: tabstop=4 shiftwidth=4 softtabstop=4
#
# Copyright 2013, 2014 Scalr Inc.
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

import time
import socket
import gevent
import datetime
import itertools

from scalrpy.util import helper
from scalrpy.util import analytics
from scalrpy.util import dbmanager
from scalrpy.util import exceptions
from scalrpy.util import application
from scalrpy.util.analytics import platforms

from scalrpy import LOG


helper.patch_gevent()


app = None



class AnalyticsProcessing(application.ScalrIterationApplication):

    def __init__(self, argv=None):
        self.description = "Scalr Cost Analytics processing application"

        options = ("  --recalculate                     recalculate data ")
        self.add_options(options)

        options = (
            """  --platform <platform>             platform to recalculate\n"""
            """\t\t\t\t\t[cloudstack, ec2, ecs, eucalyptus, gce, ocs, nebula,\n"""
            """\t\t\t\t\tidcf, openstack, rackspacenguk, rackspacengus]""")
        self.add_options(options)

        options = "  --date-from <date>                'YYYY-MM-DD' UTC"
        self.add_options(options)

        options = "  --date-to <date>                  'YYYY-MM-DD' UTC"
        self.add_options(options)

        super(AnalyticsProcessing, self).__init__(argv=argv)

        self.config['connections'].update({
            'analytics': {
                'user': None,
                'pass': None,
                'host': None,
                'port': 3306,
                'name': None,
                'pool_size': 50,
            },
        })
        self.config.update({
            'pool_size': 100,
            'interval': 1800,
            'recalculate': False,
            'date_from': False,
            'date_to': False,
            'platform': False,
        })

        self.scalr_db = None
        self.analytics_db = None
        self.analytics = None
        self._pool = None


    def validate_args(self):
        application.ScalrIterationApplication.validate_args(self)
        if self.args['--recalculate']:
            assert_msg = "To recalculate data you must specify platform argument"
            assert self.args['--platform'], assert_msg
            assert_msg = "Platform %s is not supported" % self.args['--platform']
            assert self.args['--platform'] in platforms, assert_msg

            assert_msg = "To recalculate data you must specify date-from argument"
            assert self.args['--date-from'], assert_msg
            assert_msg = "Wrong date format, use Y-m-d"
            try:
                datetime.datetime.strptime(self.args['--date-from'], '%Y-%m-%d')
            except:
                raise AssertionError(assert_msg)


    def validate_config(self):
        application.ScalrIterationApplication.validate_config(self)
        if self.config['recalculate'] and (not self.config['platform'] or not self.config['date_from']):
            sys.stderr.write("Error. You must specify 'platform' and 'date-from' for recalculating\n")
            sys.exit(1)


    def configure(self):
        enabled = self.scalr_config.get('analytics', {}).get('enabled', False)
        if not enabled:
            sys.stdout.write('Analytics is disabled. Exit\n')
            sys.exit(0)
        helper.update_config(
                self.scalr_config.get('analytics', {}).get('connections', {}).get('scalr', {}),
                self.config['connections']['mysql'])
        helper.update_config(
                self.scalr_config.get('analytics', {}).get('connections', {}).get('analytics', {}),
                self.config['connections']['analytics'])
        helper.update_config(
                self.scalr_config.get('analytics', {}).get('processing', {}),
                self.config)
        helper.validate_config(self.config)

        self.config['pool_size'] = max(21, self.config['pool_size'])
        self.config['recalculate'] = self.args['--recalculate']
        self.config['date_from'] = self.args['--date-from']
        self.config['date_to'] = self.args['--date-to']
        self.config['platform'] = self.args['--platform']

        if self.config['recalculate']:
            self.iteration_timeout = None
        else:
            self.iteration_timeout = self.config['interval'] - 5

        self.scalr_db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self.analytics_db = dbmanager.DB(self.config['connections']['analytics'])
        self.analytics = analytics.Analytics(self.scalr_db, self.analytics_db)
        self._pool = helper.GPool(pool_size=self.config['pool_size'])

        socket.setdefaulttimeout(self.config['instances_connection_timeout'])


    def _get_processing_dtime(self):
        dtime_hour_ago = datetime.datetime.utcfromtimestamp(int(time.time()) - 3600)

        if self.config['recalculate'] or self.config['date_from']:
            dtime_from = datetime.datetime.strptime(self.config['date_from'], '%Y-%m-%d')
            dtime_from = dtime_from.replace(hour=0)
        else:
            dtime_from = dtime_hour_ago
        dtime_from = dtime_from.replace(minute=0, second=0)

        two_weeks_ago = datetime.date.today() + datetime.timedelta(days=-14)

        assert_msg = '(Re)calculating is not supported for dtime-from more than two weeks ago'
        assert dtime_from.date() > two_weeks_ago, assert_msg

        assert_msg = '(Re)calculating is not supported for future'
        assert datetime.datetime.utcnow().replace(minute=0, second=0, microsecond=0) > dtime_from, assert_msg

        if self.config['date_to'] and self.config['date_from']:
            dtime_to = datetime.datetime.strptime(self.config['date_to'], '%Y-%m-%d')
            dtime_to = dtime_to.replace(hour=23)
        else:
            dtime_to = dtime_hour_ago
        dtime_to = min(dtime_to, dtime_hour_ago)
        dtime_to = dtime_to.replace(minute=59, second=59)

        return dtime_from, dtime_to


    def _set_servers_cost(self, servers):
        prices = self.analytics.get_prices(servers)
        for server in servers:
            server['cost'] = self.analytics.get_cost_from_prices(server, prices)


    def _set_usage_cost(self, records):
        prices = self.analytics.get_prices(records)
        for record in records:
            cost = self.analytics.get_cost_from_prices(record, prices) or 0
            try:
                record['cost'] = float(cost) * int(record['num'])
            except:
                msg = 'Unable to update usage cost, reason: {error}'
                msg = msg.format(error=helper.exc_info())
                LOG.error(msg)


    def calculate(self, date, hour):
        try:
            msg = "Calculate date {0}, hour {1}".format(date, hour)
            LOG.info(msg)
            for managed_servers, not_managed_servers in itertools.izip_longest(
                        self.analytics.get_managed_servers(date, hour),
                        self.analytics.get_not_managed_servers(date, hour)):
                managed_servers = managed_servers or []
                LOG.info('Managed servers for processing: %s' % len(managed_servers))
                not_managed_servers = not_managed_servers or []
                LOG.info('Not managed servers for processing: %s' % len(not_managed_servers))

                self._set_servers_cost(managed_servers + not_managed_servers)

                for server in managed_servers:
                    self._pool.wait()
                    self._pool.apply_async(self.analytics.insert_managed_server, (server,))
                    gevent.sleep(0)  # force switch

                for server in not_managed_servers:
                    self._pool.wait()
                    self._pool.apply_async(self.analytics.insert_not_managed_server, (server,))
                    gevent.sleep(0)  # force switch

            self._pool.join()
            self.analytics.fill_farm_usage_d(date, hour)

        except:
            msg = "Unable to calculate date {date}, hour {hour}, reason: {error}".format(
                    date=date, hour=hour, error=helper.exc_info())
            raise Exception(msg)


    def recalculate(self, date, hour):
        try:
            msg = "Recalculate hourly tables for date {0}, hour {1}".format(date, hour)
            LOG.info(msg)

            for usage_h_records, nm_usage_h_records in itertools.izip_longest(
                        self.analytics.get_usage_h_records(date, hour, self.config['platform']),
                        self.analytics.get_nm_usage_h_records(date, hour, self.config['platform'])):
                usage_h_records = usage_h_records or []
                LOG.info('usage_h records for recalculating: %s' % len(usage_h_records))
                nm_usage_h_records = nm_usage_h_records or []
                LOG.info('nm_usage_h records for recalculating: %s' % len(nm_usage_h_records))

                self._set_usage_cost(usage_h_records + nm_usage_h_records)

                for record in usage_h_records:
                    self._pool.wait()
                    self._pool.apply_async(self.analytics.update_usage_h, (record,))
                    gevent.sleep(0)  # force switch

                for record in nm_usage_h_records:
                    self._pool.wait()
                    self._pool.apply_async(self.analytics.update_nm_usage_h, (record,))
                    gevent.sleep(0)  # force switch

            self._pool.join()
            self.analytics.fill_farm_usage_d(date, hour, platform=self.config['platform'])

        except:
            msg = "Unable to recalculate date {date}, hour {hour}, reason: {error}".format(
                date=date, hour=hour, error=helper.exc_info())
            raise Exception(msg)


    def do_iteration(self):
        try:
            dtime_from, dtime_to = self._get_processing_dtime()

            dtime_cur = dtime_from
            while dtime_cur <= dtime_to:
                date, hour = dtime_cur.date(), dtime_cur.hour
                try:
                    if self.config['recalculate']:
                        self.recalculate(date, hour)
                    else:
                        self.calculate(date, hour)
                except KeyboardInterrupt:
                    raise
                except:
                    LOG.error(helper.exc_info())
                dtime_cur += datetime.timedelta(seconds=3600)

            self._pool.join()

            if not self.config['recalculate']:
                return

            # recalculate daily tables
            dtime_cur = dtime_from
            while dtime_cur <= dtime_to:
                date = dtime_cur.date()
                msg = "Recalculate daily tables for date {0}".format(date)
                LOG.info(msg)
                try:
                    self.analytics.recalculate_usage_d(date, self.config['platform'])
                except:
                    msg = "Recalculate usage_d table for date {0} failed, reason: {1}"
                    msg = msg.format(date, helper.exc_info())
                    LOG.warning(msg)
                try:
                    self.analytics.recalculate_nm_usage_d(date, self.config['platform'])
                except:
                    msg = "Recalculate nm_usage_d table for date {0} failed, reason: {1}"
                    msg = msg.format(date, helper.exc_info())
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
                    msg = "Recalculate quarterly_budget table for year {0}, quarter {1} failed, reason: {2}"
                    msg = msg.format(year, quarter, helper.exc_info())
                    LOG.warning(msg)
        except:
            if self.config['recalculate']:
                LOG.exception(helper.exc_info())
                sys.exit(1)
            else:
                raise

        # quit from iteration loop
        raise exceptions.QuitError()



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
