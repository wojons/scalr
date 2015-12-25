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

from gevent import monkey
monkey.patch_all()

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.join(cwd, '..')
sys.path.insert(0, scalrpy_dir)

import shutil
import pymysql.err

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import application

from scalrpy import LOG
from scalrpy import exceptions


helper.patch_gevent()


app = None


class LoadStatisticsCleaner(application.ScalrApplication):

    def __init__(self, argv=None):
        self.description = "Scalr load statistics cleaner application"
        options = "  --test                            test only, do not remove anything"
        self.add_options(options)

        super(LoadStatisticsCleaner, self).__init__(argv=argv)

        self.config.update({'rrd_dir': None})
        self._db = None

    def configure(self):
        try:
            rrd_dir = self.scalr_config['stats_poller']['rrd_db_dir']
        except KeyError:
            rrd_dir = self.scalr_config['load_statistics']['rrd']['dir']
        self.config['rrd_dir'] = rrd_dir
        helper.validate_config(self.config)

        self._db = dbmanager.ScalrDB(self.config['connections']['mysql'])

    def _is_farm_exists(self, farm):
        query = "SELECT id FROM farms WHERE id={id}".format(**farm)
        return bool(self._db.execute(query))

    def clean(self):
        for directory in os.listdir(self.config['rrd_dir']):
            for farm_id in os.listdir('%s/%s' % (self.config['rrd_dir'], directory)):
                try:
                    if self._is_farm_exists({'id': int(farm_id)}):
                        continue
                    dir_to_delete = os.path.join(self.config['rrd_dir'], directory, farm_id)
                    LOG.debug('Delete farm {0}: {1}'.format(farm_id, dir_to_delete))
                    if self.args['--test']:
                        continue
                    shutil.rmtree(dir_to_delete, ignore_errors=True)
                except KeyboardInterrupt:
                    raise
                except pymysql.err.Error as e:
                    if e.args[0] == KeyboardInterrupt:
                        raise KeyboardInterrupt
                    LOG.warning(helper.exc_info())
                except:
                    LOG.warning(helper.exc_info())

    def __call__(self):
        self.clean()


def main():
    global app
    app = LoadStatisticsCleaner()
    try:
        app.load_config()
        app.configure()
        app.run()
    except exceptions.AlreadyRunningError:
        LOG.info(helper.exc_info(where=False))
    except (SystemExit, KeyboardInterrupt):
        pass
    except:
        LOG.exception('Oops')


if __name__ == '__main__':
    main()
