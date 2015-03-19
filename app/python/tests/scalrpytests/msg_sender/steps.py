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

from gevent import pywsgi

from scalrpy.util import cryptotool
from scalrpy.msg_sender import MsgSender

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

from lettuce import step, before, after


CRYPTO_KEY = '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg=='
CRYPTO_ALGO = dict(name="des_ede3_cbc", key_size=24, iv_size=8)


class MsgSenderScript(lib.Script):

    app_cls = MsgSender
    name = 'msg_sender'


lib.ScriptCls = MsgSenderScript


def answer(environ, start_response):
    server_id = environ['HTTP_X_SERVER_ID']
    data = environ['wsgi.input'].readline()
    crypto_key = lib.world.server_properties[server_id]['scalarizr.key']
    msg = cryptotool.decrypt_scalarizr(CRYPTO_ALGO, data, cryptotool.decrypt_key(crypto_key))
    if msg != 'Carrot':
        start_response('400 NOT OK', [('Content-Type', 'text/html')])
    else:
        time.sleep(0.4)
        start_response('201 OK', [('Content-Type', 'text/html')])
    yield '<b>Hello world!</b>\n'


@step(u"White Rabbit starts wsgi server on port (\d+)")
def start_wsgi_server(step, port):
    if not hasattr(lib.world, 'wsgi_servers'):
        lib.world.wsgi_servers = {}
    wsgi_server = pywsgi.WSGIServer(('127.0.0.1', int(port)), answer)
    wsgi_server.start()
    lib.world.wsgi_servers[port] = wsgi_server
    time.sleep(0)


@step(u"White Rabbit stops wsgi server on port (\d+)")
def stop_wsgi_server(step, port):
    lib.world.wsgi_servers[port].stop()


@step(u"^Database has (\d+) messages$")
def db_has_messages(step, count):
    records = []
    for i in range(int(count)):
        record = {}

        # messages
        record['messageid'] = "'%s'" % str(uuid.uuid4())
        record['status'] = 0
        record['handle_attempts'] = 0
        record['dtlasthandleattempt'] = "'%s'" % datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        record['message'] = "'Carrot'"
        record['server_id'] = "'%s'" % lib.generate_server_id()
        record['type'] = "'out'"
        record['message_version'] = 2
        record['message_name'] = "''"
        record['message_format'] = "'json'"
        record['event_id'] = "'%s'" % lib.generate_id()

        # servers
        record['farm_id'] = lib.generate_id()
        record['farm_roleid'] = lib.generate_id()
        record['client_id'] = lib.generate_id()
        record['env_id'] = lib.generate_id()
        record['role_id'] = lib.generate_id()
        record['platform'] = random.choice(["'ec2'", "'gce'", "'idcf'", "'openstack'"])
        record['status'] = "'running'"
        record['remote_ip'] = "'127.0.0.1'"
        record['local_ip'] = "'127.0.0.1'"
        record['index'] = 1
        record['os_type'] = "'linux'"

        records.append(record)

    step.hashes = records

    fill_messages(step)
    fill_servers(step)

    for record in step.hashes:
        # server_properties
        record['name'] = "'scalarizr.ctrl_port'"
        record['value'] = "'8013'"
    fill_server_properties(step)

    for record in step.hashes:
        # server_properties
        record['name'] = "'scalarizr.key'"
        record['value'] = "'8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg=='"
    fill_server_properties(step)



@step(u"^White Rabbit checks all messages has status (\d+)$")
def check_messages_status(step, status):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    query = "SELECT count(messageid) AS count FROM messages WHERE status != {0}".format(int(status))
    result = db.execute(query)[0]
    assert result['count'] == 0


def before_scenario(scenario):
    pass


def after_scenario(scenario):
    if hasattr(lib.world, 'wsgi_servers'):
        for wsgi_server in lib.world.wsgi_servers.values():
            wsgi_server.stop()
    if hasattr(lib.world, 'script') and lib.world.script:
        lib.world.script.stop()


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)
