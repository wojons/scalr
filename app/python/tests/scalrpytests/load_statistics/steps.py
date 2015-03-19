from gevent import monkey
monkey.patch_all(subprocess=True)

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.normpath(os.path.join(cwd, '../../..'))
sys.path.insert(0, scalrpy_dir)
scalrpytests_dir = os.path.join(cwd, '../..')
sys.path.insert(0, scalrpytests_dir)

import json
import time
import shutil
import psutil
import rrdtool
import requests

from gevent import pywsgi

from scalrpy.util import rpc
from scalrpy.util import helper
from scalrpy.util import cryptotool
from scalrpy.load_statistics import LoadStatistics

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

from lettuce import step, before, after


class LoadStatisticsScript(lib.Script):

    app_cls = LoadStatistics
    name = 'load_statistics'

    def stop(self):
        lib.Script.stop(self)

    def prepare(self):
        shutil.rmtree(self.app.config['rrd']['dir'], ignore_errors=True)

    def check_rrdcached(self):
        user = self.app.config['user'] or 'root'
        for proc in psutil.process_iter():
            info = proc.as_dict(attrs=['username', 'name'])
            if info['username'] == user and info['name'] == 'rrdcached':
                break
        else:
            assert False

    def check_rrd_files(self):
        if self.app.config['rrd']['rrdcached_sock_path']:
            rrdcached_sock_file = self.app.config['rrd']['rrdcached_sock_path']
        else:
            rrdcached_sock_file = self.app.config['rrd']['rrdcached_sock_path']
        metrics = self.app.config['metrics']
        metrics_map = {
            'cpu': 'CPUSNMP/db.rrd',
            'la': 'LASNMP/db.rrd',
            'mem': 'MEMSNMP/db.rrd',
            'net': 'NETSNMP/db.rrd',
            'io': 'IO/sda1.rrd'
        }
        out_map = {
            'cpu': '10 10 10 10',
            'la': '1.0 1.0 1.0',
            'mem': '1024.0 1024.0 1024.0 U 1024.0 1024.0 1024.0 1024.0',
            'net': '1024 1024',
            'io': '10 10 10 10',
        }
        for server_id, server in lib.world.servers.iteritems():
            x1x2 = helper.x1x2(server['farm_id'])
            path = os.path.join(self.app.config['rrd']['dir'], x1x2, str(server['farm_id']))
            farm_path = os.path.join(path, 'FARM')
            role_path = os.path.join(path, 'FR_%s' % server['farm_roleid'])
            server_path = os.path.join(path, 'INSTANCE_%s_%s' % (server['farm_roleid'], server['index']))
            if server['status'] != 'Running':
                assert not os.path.isdir(server_path)
                continue
            assert os.path.isdir(farm_path), farm_path
            assert os.path.isdir(os.path.join(farm_path, 'SERVERS'))
            assert os.path.isdir(role_path), role_path
            assert os.path.isdir(os.path.join(role_path, 'SERVERS'))
            assert os.path.isdir(server_path), server_path

            for metric in metrics:
                if metric == 'snum':
                    continue
                rrd_db_file = os.path.join(server_path, metrics_map[metric])
                rrdtool.flushcached('--daemon', 'unix:%s' % rrdcached_sock_file, rrd_db_file)
                stdout, stderr, return_code = helper.call('rrdtool lastupdate %s' % rrd_db_file)
                assert not return_code
                assert stdout.split('/n')[-1].split(':')[-1].strip() == out_map[metric]

    def check_plotter(self):
        pid_file = self.app.config['pid_file']
        ppid = int(open(pid_file).read().strip())

        user = self.app.config['user'] or 'root'
        for proc in psutil.process_iter():
            if proc.username() == user and proc.name() == 'plotter' and proc.ppid() == ppid:
                break
        else:
            assert False

        cnf = self.app.config['connections']['plotter']
        scheme = cnf['scheme']
        bind_host = cnf['bind_host']
        bind_port = cnf['bind_port'] or cnf['port']
        url = '{scheme}://{bind_host}:{port}/load_statistics'.format(
                scheme=scheme, bind_host=bind_host, port=bind_port)
        r = requests.get(url)

        assert r.status_code == 200
        assert r.text == '{"msg": "Bad request", "success": false}'

    def send_request(self, path):
        cnf = self.app.config['connections']['plotter']
        scheme = cnf['scheme']
        bind_host = cnf['bind_host']
        bind_port = cnf['bind_port'] or cnf['port']
        url = '{scheme}://{bind_host}:{port}/{path}'.format(
                scheme=scheme, bind_host=bind_host, port=bind_port, path=path)
        r = requests.get(url)
        assert r.status_code == 200
        assert r.text == '{"metric": {"snum": {"img": "http://localhost/graphics/1/FARM/snum_daily.png", "success": true}}, "success": true}', r.text


