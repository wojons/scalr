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
import logging
import argparse

from scalrpy.util import cron
from scalrpy.util import helper

from scalrpy import __version__


LOG = logging.getLogger('ScalrPy')


class Application(cron.Cron):

    app_name = os.path.basename(sys.argv[0]).rstrip('.pyc')

    config = {
        'daemon': False,
        'log_file': '/var/log/scalr.%s.log' % app_name,
        'pid_file': '/var/run/scalr.%s.pid' % app_name,
        'log_size': 1024 * 1000,
        'user': False,
        'group': False,
        'verbosity': 1,
    }

    parser = argparse.ArgumentParser()

    parser_group1 = parser.add_mutually_exclusive_group()
    parser_group1.add_argument('--start', action='store_true', default=False,
            help='start program')
    parser_group1.add_argument('--stop', action='store_true', default=False,
            help='stop program')
    parser.add_argument('--daemon', action='store_true', default=None,
            help="run in daemon mode")
    parser.add_argument('-p', '--pid-file', default=None,
            help="pid file")
    parser.add_argument('-l', '--log-file', default=None,
            help="log file")
    parser.add_argument('-c', '--config-file', default='./config.yml',
            help='config file')
    parser.add_argument('-v', '--verbosity', action='count', default=None,
            help='increase output verbosity')
    parser.add_argument('--version', action='version', version='Version %s' % __version__)

    def __init__(self, config, args=None):
        for k, v in config.iteritems():
            if k not in self.config:
                self.config[k] = v
        self.args = args or argparse.Namespace()

        self.configure()
        helper.update_config(config_to=self.config, args=args)
        helper.validate_config(self.config)
        helper.configure_log(
            log_level=self.config['verbosity'],
            log_file=self.config['log_file'],
            log_size=self.config['log_size'])

        super(Application, self).__init__(self.config['pid_file'])

    def change_permissions(self):
        if self.config['group']:
            helper.chown(self.config['log_file'], os.getuid(), self.config['group'])
            helper.chown(self.config['pid_file'], os.getuid(), self.config['group'])
            helper.set_gid(self.config['group'])
        if self.config['user']:
            helper.chown(self.config['log_file'], self.config['user'], os.getgid())
            helper.chown(self.config['pid_file'], self.config['user'], os.getgid())
            helper.set_uid(self.config['user'])

    def configure(self):
        raise NotImplementedError

    def run(self):
        if self.args.start:
            if helper.check_pid(self.config['pid_file']):
                msg = "Application with pid file '%s' already running. Exit" % config['pid_file']
                LOG.info(msg)
                sys.exit(0)
            if self.args.daemon:
                helper.daemonize()
            self.start()
        elif self.args.stop:
            self.stop()
        else:
            print 'Usage %s -h' % sys.argv[0]
