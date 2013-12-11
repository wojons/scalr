
import os
import yaml
import string
import random
import gevent
import mailbox

import multiprocessing as mp

from gevent import pywsgi

from scalrpy.util import dbmanager

from scalrpytests.steplib import lib

from lettuce import world, step, after, before


BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ETC_DIR = os.path.abspath(BASE_DIR + '/../../../etc')


@step(u"Alice has '(.*)' test config")
def test_config(step, name):
    world.config = yaml.safe_load(
            open(ETC_DIR + '/config.yml'))['scalr'][name]
    assert True


@step(u'Alice waits (\d+) seconds')
def wait_sec(step, sec):
    lib.wait_sec(int(sec))
    assert True


@step(u"Alice drops test database")
def drop_db(step):
    assert lib.drop_db(world.config['connections']['mysql'])


@step(u"Alice creates test database")
def create_db(step):
    assert lib.create_db(world.config['connections']['mysql'])


@step(u"Alice creates table '(.*)'")
def create_table(step, table):
    assert lib.create_table(world.config['connections']['mysql'], str(table))


@step(u"Alice stops '(.*)' service")
def stop_service(step, name):
    assert lib.stop_service(name)


@step(u"Alice starts '(.*)' service")
def start_service(step, name):
    assert lib.start_service(name)


@step(u"Alice prepares homework")
def prepare(step):
    step.given("Alice has 'dbqueue_event' test config")
    step.given("Alice stops 'mysql' service")
    step.given("Alice starts 'mysql' service")
    step.given("Alice stops 'sendmail' service")
    step.given("Alice starts 'sendmail' service")
    step.given("Alice drops test database")
    step.given("Alice creates test database")
    step.given("Alice creates table 'farms'")
    step.given("Alice creates table 'events'")
    step.given("Alice creates table 'farm_event_observers'")
    step.given("Alice creates table 'farm_event_observers_config'")


@step(u"Alice starts '(.*)' daemon")
def start_daemon(step, name):
    config = ETC_DIR + '/config.yml'
    assert lib.start_daemon(name, config)


@step(u"Alice stops '(.*)' daemon")
def stop_daemon(step, name):
    config = ETC_DIR + '/config.yml'
    assert lib.stop_daemon(name, config)


REST = mp.Value('i', 0)
def answer(environ, start_response):
    gevent.sleep(0.5)
    global REST
    REST.value += 1
    start_response('200 OK', [('Content-Type', 'text/html')])
    yield '<b>Hello world!</b>\n'


@step(u"Alice starts wsgi server")
def start_wsgi_server(step):
    assert lib.stop_service('apache2')
    world.server_proc = mp.Process(
            target=pywsgi.WSGIServer(('127.0.0.1', 8081), answer).serve_forever)
    world.server_proc.start()


@step(u"Alice stops wsgi server")
def stop_wsgi_server(step):
    world.server_proc.terminate()
    assert lib.start_service('apache2')


@step(u"White Rabbit creates (\d+) events with Mail observer")
def fill_tables1(step, count):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        for _ in range(int(count)):

            while True:
                farm_id = random.randint(1, 9999)
                if db.events.filter(
                        db.events.farmid==farm_id).first() is None:
                    break
                continue

            while True:
                event_id = ''.join(random.choice(string.ascii_uppercase +\
                        string.digits) for x in range(36))
                if db.events.filter(
                        db.events.event_id==event_id).first() is None:
                    break
                continue

            while True:
                server_id = ''.join(random.choice(string.ascii_uppercase +\
                        string.digits) for x in range(36))
                if db.events.filter(
                        db.events.event_server_id==server_id).first() is None:
                    break
                continue

            db.farms.insert(id=farm_id, env_id=1, name='Test')

            db.events.insert(
                    farmid=farm_id, ishandled=0, event_id=event_id, server_id=server_id,
                    type='HostDown', message='HostDown')

            while True:
                i = random.randint(1, 10000)
                if not db.farm_event_observers.filter(db.farm_event_observers.id==i).all():
                    break

            db.farm_event_observers.insert(id=i,
                    farmid=farm_id, event_observer_name='MailEventObserver')
            
            db.farm_event_observers_config.insert(observerid=i,
                    key='IsEnabled', value='1')
            db.farm_event_observers_config.insert(observerid=i,
                    key='EventMailTo', value='root')
            db.farm_event_observers_config.insert(observerid=i,
                    key='OnHostDownNotify', value='1')

            world.mail_events.append(event_id)
            world.sent_emails.append(
                    {'subj1':'HostDown event notification (FarmID: %s FarmName: Test)' % farm_id,
                    'subj2':'HostDown event notification (FarmID: %s)' % farm_id,
                    'payload':'HostDown'})

        db.commit()
    finally:
        db.session.remove()

    assert True


@step(u"White Rabbit creates (\d+) events with REST observer")
def fill_tables2(step, count):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        for _ in range(int(count)):

            while True:
                farm_id = random.randint(1, 9999)
                if db.events.filter(
                        db.events.farmid==farm_id).first() is None:
                    break
                continue

            while True:
                event_id = ''.join(random.choice(string.ascii_uppercase +\
                        string.digits) for x in range(36))
                if db.events.filter(
                        db.events.event_id==event_id).first() is None:
                    break
                continue

            while True:
                server_id = ''.join(random.choice(string.ascii_uppercase +\
                        string.digits) for x in range(36))
                if db.events.filter(
                        db.events.event_server_id==server_id).first() is None:
                    break
                continue

            db.farms.insert(id=farm_id, env_id=1, name='Test')

            db.events.insert(
                    farmid=farm_id, ishandled=0, event_id=event_id, server_id=server_id,
                    type='HostDown', message='HostDown')

            while True:
                i = random.randint(1, 10000)
                if not db.farm_event_observers.filter(db.farm_event_observers.id==i).all():
                    break

            db.farm_event_observers.insert(id=i,
                    farmid=farm_id, event_observer_name='RESTEventObserver')
            
            db.farm_event_observers_config.insert(observerid=i,
                    key='IsEnabled', value='1')
            db.farm_event_observers_config.insert(observerid=i,
                    key='OnHostDownNotifyURL', value='http://localhost:8081/test')

            world.mail_events.append(event_id)

        db.commit()
    finally:
        db.session.remove()

    assert True


@step(u"White Rabbit gets right emails")
def check_mailbox(step):
    mbox = mailbox.mbox('/var/mail/root')

    rem_key = []
    for sent_email in world.sent_emails:
        for key, received_email in mbox.iteritems():
            if sent_email['subj1'].strip() == received_email['Subject'].strip() or \
                    sent_email['subj2'].strip() == received_email['Subject'].strip() and \
                    sent_email['payload'].strip() == received_email.get_payload().strip():
                rem_key.append(key)
                break
        else:
            assert False

    for key in rem_key:
        mbox.remove(key)
    mbox.flush()
    mbox.close()

    assert True


@step(u"White Rabbit gets (\d+) REST requests")
def check_rest(step, count):
    global REST
    assert REST.value == int(count)


@step(u"Alice update events status as handled")
def check_update_db(step):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'], autoflush=False)
    db = db_manager.get_db()
    try:
        events = db.events.filter(db.events.event_id.in_(world.mail_events)).all()
        for event in events:
            assert event.ishandled == 1
    finally:
        db.session.remove()


def before_scenario(scenario):
    world.config = None
    world.mail_events = []
    world.sent_emails = []
    global REST
    REST = mp.Value('i', 0)
    mbox = mailbox.mbox('/var/mail/root')
    mbox.clear()
    mbox.flush()


def after_scenario(scenario):
    pass


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)

