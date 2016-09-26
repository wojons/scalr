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
from scalrpy.util import billing

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

import datetime
import multiprocessing
import mock
import shutil
import cherrypy
import threading
import json


class AnalyticsProcessingScript(lib.Script):

    app_cls = analytics_processing.AnalyticsProcessing
    name = 'analytics_processing'


class MockedDateTime(datetime.datetime):

    @classmethod
    def utcnow(cls):
        dt_format = "%Y-%m-%d %H:%M:%S"
        return datetime.datetime.strptime('2015-05-01 06:00:01', dt_format)


class MockedDate(datetime.date):

    @classmethod
    def today(cls):
        return datetime.date(2015, 5, 4)


class AzureMockServer(object):

    @cherrypy.expose
    @cherrypy.tools.json_out()
    def subscriptions_subscription_id_RateCard(self, *args, **kwds):
        rate_file = os.path.join(scalrpy_dir, 'tests/fixtures/rate_resp.txt')
        with open(rate_file, 'r') as f:
            data = json.loads(f.read())
            return data

    @cherrypy.expose
    @cherrypy.tools.json_out()
    def subscriptions_subscription_id_UsageAggregates(self, *args, **kwds):
        resolution = kwds['aggregationGranularity'].lower()
        reported_start_time = datetime.datetime.strptime(kwds['reportedStartTime'], '%Y-%m-%dT%H:%S:%M+00:00')
        reported_end_time = datetime.datetime.strptime(kwds['reportedEndTime'], '%Y-%m-%dT%H:%S:%M+00:00')

        usage_file = os.path.join(scalrpy_dir,
                                  'tests/fixtures/usage_resp_%s.txt' % resolution)
        with open(usage_file, 'r') as f:
            data = {'value': []}
            raw_data = json.loads(f.read())
            for row in raw_data['value']:
                start_time = datetime.datetime.strptime(
                    row['properties']['usageStartTime'].split('+')[0], '%Y-%m-%dT%H:%M:%S')
                if start_time < reported_start_time:
                    continue
                if start_time > reported_end_time:
                    continue
                data['value'].append(row)
            return data

    @cherrypy.expose
    @cherrypy.tools.json_out()
    def token_tenant_name(self, *args, **kwds):
        return {'access_token': 'access_token'}


def mock_download_aws_billing_file_ok():

    def get_contents_to_filename(csv_zip_file):
        src = os.path.join(scalrpy_dir, 'tests/fixtures', os.path.basename(csv_zip_file))
        dst = csv_zip_file
        shutil.copy(src, dst)

    bucket = mock.MagicMock(name='bucket')
    keys = []
    for name in [
            '123-aws-billing-detailed-line-items-with-resources-and-tags-2015-03.csv.zip',
            '123-aws-billing-detailed-line-items-with-resources-and-tags-2015-04.csv.zip',
            '123-aws-billing-detailed-line-items-with-resources-and-tags-2015-05.csv.zip',
            '333-aws-billing-detailed-line-items-with-resources-and-tags-2015-05.csv.zip']:
        key = mock.MagicMock()
        key.name = name
        key.last_modified = 'Fri, 01 May 2015 05:50:57 GMT'
        key.size = 1024
        key.get_contents_to_filename = get_contents_to_filename
        keys.append(key)
    bucket.list.return_value = keys
    bucket.get_key.return_value = keys[1]

    conn = mock.MagicMock(name='conn')
    conn.get_bucket.return_value = bucket

    billing.boto.s3.connect_to_region = mock.MagicMock(name='connect_to_region')
    billing.boto.s3.connect_to_region.return_value = conn


def mock_download_aws_billing_file_not_in_bucket():
    bucket = mock.MagicMock(name='key')
    bucket.list.return_value = []

    conn = mock.MagicMock(name='conn')
    conn.get_bucket.return_value = bucket

    billing.boto.s3.connect_to_region = mock.MagicMock(name='connect_to_region')
    billing.boto.s3.connect_to_region.return_value = conn


def mock_download_aws_billing_file_not_ok():

    def get_contents_to_filename(csv_zip_file):
        pass

    bucket = mock.MagicMock(name='bucket')
    keys = []
    for name in [
            '123-aws-billing-detailed-line-items-with-resources-and-tags-2015-04.csv.zip',
            '123-aws-billing-detailed-line-items-with-resources-and-tags-2015-05.csv.zip',
            '333-aws-billing-detailed-line-items-with-resources-and-tags-2015-05.csv.zip']:
        key = mock.MagicMock()
        key.name = name
        key.last_modified = 'Fri, 01 May 2015 05:50:57 GMT'
        key.size = 1024
        key.get_contents_to_filename = get_contents_to_filename
        keys.append(key)
    bucket.list.return_value = keys
    bucket.get_key.return_value = keys[1]

    conn = mock.MagicMock(name='conn')
    conn.get_bucket.return_value = bucket

    billing.boto.s3.connect_to_region = mock.MagicMock(name='connect_to_region')
    billing.boto.s3.connect_to_region.return_value = conn


@step(u"^Mock download AWS billing file not in bucket$")
def download_aws_billing_file_not_in_bucket(step):
    lib.world.mock_download_aws_billing_file = mock_download_aws_billing_file_not_in_bucket


@step(u"^Mock download AWS billing file not ok$")
def download_aws_billing_file_not_ok(step):
    lib.world.mock_download_aws_billing_file = mock_download_aws_billing_file_not_ok


def analytics_process():
    analytics_processing.datetime.datetime = MockedDateTime
    analytics_processing.datetime.date = MockedDate
    billing.AzureBilling.ratecard_url = 'http://127.0.0.1:8080/subscriptions_{subscription_id}_RateCard'
    billing.AzureBilling.usage_url = 'http://127.0.0.1:8080/subscriptions_{subscription_id}_UsageAggregates'
    billing.AzureBilling.token_url = 'http://127.0.0.1:8080/token_{tenant_id}'
    analytics_processing.LAUNCH_DELAY = 0

    lib.world.mock_download_aws_billing_file()

    analytics_processing.app.load_config()
    analytics_processing.app.configure()

    t = threading.Thread(target=cherrypy.quickstart, args=(AzureMockServer(),))
    t.start()
    time.sleep(2)

    analytics_processing.app.run()

    t.join()


@step(u"^White Rabbit starts Analytics Processing script with options '(.*)'$")
def start(step, opts):
    argv = opts.split() + ['start']
    analytics_processing.app = analytics_processing.AnalyticsProcessing(argv=argv)
    lib.world.app_process = multiprocessing.Process(target=analytics_process)
    lib.world.app_process.start()


@step(u"^White Rabbit stops Analytics Processing script$")
def stop(step):
    if lib.world.app_process.is_alive():
        lib.world.app_process.terminate()


lib.ScriptCls = AnalyticsProcessingScript


def before_scenario(scenario):
    lib.world.mock_download_aws_billing_file = mock_download_aws_billing_file_ok


def after_scenario(scenario):
    pass


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)
