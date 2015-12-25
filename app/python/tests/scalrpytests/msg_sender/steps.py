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
import random
import subprocess

from scalrpy.util import dbmanager
from scalrpy.msg_sender import MsgSender

import scalrpytests
from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *
from scalrpytests import configure_log

from lettuce import step, before, after


configure_log(os.path.join(os.path.dirname(__file__), 'test_msg_sender.log'))


class MsgSenderScript(lib.Script):

    app_cls = MsgSender
    name = 'msg_sender'


lib.ScriptCls = MsgSenderScript


@step(u"^White Rabbit starts wsgi server on port (\d+)$")
def start_wsgi_server(step, port):
    if not hasattr(lib.world, 'wsgi_servers'):
        lib.world.wsgi_servers = {}
    server = os.path.join(os.path.dirname(__file__), 'scalarizr.py')
    cmd = "/usr/bin/python {server} {port}".format(server=server, port=port)
    wsgi_server = subprocess.Popen(cmd.split(), stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    time.sleep(1)
    assert not wsgi_server.poll(), wsgi_server.stderr.read()
    lib.world.wsgi_servers[port] = wsgi_server


@step(u"^White Rabbit stops wsgi server on port (\d+)$")
def stop_wsgi_server(step, port):
    try:
        lib.world.wsgi_servers[port].kill()
    except KeyError:
        raise
    except:
        pass


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
        record['value'] = random.choice(["'8010'",
                                         "'8011'",
                                         "'8012'",
                                         "'8013'",
                                         "'8014'",
                                         "'8015'",
                                         "'8016'",
                                         "'8017'",
                                         "'8018'",
                                         "'8019'"])
    fill_server_properties(step)

    for record in step.hashes:
        # server_properties
        record['name'] = "'scalarizr.key'"
        record['value'] = "'{0}'".format(scalrpytests.scalarizr_key)
    fill_server_properties(step)


@step(u"^White Rabbit checks all messages were tried to send$")
def check_tried(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    query = "SELECT count(*) AS count FROM messages WHERE handle_attempts = 0"
    result = db.execute(query)[0]
    assert result['count'] == 0, result['count']


@step(u"^White Rabbit checks all messages has status (\d+)$")
def check_messages_status(step, status):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    query = "SELECT count(messageid) AS count FROM messages WHERE status != {0}".format(int(status))
    result = db.execute(query)[0]
    assert result['count'] == 0, result['count']


def before_scenario(scenario):
    pass


def after_scenario(scenario):
    if hasattr(lib.world, 'wsgi_servers'):
        for wsgi_server in lib.world.wsgi_servers.values():
            try:
                wsgi_server.kill()
            except:
                pass
    if hasattr(lib.world, 'script') and lib.world.script:
        lib.world.script.stop()


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)
