from gevent import monkey
monkey.patch_all(subprocess=True)

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.normpath(os.path.join(cwd, '../../..'))
sys.path.insert(0, scalrpy_dir)
scalrpytests_dir = os.path.join(cwd, '../..')
sys.path.insert(0, scalrpytests_dir)

import time
import json

from gevent import pywsgi

from scalrpy.util import rpc
from scalrpy.util import cryptotool
from scalrpy.szr_upd_service import SzrUpdService

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

from lettuce import step, before, after


class SzrUpdServiceScript(lib.Script):

    app_cls = SzrUpdService
    name = 'szr_upd_service'


lib.ScriptCls = SzrUpdServiceScript 


def answer(environ, start_response):
    try:
        server_id = environ['HTTP_X_SERVER_ID']
        crypto_key = lib.world.server_properties[server_id]['scalarizr.key']
        security = rpc.Security(cryptotool.decrypt_key(crypto_key))
        data = json.loads(security.decrypt_data(environ['wsgi.input'].readline()))

        server = lib.world.servers[server_id]

        assert 'params' in data
        assert 'method' in data
        assert 'id' in data, 'id'

        if data['method'] == 'status':
            assert 'cached' in data['params'], 'cached'

            repos = {
                'latest': {
                    'linux': 'http://apt.scalr.net/debian scalr/',
                    'windows': 'http://win.scalr.net',
                },
                'stable': {
                    'linux': 'http://apt-delayed.scalr.net/debian scalr/',
                    'windows': 'http://win-delayed.scalr.net',
                }
            }

            status = {
                'server_id': server_id,
                'repository': 'stable',
                'repo_url': repos['stable'][server['os_type']],
                'executed_at': 'Mon 22 Sep 2014 12:00:00 UTC',
                'state': '',
                'error': '',
            }
            answer = {'result': status}
        elif data['method'] == 'update':
            answer = {'result': 777}
            lib.update_ok[server_id] = True
        else:
            assert False, 'method'

        response = security.encrypt_data(json.dumps(answer))
        start_response('201 OK', [('Content-Type', 'text/html')])
        yield response
    except:
        yield str(sys.exc_info())


def answer_branch(environ, start_response):
    try:
        server_id = environ['HTTP_X_SERVER_ID']
        crypto_key = lib.world.server_properties[server_id]['scalarizr.key']
        security = rpc.Security(cryptotool.decrypt_key(crypto_key))
        data = json.loads(security.decrypt_data(environ['wsgi.input'].readline()))

        server = lib.world.servers[server_id]

        assert 'params' in data
        assert 'method' in data
        assert 'id' in data, 'id'

        if data['method'] == 'status':
            assert 'cached' in data['params'], 'cached'

            repos = {
                'feature/omnibus-integration': {
                    'linux': 'http://apt.scalr.net/debian scalr/',
                    'windows': 'http://win.scalr.net',
                },
            }

            status = {
                'server_id': server_id,
                'repository': 'stable',
                'repo_url': repos['stable'][server['os_type']],
                'executed_at': 'Mon 22 Sep 2014 12:00:00 UTC',
                'state': '',
                'error': '',
            }
            answer = {'result': status}
        elif data['method'] == 'update':
            answer = {'result': 777}
            lib.update_ok[server_id] = True
        else:
            assert False, 'method'

        response = security.encrypt_data(json.dumps(answer))
        start_response('201 OK', [('Content-Type', 'text/html')])
        yield response
    except:
        yield str(sys.exc_info())

@step(u"White Rabbit starts scalarizr update client on port (\d+)$")
def start_szr_upd_client(step, port):
    if not hasattr(lib.world, 'szr_upd_client'):
        lib.world.szr_upd_client = {}
    szr_upd_client = pywsgi.WSGIServer(('127.0.0.1', int(port)), answer)
    szr_upd_client.start()
    lib.world.szr_upd_client[port] = szr_upd_client
    time.sleep(0)


@step(u"White Rabbit starts vpc router$")
def start_vpc_router(step):
    if not hasattr(lib.world, 'vpc_router'):
        vpc_router = pywsgi.WSGIServer(('127.0.0.1', 80), answer)
        vpc_router.start()
        lib.world.vpc_router = vpc_router
        time.sleep(0)


@step(u"White Rabbit stops scalarizr update client on port (\d+)$")
def stop_szr_upd_client(step, port):
    lib.world.szr_upd_client[port].stop()


@step(u"White Rabbit stops vpc router")
def stop_vpc_router(step):
    lib.world.vpc_router.stop()


@step(u"White Rabbit checks server with server_id '(.*)' was updated$")
def check_update(step, server_id):
    assert server_id in lib.update_ok
    assert lib.update_ok[server_id]



def before_scenario(scenario):
    lib.update_ok = {}


def after_scenario(scenario):
    if hasattr(lib.world, 'szr_upd_client'):
        for szr_upd_client in lib.world.szr_upd_client.values():
            szr_upd_client.stop()


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)
