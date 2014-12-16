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

from scalrpy.util import rpc
from scalrpy.util import helper
from scalrpy.load_statistics_cleaner import LoadStatisticsCleaner

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

from lettuce import step, before, after


class LoadStatisticsCleanerScript(lib.Script):

    app_cls = LoadStatisticsCleaner
    name = 'load_statistics_cleaner'


lib.ScriptCls = LoadStatisticsCleanerScript


@step(u"White Rabbit has (\d+) farms in database")
def fill_tables(step, count):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.farms_ids = list()
    for i in range(int(count)):
        while True:
            farm_id = random.randint(1, 9999)
            query = "SELECT id FROM farms WHERE id={0}".format(farm_id)
            if bool(db.execute(query)):
                continue
            break
        query = "INSERT INTO farms (id) VALUES ({0})".format(farm_id)
        db.execute(query)
        try:
            os.makedirs(os.path.join(lib.world.config['rrd_dir'], helper.x1x2(farm_id), str(farm_id)))
        except OSError as e:
            if e.args[0] != 17:
                raise
        lib.world.farms_ids.append(farm_id)
    time.sleep(1)


@step(u"White Rabbit has (\d+) farms for delete")
def create_folder(step, count):
    lib.world.farms_ids_for_delete = list()
    for i in range(int(count)):
        while True:
            farm_id_for_delete = random.randint(1, 9999)
            try:
                os.makedirs('%s/%s/%s' % (
                    lib.world.config['rrd_dir'],
                    helper.x1x2(farm_id_for_delete),
                    farm_id_for_delete)
                )
                lib.world.farms_ids_for_delete.append(farm_id_for_delete)
                break
            except OSError as e:
                if e.args[0] != 17:
                    raise
 
    try:
        os.makedirs('%s/wrongfolder' % lib.world.config['rrd_dir'])
    except OSError as e:
        if e.args[0] != 17:
            raise
    try:
        os.makedirs('%s/x1x6/wrongfolder' % lib.world.config['rrd_dir'])
    except OSError as e:
        if e.args[0] != 17:
            raise


@step(u"White Rabbit sees right folders were deleted")
def check_folders(step):
    for farm_id_for_delete in lib.world.farms_ids_for_delete:
        assert not os.path.exists('%s/%s/%s' % (
                lib.world.config['rrd_dir'],
                helper.x1x2(farm_id_for_delete),
                farm_id_for_delete)
            )

@step(u"White Rabbit sees right folders were not deleted")
def check_folders(step):
    for farm_id in lib.world.farms_ids:
        assert os.path.exists('%s/%s/%s' % (
                lib.world.config['rrd_dir'],
                helper.x1x2(farm_id),
                farm_id)
            ), farm_id

