import os
import sys
import yaml
import shutil
import logging
import argparse

from scalrpy.util import helper
from scalrpy.util import dbmanager

from scalrpy import __version__


CONFIG = {
        'connections':{
            'mysql':{
                'user':None,
                'pass':None,
                'host':None,
                'port':3306,
                'name':None,
                'pool_size':1,
                },
            },
        'rrd_db_dir': None,
        'log_file':'/var/log/scalr.stats-cleaner.log',
        'pid_file':'/var/run/scalr.stats-cleaner.pid',
        'verbosity':1,
        }

LOG = logging.getLogger('ScalrPy')


def clean():
    connection = dbmanager.make_connection(CONFIG['connections']['mysql'])
    cursor = connection.cursor()
    try:
        cursor.execute("""SELECT `id` FROM `farms`""")
        farms_id = cursor.fetchall()
    finally:
        cursor.close()
        connection.close()
    for dir_ in os.listdir(CONFIG['rrd_db_dir']):
        for farm_id in os.listdir('%s/%s' % (CONFIG['rrd_db_dir'], dir_)):
            if farm_id not in farms_id:
                LOG.debug('Delete farm %s' % farm_id)
                if not CONFIG['test']:
                    dir_to_delete = '%s/%s/%s' % (CONFIG['rrd_db_dir'], dir_, farm_id),
                    shutil.rmtree(dir_to_delete, ignore_errors=True)


def configure(config, args=None):
    global CONFIG
    helper.update_config(config['connections']['mysql'], CONFIG['connections']['mysql'])
    if 'stats_poller' in config:
        CONFIG['rrd_db_dir'] = config['stats_poller']['rrd_db_dir']
        if 'connections' in config['stats_poller']:
            helper.update_config(
                    config['stats_poller']['connections']['mysql'],
                    CONFIG['connections']['mysql']
                    )
    if 'stats_cleaner' in config:
        helper.update_config(config['stats_cleaner'], CONFIG)
    helper.update_config(config_to=CONFIG, args=args)
    helper.validate_config(CONFIG)
    helper.configure_log(
            log_level=CONFIG['verbosity'],
            log_file=CONFIG['log_file'],
            log_size=1024*1000
            )


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('-l', '--log-file', default=None,
            help="log file")
    parser.add_argument('-c', '--config-file', default='./config.yml',
            help='config file')
    parser.add_argument('-d', '--rrd-db-dir', default=None,
            help='path to rrd database')
    parser.add_argument('-v', '--verbosity', action='count', default=1,
            help='increase output verbosity [0:4]. Default is 1 - ERROR')
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
    except SystemExit:
        pass
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
