from gevent import monkey
monkey.patch_all(subprocess=True)

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.normpath(os.path.join(cwd, '../../..'))
sys.path.insert(0, scalrpy_dir)
scalrpytests_dir = os.path.join(cwd, '../..')
sys.path.insert(0, scalrpytests_dir)


from scalrpy import analytics_poller

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

import mock
import calendar
import traceback
import datetime
import multiprocessing


class AnalyticsPollerScript(lib.Script):

    app_cls = analytics_poller.AnalyticsPoller
    name = 'analytics_poller'


class MockedDateTime(datetime.datetime):

    @classmethod
    def utcnow(cls):
        dt_format = "%Y-%m-%d %H:%M:%S"
        return datetime.datetime.strptime('2015-05-01 01:00:01', dt_format)


class MockedDate(datetime.date):

    @classmethod
    def today(cls):
        return datetime.date(2015, 5, 1)


analytics_poller.ec2 = mock.MagicMock(return_value=[
        {
            'region': 'us-east-1',
            'timestamp': calendar.timegm(MockedDateTime.utcnow().timetuple()),
            'nodes': [
                {'instance_id': 'i-00000', 'instance_type': 'm1.small'},
                {'instance_id': 'i-00001', 'instance_type': 'm1.small'},
                {'instance_id': 'i-00003', 'instance_type': 'm1.small'},
            ]
        },
        {
            'region': 'us-west-1',
            'timestamp': calendar.timegm(MockedDateTime.utcnow().timetuple()),
            'nodes': [
                {'instance_id': 'i-00002', 'instance_type': 'm1.medium'},
            ]
        },
])


@step(u"White Rabbit starts Analytics Poller script with options '(.*)'")
def start(step, opts):
    argv = opts.split() + ['start']
    analytics_poller.app = analytics_poller.AnalyticsPoller(argv=argv)

    def process():
        try:
            analytics_poller.analytics.datetime.datetime = MockedDateTime
            analytics_poller.analytics.datetime.date = MockedDate
            analytics_poller.analytics.platforms = ['ec2']
            analytics_poller.app.load_config()
            analytics_poller.app.configure()
            analytics_poller.app.run()
        except:
            traceback.print_exc()

    lib.world.app_process = multiprocessing.Process(target=process)
    lib.world.app_process.start()


@step(u"White Rabbit stops Analytics Poller script")
def stop(step):
    if lib.world.app_process.is_alive():
        lib.world.app_process.terminate()


lib.ScriptCls = AnalyticsPollerScript
