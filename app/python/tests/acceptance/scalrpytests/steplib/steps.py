
import os
import yaml

from scalrpytests.steplib import lib

from lettuce import world, step, after, before


BASE_DIR = os.path.dirname(os.path.abspath(__file__))
ETC_DIR = os.path.abspath(BASE_DIR + '/../../../etc')


@step(u"White Rabbit has test config")
def test_config(step):
    world.config = yaml.safe_load(
            open(ETC_DIR + '/config.yml'))['scalr']
    assert True


@step(u"White Rabbit drops test database")
def drop_db(step):
    assert lib.drop_db(world.config['connections']['mysql'])


@step(u"White Rabbit creates test database")
def create_db(step):
    assert lib.create_db(world.config['connections']['mysql'])


@step(u"White Rabbit creates table '(.*)'")
def create_table(step, table):
    assert lib.create_table(world.config['connections']['mysql'], str(table))

