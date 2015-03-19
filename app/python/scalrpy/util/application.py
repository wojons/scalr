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
import abc
import time
import yaml
import atexit
import docopt
import psutil

from textwrap import dedent

from scalrpy.util import helper
from scalrpy.util import exceptions

from scalrpy import __version__
from scalrpy import LOG



class Application(object):
    """
    Base application class
    You must override __call__ method
    """

    __metaclass__ = abc.ABCMeta

    __doc__ = dedent(
        """
        {description}

        Usage:
          {name} [options] (start | stop)
        {custom_usage}

        Options:
        {custom_options}
          -d, --daemon                      daemonize application
          -l <file>, --log-file=<file>      log file, default: /var/log/scalr.{name}.log
          -p <file>, --pid-file=<file>      pid file, default: /var/run/scalr.{name}.pid
          -u <user>, --user=<user>          user
          -g <group>, --group=<group>       group
          -v <level>, --verbosity=<level>   verbosity level ({log_levels}),
                                                default: ERROR
          -V, --version
          -h, --help
        """
    )

    name = os.path.basename(sys.argv[0]).rstrip('.pyc')
    description = "Base application"

    config = {
        'user': False,
        'group': False,
        'log_size': 1024 * 1024 * 10,
        'log_file': '/var/log/scalr.%s.log' % name,
        'pid_file': '/var/run/scalr.%s.pid' % name,
    }

    _custom_usage = ''
    _custom_options = ''


    def __init__(self, argv=None):
        self.args = docopt.docopt(self.help(), argv=argv, version=__version__, help=True)
        self.validate_args()


    @abc.abstractmethod
    def __call__(self):
        return


    def _update_config(self):
        if self.args['--log-file']:
            self.config['log_file'] = self.args['--log-file']
        if self.args['--pid-file']:
            self.config['pid_file'] = self.args['--pid-file']
        if self.args['--user']:
            self.config['user'] = self.args['--user']
        if self.args['--group']:
            self.config['group'] = self.args['--group']


    def validate_args(self):
        if self.args['--verbosity']:
            assert_msg = 'Invalid verbosity level, try -h for help'
            assert self.args['--verbosity'] in helper.log_level_map, assert_msg
        else:
            self.args['--verbosity'] = 'ERROR'


    def validate_config(self):
        assert self.config['log_file']
        assert isinstance(self.config['log_file'], basestring)
        assert self.config['pid_file']
        assert isinstance(self.config['pid_file'], basestring)


    def add_usage(self, usage):
        if self._custom_usage:
            self._custom_usage = self._custom_usage + '\n' + usage
        else:
            self._custom_usage = usage


    def add_options(self, options):
        if self._custom_options:
            self._custom_options = self._custom_options + '\n' + options
        else:
            self._custom_options = options


    def help(self):
        doc = Application.__doc__.format(
                description=self.description,
                name=self.name,
                log_levels=' | '.join(helper.log_level_map.keys()),
                custom_usage=self._custom_usage,
                custom_options=self._custom_options)
        doc = doc.replace('\n\n\n', '\n\n')
        return doc


    def change_permissions(self):
        if self.config['group']:
            if self.config['log_file']:
                helper.chown(self.config['log_file'], os.getuid(), self.config['group'])
            helper.chown(self.config['pid_file'], os.getuid(), self.config['group'])
            helper.set_gid(self.config['group'])
        if self.config['user']:
            if self.config['log_file']:
                helper.chown(self.config['log_file'], self.config['user'], os.getgid())
            helper.chown(self.config['pid_file'], self.config['user'], os.getgid())
            helper.set_uid(self.config['user'])


    def _configure(self):
        self._update_config()
        self.validate_config()
        helper.configure_log(
                log_level=helper.log_level_map[self.args['--verbosity']],
                log_file=self.config['log_file'],
                log_size=self.config['log_size'])


    def _start(self):
        if helper.check_pid(self.config['pid_file']):
            raise exceptions.AlreadyRunningError(self.config['pid_file'])
        LOG.debug('Starting')
        if self.args['--daemon']:
            helper.daemonize()
        helper.create_pid_file(self.config['pid_file'])
        atexit.register(helper.delete_file, self.config['pid_file'])
        LOG.info('Started')
        helper.set_proc_name(self.name)
        self()
        LOG.info('Stopped')


    def _stop(self):
        LOG.debug('Stopping')
        try:
            if not os.path.exists(self.config['pid_file']):
                msg = "Can't stop, pid file %s doesn't exist\n" % self.config['pid_file']
                sys.stderr.write(helper.colorize(helper.Color.FAIL, msg))
                return
            with file(self.config['pid_file'], 'r') as pf:
                pid = int(pf.read().strip())
            for ps in psutil.process_iter():
                if ps.name() == self.name[0:15]:
                    # TODO
                    # SIGINT
                    helper.kill_children(pid)
                    helper.kill(pid)
                    break
            else:
                msg = "Process with name {0} doesn't exists".format(self.name)
                raise Exception(msg)
            LOG.info('Stopped')
            helper.delete_file(self.config['pid_file'])
        except:
            msg = "Can't stop, reason: {error}".format(error=helper.exc_info())
            raise Exception(msg)


    def run(self):
        self._configure()
        if self.args['start']:
            self._start()
        elif self.args['stop']:
            self._stop()
        else:
            print self.help()



