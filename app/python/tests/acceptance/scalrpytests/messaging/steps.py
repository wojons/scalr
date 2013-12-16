from lettuce import *

import os
import yaml
import string
import random
import gevent
import multiprocessing as mp

from gevent import pywsgi

from sqlalchemy import and_
from sqlalchemy import desc
from sqlalchemy import func
from scalrpy.util import dbmanager

from scalrpytests.steplib import lib

from lettuce import world, step, before, after


BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ETC_DIR = os.path.abspath(BASE_DIR + '/../../../etc')


@step(u"I have '(.*)' test config")
def test_config(step, name):
    world.config = yaml.safe_load(open(ETC_DIR + '/config.yml'))['scalr'][name]
    assert True


@step(u'I wait (\d+) seconds')
def wait_sec(step, sec):
    lib.wait_sec(int(sec))
    assert True


@step(u"I drop test database")
def drop_db(step):
    assert lib.drop_db(world.config['connections']['mysql'])


@step(u"I create test database")
def create_db(step):
    assert lib.create_db(world.config['connections']['mysql'])


@step(u"I create table '(.*)'")
def create_table(step, table):
    assert lib.create_table(world.config['connections']['mysql'], str(table))


@step(u"I have (\d+) messages with status (\d+) and type '(.*)'")
def fill_tables(step, count, st, tp):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        for i in range(int(count)):

            while True:
                msg_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(75))
                if db.messages.filter(db.messages.messageid == msg_id).first() is None:
                    break
                continue

            while True:
                farm_id = random.randint(1, 9999)
                if db.farm_settings.filter(db.farm_settings.farmid == farm_id).first() is None:
                    break
                continue

            while True:
                srv_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(36))
                if db.servers.filter(db.servers.server_id == srv_id).first() is None:
                    break
                continue

            while True:
                event_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(36))
                if db.events.filter(db.events.event_id == event_id).first() is None:
                    break
                continue

            db.messages.insert(
                    messageid=msg_id,
                    status=int(st),
                    handle_attempts=0,
                    dtlasthandleattempt=func.now(),
                    message='some text here',
                    server_id=srv_id,
                    type='%s' % tp,
                    message_version=2,
                    message_name=random.choice(['ExecScript', '']),
                    message_format=random.choice(['xml', 'json']),
                    event_id=event_id)
            world.msgs_id.setdefault(msg_id, {}).setdefault('status', st)

            db.servers.insert(
                    farm_id=farm_id,
                    server_id=srv_id,
                    env_id=1,
                    status='Running',
                    remote_ip='127.0.0.1')

            db.events.insert(event_id=event_id, msg_sent=0)

            world.srvs_id.append(srv_id)

            db.server_properties.insert(
                    server_id=srv_id, name='scalarizr.key', value='hoho')
            db.server_properties.insert(
                    server_id=srv_id, name='scalarizr.ctrl_port', value=8013)

        db.commit()

    finally:
        db.session.remove()

    lib.wait_sec(1)
    assert True


@step(u"I have (\d+) wrong messages with status (\d+) and type '(.*)'")
def fill_tables_wrong(step, count, st, tp):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        for i in range(int(count)):

            while True:
                msg_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(75))
                if db.messages.filter(db.messages.messageid == msg_id).first() is None:
                    break
                continue

            while True:
                farm_id = random.randint(1, 9999)
                if db.farm_settings.filter(db.farm_settings.farmid == farm_id).first() is None:
                    break
                continue

            while True:
                srv_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(36))
                if db.servers.filter(db.servers.server_id == srv_id).first() is None:
                    break
                continue

            while True:
                event_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(36))
                if db.events.filter(db.events.event_id == event_id).first() is None:
                    break
                continue

            db.messages.insert(
                    messageid=random.choice([msg_id, '', None]),
                    status=int(st),
                    handle_attempts=0,
                    dtlasthandleattempt=func.now(),
                    message=random.choice(['some text here', 'another some text', '', None]),
                    server_id=random.choice([srv_id, None]),
                    type='%s' % tp,
                    message_version=2,
                    message_format=random.choice(['xml', 'json']),
                    event_id=event_id)
            world.msgs_id.setdefault(msg_id, {}).setdefault('status', st)

            db.servers.insert(
                    farm_id=random.choice([farm_id, '', None]),
                    server_id=random.choice([srv_id, '', None]),
                    env_id=1,
                    status='Running',
                    remote_ip=random.choice(['127.0.0.1', '333.777.444.333', '', None]))

            db.events.insert(event_id=event_id, msg_sent=0)

            world.srvs_id.append(srv_id)

            db.server_properties.insert(
                    server_id=srv_id,
                    name='scalarizr.key',
                    value=random.choice(['hoho', 'haha', '', None]))
            db.server_properties.insert(
                    server_id=srv_id,
                    name='scalarizr.ctrl_port',
                    value=random.choice([8013, '', None]))

        db.commit()

    finally:
        db.session.remove()

    lib.wait_sec(1)
    assert True


