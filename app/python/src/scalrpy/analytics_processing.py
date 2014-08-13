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

import sys
import time
import yaml
import gevent
import logging
import argparse
import datetime
import itertools

from scalrpy.util import cron
from scalrpy.util import helper
from scalrpy.util import analytics
from scalrpy.util import dbmanager

from gevent.pool import Group as Pool

from scalrpy import __version__


helper.patch_gevent()

CONFIG = {
    'connections': {
        'mysql': {
            'scalr': {
                'user': None,
                'pass': None,
                'host': None,
                'port': 3306,
                'name': None,
                'pool_size': 50,
            },
            'analytics': {
                'user': None,
                'pass': None,
                'host': None,
                'port': 3306,
                'name': None,
                'pool_size': 50,
            },
        },
    },
    'no_daemon': False,
    'recalculate': False,
    'platform': False,
    'pool_size': 50,
    'date_from': False,
    'date_to': False,
    'log_file': '/var/log/scalr.analytics-processing.log',
    'pid_file': '/var/run/scalr.analytics-processing.pid',
    'verbosity': 1,
}

LOG = logging.getLogger('ScalrPy')
CRYPTO_KEY = None
POOL = None
SCALR_DB = None
ANALYTICS_DB = None


def wait_pool():
    while len(POOL) >= CONFIG['pool_size']:
        gevent.sleep(0.2)


class IterationTimeoutError(Exception):
    pass


class AnalyticsProcessing(cron.Cron):

    def __init__(self):
        super(AnalyticsProcessing, self).__init__(CONFIG['pid_file'])
        self.analytics = analytics.Analytics(SCALR_DB, ANALYTICS_DB)

    def _get_processing_dtime(self):
        dtime_hour_ago = datetime.datetime.utcfromtimestamp(int(time.time())-3600)

        if CONFIG['recalculate'] or CONFIG['date_from']:
            dtime_from = datetime.datetime.strptime(CONFIG['date_from'], '%Y-%m-%d')
            dtime_from = dtime_from.replace(hour=0)
        else:
            dtime_from = dtime_hour_ago
        dtime_from = dtime_from.replace(minute=0, second=0)

        two_weeks_ago = datetime.date.today() + datetime.timedelta(days=-14)
        assert dtime_from.date() > two_weeks_ago
        assert datetime.datetime.utcnow().replace(minute=0, second=0, microsecond=0) > dtime_from

        if CONFIG['date_to'] and CONFIG['date_from']:
            dtime_to = datetime.datetime.strptime(CONFIG['date_to'], '%Y-%m-%d')
            dtime_to = dtime_to.replace(hour=23)
        else:
            dtime_to = dtime_hour_ago
        dtime_to = min(dtime_to, dtime_hour_ago)
        dtime_to = dtime_to.replace(minute=59, second=59)

        return dtime_from, dtime_to

    def _set_server_cost(self, servers):
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

    @helper.greenlet
    def calculate(self, date, hour):
        try:
            for managed_servers, not_managed_servers in itertools.izip_longest(
                        self.analytics.get_managed_servers(date, hour),
                        self.analytics.get_not_managed_servers(date, hour)):
                managed_servers = managed_servers or []
                LOG.debug('Managed servers for processing: %s' % len(managed_servers))
                not_managed_servers = not_managed_servers or []
                LOG.debug('Not managed servers for processing: %s' % len(not_managed_servers))

                self._set_server_cost(managed_servers + not_managed_servers)

                for server in managed_servers:
                    wait_pool()
                    POOL.apply_async(self.analytics.insert_managed_server, (server,))
                    gevent.sleep(0) # force switch

                for server in not_managed_servers:
                    wait_pool()
                    POOL.apply_async(self.analytics.insert_not_managed_server, (server,))
                    gevent.sleep(0) # force switch
        except:
            msg = "Unable to calculate date {date}, hour {hour}, reason: {error}".format(
                    date=date, hour=hour, error=helper.exc_info())
            raise Exception(msg)

    @helper.greenlet
    def recalculate(self, date, hour):
        try:
            for usage_h_records, nm_usage_h_records in itertools.izip_longest(
                        self.analytics.get_usage_h_records(date, hour, CONFIG['platform']),
                        self.analytics.get_nm_usage_h_records(date, hour, CONFIG['platform'])):
                usage_h_records = usage_h_records or []
                LOG.debug('usage_h records for recalculating: %s' % len(usage_h_records))
                nm_usage_h_records = nm_usage_h_records or []
                LOG.debug('nm_usage_h records for recalculating: %s' % len(nm_usage_h_records))

                self._set_usage_cost(usage_h_records + nm_usage_h_records)

                for record in usage_h_records:
                    wait_pool()
                    POOL.apply_async(self.analytics.update_usage_h, (record,))
                    gevent.sleep(0) # force switch

                for record in nm_usage_h_records:
                    wait_pool()
                    POOL.apply_async(self.analytics.update_nm_usage_h, (record,))
                    gevent.sleep(0) # force switch
        except:
            msg = "Unable to recalculate date {date}, hour {hour}, reason: {error}".format(
                date=date, hour=hour, error=helper.exc_info())
            raise Exception(msg)

    def _run(self):
        try:
            start_time = time.time()
            dtime_from, dtime_to = self._get_processing_dtime()

            dtime_cur = dtime_from
            while dtime_cur <= dtime_to:
                date, hour = dtime_cur.date(), dtime_cur.hour
                msg = "Processing date {0}, hour {1}".format(date, hour)
                LOG.debug(msg)
                try:
                    if CONFIG['recalculate']:
                        g = self.recalculate(date, hour)
                    else:
                        g = self.calculate(date, hour)
                    try:
                        g.get(timeout=600)
                    except gevent.Timeout:
                        raise IterationTimeoutError()
                    finally:
                        if not g.ready():
                            g.kill()
                except KeyboardInterrupt:
                    raise
                except:
                    LOG.error(helper.exc_info())
                dtime_cur += datetime.timedelta(seconds=3600)

            POOL.join()

            if not CONFIG['recalculate']:
                return

            # recalculate daily tables
            dtime_cur = dtime_from
            while dtime_cur <= dtime_to:
                date = dtime_cur.date()
                try:
                    msg = "Recalculate usage_d for date {0}".format(date)
                    LOG.debug(msg)
                    self.analytics.recalculate_usage_d(date, CONFIG['platform'])
                except:
                    msg = "Recalculate usage_d for date {0} failed, reason: {1}"
                    msg = msg.format(date, helper.exc_info())
                    LOG.warning(msg)
                try:
                    msg = "Recalculate nm_usage_d for date {0}".format(date)
                    LOG.debug(msg)
                    self.analytics.recalculate_nm_usage_d(date, CONFIG['platform'])
                except:
                    msg = "Recalculate nm_usage_d for date {0} failed, reason: {1}"
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
                    msg = "Recalculate quarterly_budget for year {0}, quarter {1}"
                    msg = msg.format(year, quarter)
                    LOG.debug(msg)
                    self.analytics.recalculate_quarterly_budget(year, quarter)
                except:
                    msg = "Recalculate quarterly_budget for year {0}, quarter {1} failed, reason: {2}"
                    msg = msg.format(year, quarter, helper.exc_info())
                    LOG.warning(msg)

        finally:
            LOG.info('End iteration: %s' % (time.time() - start_time))


