# vim: tabstop=4 shiftwidth=4 softtabstop=4
#
# Copyright 2013, 2014 Scalr Inc.
#
# Licensed under the Apache License, Version 2.0 (the "License"); you may
# not use this file except in compliance with the License. You may obtain
# a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.

import os
import sys
import yaml
import shutil
import logging
import argparse
import pymysql.err

from scalrpy.util import helper
from scalrpy.util import dbmanager

from scalrpy import __version__


CONFIG = {
    'connections': {
        'mysql': {
            'user': None,
            'pass': None,
            'host': None,
            'port': 3306,
            'name': None,
            'pool_size': 1,
        },
    },
    'rrd': {'dir': None},
    'verbosity': 1,
    'log_file': '/var/log/scalr.stats-cleaner.log',
}

LOG = logging.getLogger('ScalrPy')


def get_farms():
    connection = dbmanager.make_connection(CONFIG['connections']['mysql'])
    cursor = connection.cursor()
    try:
        cursor.execute("SELECT id FROM farms")
        return cursor.fetchall()
    finally:
        cursor.close()
        connection.close()


def farm_exists(farm_id):
    connection = dbmanager.make_connection(CONFIG['connections']['mysql'])
    cursor = connection.cursor()
    try:
        cursor.execute("SELECT id FROM farms WHERE id=%s" % farm_id)
        return bool(cursor.fetchall())
    finally:
        cursor.close()
        connection.close()


def clean():
    LOG.debug('Start')
    farms_id = [int(_['id']) for _ in get_farms()]
    for dir_ in os.listdir(CONFIG['rrd']['dir']):
        for farm_id in os.listdir('%s/%s' % (CONFIG['rrd']['dir'], dir_)):
            try:
                if int(farm_id) not in farms_id and not farm_exists(farm_id):
                    LOG.debug('Delete farm %s' % farm_id)
                    if not CONFIG['test']:
                        dir_to_delete = '%s/%s/%s' % (CONFIG['rrd']['dir'], dir_, farm_id)
                        shutil.rmtree(dir_to_delete, ignore_errors=True)
            except KeyboardInterrupt:
                raise
            except pymysql.err.Error as e:
                if e.args[0] == KeyboardInterrupt:
                    raise KeyboardInterrupt
                LOG.warning(helper.exc_info())
            except:
                LOG.warning(helper.exc_info())


def configure(config, args=None):
    global CONFIG
    if 'connections' in config and 'mysql' in config['connections']:
        helper.update_config(config['connections']['mysql'], CONFIG['connections']['mysql'])
    try:
        CONFIG['rrd']['dir'] = config['stats_poller']['rrd_db_dir']
    except:
        pass
    try:
        helper.update_config(
            config['stats_poller']['connections']['mysql'],
            CONFIG['connections']['mysql']
        )
    except:
        pass
    try:
        CONFIG['rrd']['dir'] = config['load_statistics']['rrd']['dir']
    except:
        pass
    try:
        helper.update_config(
            config['load_statistics']['connections']['mysql'],
            CONFIG['connections']['mysql']
        )
    except:
        pass
    if 'stats_cleaner' in config:
        helper.update_config(config['stats_cleaner'], CONFIG)
    helper.update_config(config_to=CONFIG, args=args)
    helper.validate_config(CONFIG)
    helper.configure_log(
        log_level=CONFIG['verbosity'],
        log_file=CONFIG['log_file'],
        log_size=1024 * 1000
    )


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('-l', '--log-file', default=None,
            help="log file")
    parser.add_argument('-c', '--config-file', default='./config.yml',
            help='config file')
    parser.add_argument('-d', '--rrd-db-dir', default=None,
            help='path to rrd database')
    parser.add_argument('-v', '--verbosity', action='count', default=None,
            help='increase output verbosity')
    parser.add_argument('-t', '--test', action='store_true', default=False,
            help='test only')
    parser.add_argument('--version', action='version', version='Version %s' % __version__)
    args = parser.parse_args()
    try:
        config = yaml.safe_load(open(args.config_file))['scalr']
        configure(config, args)
    except:
        if args.verbosity > 3:
            raise
        else:
            sys.stderr.write('%s\n' % helper.exc_info())
        sys.exit(1)
    try:
        clean()
    except KeyboardInterrupt:
        LOG.critical('KeyboardInterrupt')
        sys.exit(0)
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
