import os
import yaml
import random

from datetime import date

from scalrpy.util import dbmanager

from scalrpytests.steplib import lib

from lettuce import world, step, before, after


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
    step.given("Alice has 'dns_manager' test config")
    step.given("Alice stops 'mysql' service")
    step.given("Alice starts 'mysql' service")
    step.given("Alice drops test database")
    step.given("Alice creates test database")
    step.given("Alice creates table 'dns_zones'")
    step.given("Alice creates table 'dns_zone_records'")


@step(u"White Rabbit inserts (\d+) dns zones with '(.*)' status in test database")
def insert_dns_zones(step, count, status):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        for i in range(int(count)):

            while True:
                index = random.randint(1, 9999)
                zone_name = 'test.zone%s.bla' % index
                if db.session.query(db.dns_zones).filter(
                        db.dns_zones.zone_name==zone_name).first() is None:
                    break
                continue

            if status == 'Random':
                status = random.choice(['Active', 'Pending create', 'Pending update', 'Inactive'])
            soa_owner = 'owner.%s' % zone_name
            soa_serial = date.today().strftime('%Y%m%d')
            soa_parent = 'parent.%s' % zone_name
            isonnsserver=random.randint(0,1)

            db.dns_zones.insert(id=index, zone_name=zone_name, soa_parent=soa_parent, env_id=0,
                    status=status, isonnsserver=isonnsserver, soa_owner=soa_owner,
                    soa_serial=soa_serial)

            if status in ['Pending create', 'Pending update']:
                if zone_name not in world.dns_zones_files_for_create:
                    world.dns_zones_files_for_create.append(zone_name)
            if status in ['Pending delete', 'Inactive']:
                if zone_name not in world.dns_zones_files_for_remove:
                    world.dns_zones_files_for_remove.append(zone_name)
            if status in ['Pending delete']:
                world.dns_zones_for_remove.append(zone_name)

        db.session.commit()

    finally:
        db.session.remove()

    assert True


@step(u"White Rabbit inserts (\d+) wrong dns zones with '(.*)' status in test database")
def insert_dns_zones(step, count, status):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        for i in range(int(count)):

            while True:
                index = random.randint(1, 9999)
                zone_name = 'test.zone%s.bla' % index
                if db.session.query(db.dns_zones).filter(
                        db.dns_zones.zone_name==zone_name).first() is None:
                    break
                continue

            if status == 'Random':
                status = random.choice(['Active', 'Pending create', 'Pending update', 'Inactive'])
            soa_owner = random.choice(['owner.%s' % zone_name, '', None])
            soa_serial = random.choice([date.today().strftime('%Y%m%d'), '', None])
            soa_parent = random.choice(['parent.%s' % zone_name, '', None])
            isonnsserver=random.randint(0,1)

            db.dns_zones.insert(id=index, zone_name=zone_name, soa_parent=soa_parent, env_id=0,
                    status=status, isonnsserver=isonnsserver, soa_owner=soa_owner,
                    soa_serial=soa_serial)

            if status in ['Pending create', 'Pending update']:
                if zone_name not in world.dns_zones_files_for_create:
                    world.dns_zones_files_for_create.append(zone_name)
            if status in ['Pending delete', 'Inactive']:
                if zone_name not in world.dns_zones_files_for_remove:
                    world.dns_zones_files_for_remove.append(zone_name)
            if status in ['Pending delete']:
                world.dns_zones_for_remove.append(zone_name)

        db.session.commit()

    finally:
        db.session.remove()

    assert True


@step(u"Alice starts '(.*)' daemon")
def start_daemon(step, name):
    config = ETC_DIR + '/config.yml'
    assert lib.start_daemon(name, config)


@step(u"Alice stops '(.*)' daemon")
def stop_daemon(step, name):
    config = ETC_DIR + '/config.yml'
    assert lib.stop_daemon(name, config)


@step(u"White Rabbit checks zones files")
def check_zones_files(step):
    path = world.config['zones_dir']
    if not os.path.exists(path):
        assert not world.dns_zones_files_for_create
        return

    zones = os.listdir(world.config['zones_dir'])
    for zone in world.dns_zones_files_for_create:
        assert '%s.db' % zone in zones
    for zone in world.dns_zones_files_for_remove:
        assert '%s.db' % zone not in zones


@step(u"Alice clears zones files dir")
def clear(step):
    if hasattr(world, 'config'):
        path = world.config['zones_dir']
        if not os.path.exists(path):
            return

        zones = os.listdir(path)
        for zone in zones:
            os.remove('%s/%s' % (path, zone))


@step(u"White Rabbit changes '(.*)' status of dns zones to '(.*)'")
def update_status(step, status1, status2):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        zones = db.session.query(db.dns_zones).filter(
                db.dns_zones.status==status1).all()
        for zone in zones:
            zone.status = status2
            if status2 in ['Pending create', 'Pending update']:
                if zone.zone_name not in world.dns_zones_files_for_create:
                    world.dns_zones_files_for_create.append(zone.zone_name)
                try:
                    world.dns_zones_files_for_remove.remove(zone.zone_name)
                except: pass
                try:
                    world.dns_zones_for_remove.remove(zone.zone_name)
                except: pass
            if status2 in ['Pending delete', 'Inactive']:
                if zone.zone_name not in world.dns_zones_files_for_remove:
                    world.dns_zones_files_for_remove.append(zone.zone_name)
                try:
                    world.dns_zones_files_for_create.remove(zone.zone_name)
                except: pass
            if status2 in ['Pending delete']:
                if zone.zone_name not in world.dns_zones_files_for_remove:
                    world.dns_zones_for_remove.append(zone.zone_name)

        db.session.commit()

    finally:
        db.session.remove()

    assert True


@step(u"White Rabbit changes status of all dns zones to '(.*)'")
def update_status(step, status):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        zones = db.session.query(db.dns_zones).all()
        for zone in zones:
            zone.status = status
            if status in ['Pending create', 'Pending update']:
                if zone.zone_name not in world.dns_zones_files_for_create:
                    world.dns_zones_files_for_create.append(zone.zone_name)
                try:
                    world.dns_zones_files_for_remove.remove(zone.zone_name)
                except: pass
                try:
                    world.dns_zones_for_remove.remove(zone.zone_name)
                except: pass
            if status in ['Pending delete', 'Inactive']:
                if zone.zone_name not in world.dns_zones_files_for_remove:
                    world.dns_zones_files_for_remove.append(zone.zone_name)
                try:
                    world.dns_zones_files_for_create.remove(zone.zone_name)
                except: pass
            if status in ['Pending delete']:
                if zone.zone_name not in world.dns_zones_files_for_remove:
                    world.dns_zones_for_remove.append(zone.zone_name)

        db.session.commit()

    finally:
        db.session.remove()

    assert True


@step(u"White Rabbit checks test database has been updated")
def check_database(step):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    try:
        zones = db.session.query(db.dns_zones).filter(
                db.dns_zones.zone_name.in_(world.dns_zones_for_remove)).all()
        assert not zones
        zones = db.session.query(db.dns_zones).filter(
                db.dns_zones.zone_name.in_(world.dns_zones_files_for_create)).all()
        for zone in zones:
            assert zone.status == 'Active'
    finally:
        db.session.remove


def before_scenario(scenario):
    world.dns_zones_files_for_create = []
    world.dns_zones_files_for_remove = []
    world.dns_zones_for_remove = []


def after_scenario(scenario):
    pass


before.each_scenario(before_scenario)
after.each_scenario(after_scenario)