def configure(config, args=None):
    enabled = config.get('analytics', {}).get('enabled', False)
    if not enabled:
        sys.stdout.write('Analytics is disabled\n')
        sys.exit(0)
    global CONFIG
    helper.update_config(
            config.get('connections', {}).get('mysql', {}),
            CONFIG['connections']['mysql']['scalr'])
    helper.update_config(
            config.get('analytics', {}).get('connections', {}).get('scalr', {}),
            CONFIG['connections']['mysql']['scalr'])
    helper.update_config(
            config.get('analytics', {}).get('connections', {}).get('analytics', {}),
            CONFIG['connections']['mysql']['analytics'])
    helper.update_config(
            config.get('analytics', {}).get('processing', {}),
            CONFIG)
    helper.update_config(config_to=CONFIG, args=args)
    CONFIG['pool_size'] = max(21, CONFIG['pool_size'])
    helper.validate_config(CONFIG)
    if CONFIG['recalculate'] and (not CONFIG['platform'] or not CONFIG['date_from']):
        sys.stderr.write("Error. You must specify 'platform' and 'date-from' for recalculating\n")
        sys.exit(1)
    helper.configure_log(
        log_level=CONFIG['verbosity'],
        log_file=CONFIG['log_file'],
        log_size=1024 * 10000
    )
    global POOL
    POOL = Pool()
    global SCALR_DB
    SCALR_DB = dbmanager.ScalrDB(CONFIG['connections']['mysql']['scalr'])
    global ANALYTICS_DB
    ANALYTICS_DB = dbmanager.DB(CONFIG['connections']['mysql']['analytics'])


def main():
    parser = argparse.ArgumentParser()

    group1 = parser.add_mutually_exclusive_group()
    group1.add_argument('--start', action='store_true', default=False,
            help='start program')
    group1.add_argument('--stop', action='store_true', default=False,
            help='stop program')
    parser.add_argument('--no-daemon', action='store_true', default=None,
            help="run in no daemon mode")
    parser.add_argument('-p', '--pid-file', default=None,
            help="pid file")
    parser.add_argument('-l', '--log-file', default=None,
            help="log file")
    parser.add_argument('-c', '--config-file', default='./config.yml',
            help='config file')
    parser.add_argument('-v', '--verbosity', action='count', default=None,
            help='increase output verbosity')
    parser.add_argument('--version', action='version', version='Version %s' % __version__)
    parser.add_argument('--recalculate', action='store_true', default=False,
            help="recalculate data")
    parser.add_argument('--platform', type=str, default=False,
            help=(
                "platform to recalculate, "
                "[cloudstack, ec2, ecs, eucalyptus, gce, idcf, openstack, "
                "rackspacenguk, rackspacengus]"))
    parser.add_argument('--date-from', type=str, default=False,
            help="from date, 'YYYY-MM-DD' UTC")
    parser.add_argument('--date-to', type=str, default=False,
            help="to date, 'YYYY-MM-DD' UTC")

    args = parser.parse_args()
    try:
        config = yaml.safe_load(open(args.config_file))['scalr']
        configure(config, args)
    except SystemExit:
        raise
    except:
        if args.verbosity > 3:
            raise
        else:
            sys.stderr.write('%s\n' % helper.exc_info(line_no=False))
        sys.exit(1)
    try:
        app = AnalyticsProcessing()
        if args.start:
            if helper.check_pid(CONFIG['pid_file']):
                msg = "Application with pid file '%s' already running. Exit" % CONFIG['pid_file']
                LOG.info(msg)
                sys.exit(0)
            if not args.no_daemon:
                helper.daemonize()
            app.start()
        elif args.stop:
            app.stop()
        else:
            print 'Usage %s -h' % sys.argv[0]
    except KeyboardInterrupt:
        LOG.critical('KeyboardInterrupt')
        return
    except SystemExit:
        pass
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
