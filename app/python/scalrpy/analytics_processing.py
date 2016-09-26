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

import socket
import datetime
import time
import gevent

from scalrpy.util import helper
from scalrpy.util import analytics
from scalrpy.util import dbmanager
from scalrpy.util import application
from scalrpy.util import cryptotool
from scalrpy.util import billing
from scalrpy.util.analytics import PLATFORMS

from scalrpy import LOG
from scalrpy import exceptions


helper.patch_gevent()

LAUNCH_DELAY = 60

BILLING_TYPES = ('poller', 'aws-detailed-billing', 'azure')


class AnalyticsProcessing(application.ScalrApplication):

    def __init__(self, argv=None):
        self.description = "Scalr Cost Analytics application"

        options = (
            """  --billing <billing>               billing method:\n"""
            """\t\t\t\t\t[poller, aws-detailed-billing, azure]\n"""
        )
        self.add_options(options)

        options = "  --recalculate                     recalculate data "
        self.add_options(options)

        options = (
            """  --platform <platform>             platform to recalculate for 'poller' billing\n"""
            """\t\t\t\t\t[cloudstack, ec2, gce, idcf, openstack,\n"""
            """\t\t\t\t\trackspacenguk, rackspacengus, ocs, nebula,\n"""
            """\t\t\t\t\tmirantis, vio, verizon, cisco]"""
        )
        self.add_options(options)

        options = "  --date-from <date>                start date to recalculate, 'YYYY-MM-DD' UTC"
        self.add_options(options)

        options = "  --date-to <date>                  end date to recalculate, 'YYYY-MM-DD' UTC"
        self.add_options(options)

        options = "  --year <year>                     year to recalculate data "
        self.add_options(options)
        options = "  --quarter <quarter>               quarter to recalculate data "
        self.add_options(options)

        super(AnalyticsProcessing, self).__init__(argv=argv)

        self.config['connections'].update({
            'analytics': {
                'user': None,
                'pass': None,
                'host': None,
                'port': 3306,
                'name': None,
                'pool_size': 100,
                'timeout': 15,
            },
        })
        self.config.update({
            'pool_size': 150,
            'dtime_from': False,
            'dtime_to': False,
            'billing': BILLING_TYPES,
            'platform': PLATFORMS,
        })
        self.config['proxy'] = {}
        self.config['crypto_key'] = ''

        self.scalr_db = None
        self.analytics_db = None
        self.analytics = None

    def validate_args(self):
        super(AnalyticsProcessing, self).validate_args()
        utcnow = datetime.datetime.utcnow().replace(microsecond=0)
        if self.args['--billing']:
            assert_msg = "Billing type '%s' is not supported" % self.args['--billing']
            assert self.args['--billing'] in BILLING_TYPES, assert_msg
        if self.args['--platform']:
            assert_msg = "Platform '%s' is not supported" % self.args['--platform']
            assert self.args['--platform'] in PLATFORMS, assert_msg
        if self.args['--recalculate']:
            assert_msg = "'--daemon' option is not supported in recalculating mode"
            assert self.args['--daemon'] is False, assert_msg
            assert_msg = "To recalculate data you should specify '--year' or '--date-from' option"
            assert self.args['--year'] or self.args['--date-from'], assert_msg
            assert_msg = "Recalculating is supported only for 'aws-detailed-billing and 'poller' billing types"
            assert self.args['--billing'] in ('aws-detailed-billing', 'poller'), assert_msg
        if self.args['--date-from']:
            assert_msg = "Wrong date format for '--date-from' option, use Y-m-d"
            try:
                dtime_from = datetime.datetime.strptime(self.args['--date-from'], '%Y-%m-%d')
            except:
                raise AssertionError(assert_msg)
            assert_msg = 'Processing is not supported for future'
            assert dtime_from <= utcnow, assert_msg
        if self.args['--date-to']:
            assert_msg = "Wrong date format for '--date-to' option, use Y-m-d"
            try:
                dtime_to = datetime.datetime.strptime(self.args['--date-to'], '%Y-%m-%d')
            except:
                raise AssertionError(assert_msg)
            assert_msg = 'Processing is not supported for future'
            assert dtime_to <= utcnow, assert_msg
        if self.args['--year']:
            assert_msg = "'--year' option is used in recalculate mode"
            assert self.args['--recalculate'], assert_msg
            assert_msg = "'--date-from' option conflicts with '--year' option"
            assert not self.args['--date-from'], assert_msg
            assert_msg = "'--date-to' option conflicts with '--year' option"
            assert not self.args['--date-to'], assert_msg
        if self.args['--quarter']:
            assert int(self.args['--quarter']) in (1, 2, 3, 4)
            assert_msg = "'--quarter' option is used in recalculate mode"
            assert self.args['--recalculate'], assert_msg
            assert_msg = "'--date-from' option conflicts with '--quarter' option"
            assert not self.args['--date-from'], assert_msg
            assert_msg = "'--date-to' option conflicts with '--quarter' option"
            assert not self.args['--date-to'], assert_msg

    def configure(self):
        enabled = self.scalr_config.get('analytics', {}).get('enabled', False)
        if not enabled:
            sys.stdout.write('Analytics is disabled. Exit\n')
            sys.exit(0)

        utcnow = datetime.datetime.utcnow().replace(microsecond=0)
        if self.args['--date-from']:
            dtime_from = datetime.datetime.strptime(self.args['--date-from'], '%Y-%m-%d')
            if not self.args['--recalculate']:
                if self.args['--billing'] in ('aws-detailed-billing', 'azure'):
                    three_months_ago = utcnow + datetime.timedelta(days=-119)
                    assert_msg = 'Processing is not supported for dtime-from more than four months ago'
                    assert dtime_from > three_months_ago, assert_msg
                else:
                    two_weeks_ago = utcnow + datetime.timedelta(days=-14)
                    assert_msg = 'Processing is not supported for dtime-from more than two weeks ago'
                    assert dtime_from > two_weeks_ago, assert_msg
            self.config['dtime_from'] = dtime_from
        if self.args['--date-to']:
            dtime_to = datetime.datetime.strptime(self.args['--date-to'], '%Y-%m-%d')
            self.config['dtime_to'] = dtime_to

        helper.update_config(
            self.scalr_config.get('analytics', {}).get('connections', {}).get('scalr', {}),
            self.config['connections']['mysql'])
        helper.update_config(
            self.scalr_config.get('analytics', {}).get('connections', {}).get('analytics', {}),
            self.config['connections']['analytics'])
        helper.update_config(
            self.scalr_config.get('analytics', {}).get('processing', {}),
            self.config)

        if self.args['--billing']:
            self.config['billing'] = (self.args['--billing'],)
        if self.args['--platform']:
            assert_msg = "--platform option supported only for 'poller' billing type"
            assert 'poller' in self.config['billing'], assert_msg
            self.config['platform'] = (self.args['--platform'],)

        helper.validate_config(self.config)

        crypto_key_path = os.path.join(os.path.dirname(self.args['--config']), '.cryptokey')
        self.config['crypto_key'] = cryptotool.read_key(crypto_key_path)
        self.config['azure_app_client_id'] = self.scalr_config.get('azure', {}).get('app_client_id')
        self.config['azure_app_secret_key'] = self.scalr_config.get('azure', {}).get('app_secret_key')

        self.scalr_db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self.analytics_db = dbmanager.ScalrDB(self.config['connections']['analytics'])
        self.analytics = analytics.Analytics(self.scalr_db, self.analytics_db)

        if self.args['--year']:
            # XXX pymysql.connect issue
            time.sleep(0)
            quarters_calendar = self.analytics.get_quarters_calendar()
            year = int(self.args['--year'])
            quarter = int(self.args['--quarter'] or 1)
            dtime_from, dtime_to = quarters_calendar.dtime_for_quarter(quarter, year=year)
            self.config['dtime_from'] = dtime_from
            self.config['dtime_to'] = min(utcnow, dtime_to)

        self.config['proxy']['aws'] = helper.get_proxy_settings(self.scalr_config, 'aws')
        self.config['proxy']['azure'] = helper.get_proxy_settings(self.scalr_config, 'azure')

        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

    def __call__(self):
        self.change_permissions()

        tasks = []

        if self.args['--recalculate']:

            def after_task(task):
                task.task.fill_farm_usage_d(force=True)
                task.stop()

            task_cls = {
                'poller': billing.RecalculatePollerBilling,
                'aws-detailed-billing': billing.RecalculateAWSBilling,
            }
            for billing_type in self.config['billing']:
                task = task_cls[billing_type](self.analytics, self.config)
                tasks.append(helper.PeriodicalTask(task, timeout=task.timeout, after=after_task))
        else:

            def after_task(task):
                task.task.fill_farm_usage_d()
                if self.config['dtime_to']:
                    task.stop()
                else:
                    task.task.config['dtime_from'] = False

            task_cls = {
                'poller': billing.PollerBilling,
                'aws-detailed-billing': billing.AWSBilling,
                'azure': billing.AzureBilling,
            }
            for billing_type in self.config['billing']:
                task = task_cls[billing_type](self.analytics, self.config)
                tasks.append(helper.PeriodicalTask(task,
                                                   period=task.period,
                                                   timeout=task.timeout,
                                                   after=after_task))

        results = []
        for task in tasks:
            results.append(task())
            if len(tasks) > 1:
                time.sleep(LAUNCH_DELAY)

        gevent.wait(results)


def main():
    app = AnalyticsProcessing()
    try:
        app.load_config()
        app.configure()
        app.run()
    except exceptions.AlreadyRunningError:
        LOG.info(helper.exc_info())
    except (SystemExit, KeyboardInterrupt):
        pass
    except:
        LOG.exception('Oops')


if __name__ == '__main__':
    main()