class ScalrApplication(Application):

    def __init__(self, argv=None):
        cwd = os.path.dirname(os.path.abspath(__file__))
        self.scalr_dir = os.path.normpath(os.path.abspath(os.path.join(cwd, '../../../../')))
        options = (
                """  -c <file>, --config=<file>        Scalr config file\n"""
                """\t\t\t\t\t[default: {scalr_dir}/app/etc/config.yml]"""
        ).format(scalr_dir=self.scalr_dir)
        self.add_options(options)

        super(ScalrApplication, self).__init__(argv=argv)

        self.scalr_config = None
        self.config.update({
            'connections': {
                'mysql': {
                    'user': None,
                    'pass': None,
                    'host': None,
                    'port': 3306,
                    'name': None,
                    'pool_size': 10,
                },
            },
            'instances_connection_timeout': 5,
        })


    def validate_args(self):
        Application.validate_args(self)
        assert isinstance(self.args['--config'], basestring), type(self.args['--config'])
        assert_msg = "%s doesn't exists or it's not a file" % self.args['--config']
        assert os.path.isfile(self.args['--config']), assert_msg


    def validate_config(self):
        Application.validate_config(self)
        int(self.config['connections']['mysql']['port'])
        int(self.config['connections']['mysql']['pool_size'])


    def load_config(self):
        try:
            self.scalr_config = yaml.safe_load(open(self.args['--config']))['scalr']
        except:
            msg = 'Unable to load Scalr config.yml file, reason: {error}'.format(
                    error=helper.exc_info())
            raise Exception(msg)

        helper.update_config(
                self.scalr_config.get('connections', {}).get('mysql', {}),
                self.config['connections']['mysql'])
        self.config['instances_connection_timeout'] = self.scalr_config.get(
                'system', {}).get(
                'instances_connection_timeout', self.config['instances_connection_timeout'])



class ScalrIterationApplication(ScalrApplication):

    def __init__(self, argv=None, interval=False):
        super(ScalrIterationApplication, self).__init__(argv=argv)

        self.config.update({'interval': False})

        self.nothing_to_do_sleep = 5
        self.error_sleep = 5
        self.iteration_timeout = 300
        self.iteration_timestamp = None


    @helper.greenlet
    def _do_iteration(self):
        try:
            self.do_iteration()
        except exceptions.QuitError:
            sys.exit(0)
        except (SystemExit, KeyboardInterrupt):
            raise
        except exceptions.NothingToDoError:
            time.sleep(self.nothing_to_do_sleep)
        except:
            LOG.error('Iteration failed, reason: {0}'.format(helper.exc_info()))
            self.on_iteration_error()
            time.sleep(self.error_sleep)


    @abc.abstractmethod
    def do_iteration(self):
        return


    def on_iteration_error(self):
        return


    def __call__(self):
        self.change_permissions()
        while True:
            self.iteration_timestamp = time.time()
            g = self._do_iteration()
            try:
                g.get(timeout=self.iteration_timeout)
            finally:
                if not g.ready():
                    g.kill()
            LOG.debug('End iteration: {0:.1f} seconds'.format(time.time() - self.iteration_timestamp))
            if self.config['interval']:
                next_iteration_time = self.iteration_timestamp + self.config['interval']
                time.sleep(next_iteration_time - time.time())
