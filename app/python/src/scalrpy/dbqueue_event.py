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

import sys
import yaml
import time
import socket
import gevent
import logging
import pymysql
import argparse
import requests
import requests.exceptions

from gevent.pool import Group as Pool

from scalrpy.util import cron
from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool

from scalrpy import __version__

helper.patch_gevent()

CONFIG = {
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
    'pool_size': 100,
    'no_daemon': False,
    'instances_connection_timeout': 10,
    'log_file': '/var/log/scalr.dbqueue-event.log',
    'pid_file': '/var/run/scalr.dbqueue-event.pid',
    'verbosity': 1,
}

LOG = logging.getLogger('ScalrPy')

POOL = Pool()


class NothingToDoError(Exception):
    pass


class IterationTimeoutError(Exception):
    pass


def wait_pool():
    while len(POOL) >= CONFIG['pool_size']:
        gevent.sleep(0.2)


class DBQueueEvent(cron.Cron):

    def __init__(self):
        super(DBQueueEvent, self).__init__(pid_file=CONFIG['pid_file'])
        self.iteration_timestamp = None
        self._db = dbmanager.ScalrDB(CONFIG['connections']['mysql'])

    def _get_db_webhook_history(self):
        query = (
                "SELECT HEX(`history_id`) as history_id, HEX(`webhook_id`) as webhook_id,"
                "HEX(`endpoint_id`) as endpoint_id,`payload` "
                "FROM webhook_history "
                "WHERE `status`=0 "
                "LIMIT 250"
        )
        return self._db.execute(query)

    def _get_db_webhook_endpoints(self, webhook_history):
        endpoint_ids = list(set([_['endpoint_id'] for _ in webhook_history]))
        if not endpoint_ids:
            return tuple()
        query = (
                "SELECT HEX(`endpoint_id`) as endpoint_id, `url`, `security_key` "
                "FROM webhook_endpoints "
                "WHERE HEX(`endpoint_id`) IN ({0})"
        ).format(str(endpoint_ids)[1:-1])
        return self._db.execute(query)

    def get_webhooks(self):
        webhook_history = self._get_db_webhook_history()
        webhook_endpoints = self._get_db_webhook_endpoints(webhook_history)
        webhook_endpoints_map = dict((_['endpoint_id'], _) for _ in webhook_endpoints)
        webhooks = []
        for webhook in webhook_history:
            webhook_endpoint = webhook_endpoints_map[webhook['endpoint_id']]
            webhook['url'] = webhook_endpoint['url']
            webhook['security_key'] = webhook_endpoint['security_key']
            webhooks.append(webhook)
        return webhooks

    class PostError(Exception):
        pass

    def post_webhook(self, webhook):
        signature, date = cryptotool.sign(webhook['payload'], webhook['security_key'], version=2)
        headers = {
            'Date': date,
            'X-Signature': signature,
            'X-Scalr-Webhook-Id': webhook['history_id'],
            'Content-type': 'application/json',
        }

        if webhook['url'] == 'SCALR_MAIL_SERVICE':
            url = 'https://my.scalr.com/webhook_mail.php'
        else:
            url = webhook['url']

        try:
            r = requests.post(url, data=webhook['payload'], headers=headers, timeout=3)
        except requests.exceptions.RequestException:
            msg = '{0}, url: {1}'.format(sys.exc_info()[0].__name__, url)
            raise DBQueueEvent.PostError(msg)

        if r.status_code <= 205:
            msg = "Request OK. url: {0}, webhook history_id: {1}, status: {2}"
            msg = msg.format(url, webhook['history_id'], r.status_code)
            LOG.debug(msg)
        else:
            webhook['error_msg'] = r.text.encode('ascii', 'replace')
            msg = "Request failed. url: {0}, webhook history_id: {1}, status: {2}, text: {3}"
            msg = msg.format(url, webhook['history_id'], r.status_code, r.text)
            LOG.warning(msg)
        return r.status_code

    def update_webhook(self, webhook):
        while True:
            try:
                response_code = webhook['response_code']
                error_msg = webhook.get('error_msg', '')[0:255]
                history_id = webhook['history_id']

                if response_code == 'NULL' or response_code > 205:
                    status = 2
                else:
                    status = 1
                query = (
                        """UPDATE `webhook_history` """
                        """SET `status`={0},`response_code`={1}, `error_msg`="{2}" """
                        """WHERE `history_id`=UNHEX('{3}')"""
                ).format(status, response_code, pymysql.escape_string(error_msg), history_id)
                self._db.execute(query)
                break
            except KeyboardInterrupt:
                raise
            except:
                msg = "Webhook update failed, history_id: {0}, reason: {1}"
                msg = msg.format(webhook['history_id'], helper.exc_info())
                LOG.warning(msg)
                time.sleep(5)

    @helper.greenlet
    def do_iteration(self):
        self.iteration_timestamp = time.time()
        webhooks = self.get_webhooks()
        if not webhooks:
            raise NothingToDoError()

        for webhook in webhooks:
            try:
                wait_pool()
                webhook['async_result'] = POOL.apply_async(self.post_webhook, (webhook,))
            except:
                msg = "Unable to process webhook history_id: {0}, reason: {1}"
                msg = msg.format(webhook['history_id'], helper.exc_info())
                LOG.warning(msg)

        for webhook in webhooks:
            try:
                webhook['response_code'] = webhook['async_result'].get(timeout=60)
            except DBQueueEvent.PostError:
                error_msg = str(sys.exc_info()[1])
                self._handle_error(webhook, error_msg)
            except:
                error_msg = 'Internal error'
                self._handle_error(webhook, error_msg)
            try:
                wait_pool()
                POOL.apply_async(self.update_webhook, (webhook,))
            except:
                msg = "Unable to update webhook history_id: {0}, reason: {1}"
                msg = msg.format(webhook['history_id'], helper.exc_info())
                LOG.warning(msg)

        POOL.join()

    def _handle_error(self, webhook, error_msg):
        webhook['error_msg'] = error_msg
        webhook['response_code'] = 'NULL'
        msg = "Unable to process webhook history_id: {0}, reason: {1}"
        msg = msg.format(webhook['history_id'], helper.exc_info())
        LOG.warning(msg)

    def _run(self):
        while True:
            LOG.debug('Start iteration')
            try:
                g = self.do_iteration()
                try:
                    g.get(timeout=300)
                except gevent.Timeout:
                    raise IterationTimeoutError()
                finally:
                    if not g.ready():
                        g.kill()
            except KeyboardInterrupt:
                raise
            except NothingToDoError:
                LOG.debug('Nothing to do. Sleep 5 seconds')
                gevent.sleep(5)
            except:
                LOG.error('Iteration failed, reason: {0}'.format(helper.exc_info()))
                POOL.kill()
                gevent.sleep(5)
            finally:
                LOG.debug('End iteration: {0:.1f}'.format(time.time() - self.iteration_timestamp))
                gevent.sleep(0.2)