lib.ScriptCls = LoadStatisticsScript


def answer(environ, start_response):
    server_id = environ['HTTP_X_SERVER_ID']
    crypto_key = lib.world.server_properties[server_id]['scalarizr.key']
    security = rpc.Security(cryptotool.decrypt_key(crypto_key))
    data = json.loads(security.decrypt_data(environ['wsgi.input'].readline()))

    responses = {
        'cpu_stat': {
            'user': 10,
            'nice': 10,
            'system': 10,
            'idle': 10,
        },
        'load_average': [
            1,
            1,
            1,
        ],
        'mem_info': {
            'total_swap': 1024,
            'avail_swap': 1024,
            'total_real': 1024,
            'total_free': 1024,
            'shared': 1024,
            'buffer': 1024,
            'cached': 1024,
        },
        'net_stats': {
            'eth0': {
                'receive': {
                    'bytes': 1024,
                    'packets': 1024,
                    'errors': 0,
                },
                'transmit': {
                    'bytes': 1024,
                    'packets': 1024,
                    'errors': 0,
                },
            },
        },
        'disk_stats': {
            'sda1': {
                'read': {
                    'num': 10,
                    'sectors': 10,
                    'bytes': 10,
                },
                'write': {
                    'num': 10,
                    'sectors': 10,
                    'bytes': 10,
                },
            },
        },
    }

    result = {'result': responses[data['method']]}

    response = security.encrypt_data(json.dumps(result))

    start_response('201 OK', [('Content-Type', 'text/html')])
    yield response


@step(u"White Rabbit starts api server on port (\d+)")
def start_api_server(step, port):
    if not hasattr(lib.world, 'api_servers'):
        lib.world.api_servers = {}
    api_server = pywsgi.WSGIServer(('127.0.0.1', int(port)), answer)
    api_server.start()
    lib.world.api_servers[port] = api_server
    time.sleep(0)


@step(u"White Rabbit stops api server on port (\d+)")
def stop_api_server(step, port):
    lib.world.api_servers[port].stop()


@step(u"White Rabbit checks rrdcached")
def check_rrdcached(step):
    lib.world.script.check_rrdcached()


@step(u"White Rabbit checks rrd files")
def check_rrd_files(step):
    lib.world.script.check_rrd_files()


@step(u"White Rabbit checks plotter")
def check_plotter(step):
    lib.world.script.check_plotter()


@step(u"White Rabbit sends request to plotter '(.*)'")
def sends_request(step, path):
    lib.world.script.send_request(path)


@step(u"White Rabbit starts rrdcached service")
def start_rrdcached(step):
    lib.start_system_service('rrdcached')


@step(u"White Rabbit stops rrdcached service")
def stop_rrdcached(step):
    lib.stop_system_service('rrdcached')


def before_scenario(scenario):
    pass


def after_scenario(scenario):
    if hasattr(lib.world, 'api_servers'):
        for api_server in lib.world.api_servers.values():
            api_server.stop()
    if lib.world.script:
        lib.world.script.stop()


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)
