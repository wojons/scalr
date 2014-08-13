from lettuce import *

import os
import yaml
import time
import string
import random
import subprocess as subps

from sqlalchemy import and_
from sqlalchemy import desc
from sqlalchemy import func
from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool

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


@step(u"I stop '(.*)' service")
def stop_service(step, name):
    assert lib.stop_service(name)


@step(u"I start '(.*)' service")
def start_service(step, name):
    assert lib.start_service(name)


@step(u"I start stats_cleaner")
def start_stats_cleaner(step):
    config = ETC_DIR + '/config.yml'
    cmd = 'python -m scalrpy.stats_cleaner -vvvv -c %s' % config
    subps.Popen(cmd.split())

    time.sleep(0.5)

    ps = subps.Popen('ps -ef'.split(), stdout=subps.PIPE)
    output = ps.stdout.read()
    ps.stdout.close()
    ps.wait()

    return 'scalrpy.stats_cleaner -vvvv -c %s' % config in output


@step(u"I make prepare")
def prepare(step):
    step.given("I have 'load_statistics' test config")
    step.given("I stop 'mysql' service")
    step.given("I start 'mysql' service")
    step.given("I drop test database")
    step.given("I create test database")


@step(u"I have (\d+) farms in database")
def fill_tables(step, count):
    db_manager = dbmanager.DBManager(world.config['connections']['mysql'])
    db = db_manager.get_db()
    world.farm_id = list()
    try:
        for i in range(int(count)):

            while True:
                farm_id = random.randint(1, 9999)
                if db.farms.filter(db.farms.id == farm_id).first() is None:
                    break
                continue

            db.farms.insert(id=farm_id, env_id=0, changed_by_id=0)

            try:
                os.makedirs('%s/%s/%s' % (world.config['rrd']['dir'], helper.x1x2(farm_id), farm_id))
            except OSError as e:
                if e.args[0] != 17:
                    raise
        
            world.farm_id.append(farm_id)

        db.commit()


    finally:
        db.session.remove()

    lib.wait_sec(1)
    assert True

@step(u"I have (\d+) farms for delete")
def create_folder(step, count):
    world.farm_id_for_delete = list()
    for i in range(int(count)):
        while True:
            farm_id_for_delete = random.randint(1, 9999)
            try:
                os.makedirs('%s/%s/%s' % (
                    world.config['rrd']['dir'],
                    helper.x1x2(farm_id_for_delete),
                    farm_id_for_delete)
                )
                world.farm_id_for_delete.append(farm_id_for_delete)
                break
            except OSError as e:
                if e.args[0] != 17:
                    raise

    try:
        os.makedirs('%s/wrongfolder' % world.config['rrd']['dir'])
    except OSError as e:
        if e.args[0] != 17:
            raise
    try:
        os.makedirs('%s/x1x6/wrongfolder' % world.config['rrd']['dir'])
    except OSError as e:
        if e.args[0] != 17:
            raise


@step(u"I see right folders were deleted")
def check_folders(step):
    for farm_id_for_delete in world.farm_id_for_delete:
        assert not os.path.exists('%s/%s/%s' % (
                world.config['rrd']['dir'],
                helper.x1x2(farm_id_for_delete),
                farm_id_for_delete)
            )

@step(u"I see right folders were not deleted")
def check_folders(step):
    for farm_id in world.farm_id:
        assert os.path.exists('%s/%s/%s' % (
                world.config['rrd']['dir'],
                helper.x1x2(farm_id),
                farm_id)
            )