def configure(config, args=None):
    global CONFIG
    if 'connections' in config and 'mysql' in config['connections']:
        helper.update_config(config['connections']['mysql'], CONFIG['connections']['mysql'])
    if 'system' in config and 'instances_connection_timeout' in config['system']:
        timeout = config['system']['instances_connection_timeout']
        CONFIG['instances_connection_timeout'] = timeout
    if 'dbqueue_event' in config:
        helper.update_config(config['dbqueue_event'], CONFIG)
    helper.update_config(config_to=CONFIG, args=args)
    helper.validate_config(CONFIG)
    helper.configure_log(
        log_level=CONFIG['verbosity'],
        log_file=CONFIG['log_file'],
        log_size=1024 * 1000
    )
    socket.setdefaulttimeout(CONFIG['instances_connection_timeout'])


def main():
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--start', action='store_true', default=False,
            help='start program')
    group.add_argument('--stop', action='store_true', default=False,
            help='stop program')
    parser.add_argument('--no-daemon', action='store_true', default=None,
            help="run in no daemon mode")
    parser.add_argument('-p', '--pid-file', default=None,
            help="pid file")
    parser.add_argument('-l', '--log-file', default=None,
            help="log file")
    parser.add_argument('-c', '--config-file', default='./config.yml',
            help='config file')
    parser.add_argument('-t', '--instances-connection-timeout', type=int, default=None,
            help='instances connection timeout')
    parser.add_argument('-v', '--verbosity', action='count', default=None,
            help='increase output verbosity')
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
        app = DBQueueEvent()
        if args.start:
            if helper.check_pid(CONFIG['pid_file']):
                msg = "Application with pid file '{0}' already running. Exit"
                msg = msg.format(CONFIG['pid_file'])
                LOG.info(msg)
                sys.exit(0)
            if not args.no_daemon:
                helper.daemonize()
            app.start()
        elif args.stop:
            app.stop()
        else:
            print 'Usage %s -h' % sys.argv[0]
    except SystemExit:
        pass
    except KeyboardInterrupt:
        LOG.critical('KeyboardInterrupt')
        return
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