@step(u"I have (\d+) vpc messages with status (\d+) and type '(.*)'")
def fill_tables_vpc(step, count, st, tp):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        for i in range(int(count)):

            while True:
                msg_id = ''.join(random.choice(string.ascii_uppercase + 
                        string.digits) for x in range(75))
                if db.messages.filter(db.messages.messageid == msg_id).first() is None:
                    break
                continue
            while True:
                msg_id_router = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(75))
                if db.messages.filter(db.messages.messageid == msg_id_router).first() is None:
                    break
                continue

            while True:
                farm_id = random.randint(1, 20000)
                if db.farm_settings.filter(db.farm_settings.farmid == farm_id).first() is None:
                    break
                continue

            while True:
                farm_role_id = random.randint(1, 20000)
                if db.role_behaviors.filter(
                            db.role_behaviors.role_id == farm_role_id).first() is None:
                    break
                continue
            while True:
                farm_role_id_router = random.randint(1, 20000)
                if db.role_behaviors.filter(
                            db.role_behaviors.role_id == farm_role_id_router).first() is None:
                    break
                continue

            while True:
                srv_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(36))
                if db.servers.filter(db.servers.server_id == srv_id).first() is None:
                    break
                continue
            while True:
                srv_id_router = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(36))
                if db.servers.filter(db.servers.server_id == srv_id_router).first() is None:
                    break
                continue

            while True:
                event_id = ''.join(random.choice(string.ascii_uppercase +
                        string.digits) for x in range(36))
                if db.events.filter(db.events.event_id == event_id).first() is None:
                    break
                continue

            db.messages.insert(
                    messageid=msg_id,
                    status=int(st),
                    handle_attempts=0,
                    dtlasthandleattempt=func.now(),
                    message='some text here',
                    server_id=srv_id,
                    type='%s' % tp,
                    message_version=2,
                    message_format=random.choice(['xml', 'json']),
                    event_id=event_id)
            db.messages.insert(
                    messageid=msg_id_router,
                    status=int(st),
                    handle_attempts=0,
                    dtlasthandleattempt=func.now(),
                    message='some text here',
                    server_id=srv_id_router,
                    type='%s' % tp,
                    message_version=2,
                    message_format=random.choice(['xml', 'json']))

            db.farms.insert(farm_id=farm_id, env_id=1)

            db.events.insert(event_id=event_id, msg_sent=0)

            db.farm_roles.insert(farmid=farm_id, role_id=farm_role_id)
            db.farm_roles.insert(farmid=farm_id, role_id=farm_role_id_router)

            db.servers.insert(
                    farm_id=farm_id,
                    farm_roleid=farm_role_id,
                    server_id=srv_id,
                    env_id=1,
                    status='Running',
                    local_ip='244.244.244.244')
            db.servers.insert(
                    farm_id=farm_id,
                    farm_roleid=farm_role_id_router,
                    server_id=srv_id_router,
                    env_id=1,
                    status='Running',
                    local_ip='254.254.254.254')

            db.role_behaviors.insert(role_id=farm_role_id, behavior='not router')
            db.role_behaviors.insert(role_id=farm_role_id_router, behavior='router')

            db.farm_settings.insert(farmid=farm_id, name='ec2.vpc.id', value='1')

            id_ = db.farm_roles.filter(
                    db.farm_roles.role_id == farm_role_id_router,
                    db.farm_roles.farmid == farm_id).first().id
            db.farm_role_settings.insert(
                    farm_roleid=id_, name='router.vpc.ip', value='127.0.0.1')

            world.srvs_id.append(srv_id)
            world.srvs_id.append(srv_id_router)

            db.server_properties.insert(
                    server_id=srv_id, name='scalarizr.key', value='hoho')
            db.server_properties.insert(
                    server_id=srv_id, name='scalarizr.ctrl_port', value='8013')

            db.server_properties.insert(
                    server_id=srv_id_router, name='scalarizr.key', value='hoho')
            db.server_properties.insert(
                    server_id=srv_id_router, name='scalarizr.ctrl_port', value='8013')

        db.commit()

    finally:
        db.session.remove()

    lib.wait_sec(1)
    assert True


