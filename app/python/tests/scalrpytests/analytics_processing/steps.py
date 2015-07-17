from gevent import monkey
monkey.patch_all(subprocess=True)

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.normpath(os.path.join(cwd, '../../..'))
sys.path.insert(0, scalrpy_dir)
scalrpytests_dir = os.path.join(cwd, '../..')
sys.path.insert(0, scalrpytests_dir)


from scalrpy import analytics_processing

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

import traceback
import datetime
import multiprocessing


class AnalyticsProcessingScript(lib.Script):

    app_cls = analytics_processing.AnalyticsProcessing
    name = 'analytics_processing'


class MockedDateTime(datetime.datetime):

    @classmethod
    def utcnow(cls):
        dt_format = "%Y-%m-%d %H:%M:%S"
        return datetime.datetime.strptime('2015-05-01 04:00:01', dt_format)


class MockedDate(datetime.date):

    @classmethod
    def today(cls):
        return datetime.date(2015, 5, 4)


@step(u"White Rabbit starts Analytics Processing script with options '(.*)'")
def start(step, opts):
    argv = opts.split() + ['start']
    analytics_processing.app = analytics_processing.AnalyticsProcessing(argv=argv)

    def process():
        try:
            analytics_processing.datetime.datetime = MockedDateTime
            analytics_processing.datetime.date = MockedDate
            analytics_processing.app.load_config()
            analytics_processing.app.configure()

            def download_aws_billing_file(cred, bucket_name, date=None):
                file_name = analytics_processing.get_aws_csv_file_name(1, date)
                billing_file = os.path.join(scalrpy_dir, 'tests/fixtures', file_name)
                return billing_file

            analytics_processing.app.download_aws_billing_file = download_aws_billing_file
            analytics_processing.app.run()
        except:
            traceback.print_exc()

    lib.world.app_process = multiprocessing.Process(target=process)
    lib.world.app_process.start()


@step(u"White Rabbit stops Analytics Processing script")
def stop(step):
    if lib.world.app_process.is_alive():
        lib.world.app_process.terminate()


lib.ScriptCls = AnalyticsProcessingScript