def answer(environ, start_response):
    gevent.sleep(1)
    start_response('201 OK', [('Content-Type', 'text/html')])
    yield '<b>Hello world!</b>\n'


@step(u"I start wsgi server")
def start_wsgi_server(step):
    world.server_proc = mp.Process(
            target=pywsgi.WSGIServer(('127.0.0.1', 8013), answer).serve_forever)
    world.vpc_proc = mp.Process(
            target=pywsgi.WSGIServer(('127.0.0.1', 80), answer).serve_forever)
    world.server_proc.start()

    assert lib.stop_service('apache2')

    world.vpc_proc.start()


@step(u"I stop wsgi server")
def stop_wsgi_server(step):
    world.server_proc.terminate()
    world.vpc_proc.terminate()
    lib.start_service('apache2')
    assert True


@step(u"I make prepare")
def prepare(step):
    step.given("I have 'msg_sender' test config")
    step.given("I stop 'mysql' service")
    step.given("I start 'mysql' service")
    step.given("I drop test database")
    step.given("I create test database")
    step.given("I create table 'messages'")
    step.given("I create table 'farms'")
    step.given("I create table 'farm_role_settings'")
    step.given("I create table 'servers'")
    step.given("I create table 'farm_roles'")
    step.given("I create table 'server_properties'")
    step.given("I create table 'farm_settings'")
    step.given("I create table 'role_behaviors'")
    step.given("I create table 'events'")


@step(u"I stop '(.*)' service")
def stop_service(step, name):
    assert lib.stop_service(name)


@step(u"I start '(.*)' service")
def start_service(step, name):
    assert lib.start_service(name)


@step(u"I start messaging daemon")
def start_daemon(step):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'], autoflush=False)
    db = db_manager.get_db()
    try:
        CRATIO = 120
        where = and_(db.messages.status == 0, db.messages.type == 'out',
                db.messages.message_version == 2,
                db.messages.message != '',
                db.messages.message != None,
                func.unix_timestamp(db.messages.dtlasthandleattempt) +
                db.messages.handle_attempts * CRATIO < func.unix_timestamp(func.now()))
        msgs = db.messages.filter(where).order_by(desc(db.messages.id)).all()

        world.right_msgs = [
                msg.messageid for msg in msgs if msg.message_name != 'ExecScript']

        assert len(world.right_msgs) != 0

        config = ETC_DIR + '/config.yml'
        assert lib.start_daemon('msg_sender', config)
    finally:
        db.session.remove()


@step(u"I stop messaging daemon")
def stop_daemon(step):
    config = ETC_DIR + '/config.yml'
    assert lib.stop_daemon('msg_sender', config)


@step(u"I see right messages were delivered")
def right_messages_were_delivered(step):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'], autoflush=False)
    db = db_manager.get_db()
    try:
        msgs = db.messages.filter(db.messages.messageid.in_(world.right_msgs)).all()

        assert len(msgs) != 0

        for msg in msgs:
            assert msg.status == 1
    finally:
        db.session.remove()


@step(u"I see right messages have (\d+) handle_attempts")
def right_messages_have_right_handle_attemps(step, val):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'], autoflush=False)
    db = db_manager.get_db()
    try:
        msgs = db.messages.filter(db.messages.messageid.in_(world.right_msgs)).all()
        for msg in msgs:
            assert msg.handle_attempts == int(val)
    finally:
        db.session.remove()


def before_scenario(scenario):
    world.right_msgs = []
    world.msgs_id = {}
    world.srvs_id = []


def after_scenario(scenario):
    pass


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)
