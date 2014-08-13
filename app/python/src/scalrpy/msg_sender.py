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
import time
import yaml
import socket
import logging
import argparse
import requests

from gevent.pool import Group as Pool

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import basedaemon
from scalrpy.util import cryptotool

from scalrpy import __version__


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
    'cratio': 120,
    'instances_connection_timeout': 5,
    'log_file': '/var/log/scalr.msg-sender.log',
    'pid_file': '/var/run/scalr.msg-sender.pid',
    'verbosity': 1,
}
SCALR_CONFIG = None
LOG = logging.getLogger('ScalrPy')
POOL = Pool()


def wait_pool():
    while len(POOL) >= CONFIG['pool_size']:
        time.sleep(0.1)


class NothingToDoError(Exception):
    pass


class MsgSender(basedaemon.BaseDaemon):

    def __init__(self):
        super(MsgSender, self).__init__(pid_file=CONFIG['pid_file'])
        self._db = dbmanager.ScalrDB(CONFIG['connections']['mysql'])

    def _encrypt(self, server_id, crypto_key, data, headers=None):
        assert server_id, 'server_id'
        assert crypto_key, 'scalarizr.key'
        assert data, 'data to encrypt'
        crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)
        data = cryptotool.encrypt(crypto_algo, data, cryptotool.decrypt_key(crypto_key))
        headers = headers or dict()
        headers['X-Signature'], headers['Date'] = cryptotool.sign(data, crypto_key)
        headers['X-Server-Id'] = server_id
        LOG.debug("Server: %s, key: %s ... %s" % (server_id, crypto_key[0:5], crypto_key[-5:]))
        return data, headers

    def get_messages(self):
        query = (
                "SELECT messageid, server_id, event_id, message_format, "
                "handle_attempts ,message, message_name "
                "FROM messages "
                "WHERE type = 'out' "
                "AND status = 0 "
                "AND messageid IS NOT NULL "
                "AND messageid != '' "
                "AND message_version = 2 "
                "AND UNIX_TIMESTAMP(dtlasthandleattempt)+handle_attempts*{cratio}<UNIX_TIMESTAMP() "
                "ORDER BY id ASC "
                "LIMIT 250"
        ).format(cratio=CONFIG['cratio'])
        return self._db.execute(query)

    def get_servers(self, messages):
        servers = ()
        servers_id = [_['server_id'] for _ in messages if _['server_id']]
        if not servers_id:
            return servers
        statuses = [
            'Running',
            'Initializing',
            'Importing',
            'Temporary',
            'Pending terminate',
            'Pending suspend',
        ]
        query = (
                "SELECT server_id, farm_id, farm_roleid farm_roleid, remote_ip, local_ip, platform "
                "FROM servers "
                "WHERE server_id IN ({0}) AND status IN ({1})"
        ).format(str(servers_id)[1:-1], str(statuses)[1:-1])
        servers = self._db.execute(query)

        props = ['scalarizr.ctrl_port', 'scalarizr.key']
        self._db.load_server_properties(servers, props)

        for server in servers:
            if 'scalarizr.ctrl_port' not in server:
                server['scalarizr.ctrl_port'] = 8013
            if 'scalarizr.key' not in server:
                server['scalarizr.key'] = None

        self._db.load_vpc_settings(servers)

        return servers

    def make_request(self, message, server):
        data, headers = self._encrypt(
                server['server_id'],
                server['scalarizr.key'],
                message['message'])
        instances_connection_policy = SCALR_CONFIG.get(server['platform'], {}).get(
                'instances_connection_policy', SCALR_CONFIG['instances_connection_policy'])
        ip, port, proxy_headers = helper.get_szr_ctrl_conn_info(server, instances_connection_policy)
        headers.update(proxy_headers)
        if not ip:
            msg = "Unable to determine ip"
            raise Exception(msg)
        if message['message_format'] == 'json':
            headers['Content-type'] = 'application/json'
        url = 'http://%s:%s/%s' % (ip, port, 'control')
        request = {
            'url': url,
            'data': data,
            'headers': headers,
        }
        return request

    def update_error(self, message):
        query = (
            "UPDATE messages "
            "SET status = 3, handle_attempts = handle_attempts + 1, dtlasthandleattempt = NOW() "
            "WHERE messageid = '{0}'"
        ).format(message['messageid'])
        self._db.execute(query, retries=1)

    def update_not_ok(self, message):
        query = (
                "UPDATE messages "
                "SET status = {0}, handle_attempts = handle_attempts + 1, dtlasthandleattempt = NOW() "
                "WHERE messageid = '{1}'"
        ).format(0 if message['handle_attempts'] < 2 else 3, message['messageid'])
        self._db.execute(query, retries=1)

    def update_ok(self, message):
        if message['message_name'] == 'ExecScript':
            query = "DELETE FROM messages WHERE messageid = '{0}'".format(message['messageid'])
            self._db.execute(query, retries=1)
        else:
            query = (
                    "UPDATE messages "
                    "SET status = 1, message = '', dtlasthandleattempt = NOW() "
                    "WHERE messageid = '{0}'"
            ).format(message['messageid'])
            self._db.execute(query, retries=1)
            if message['event_id']:
                query = (
                        "UPDATE events "
                        "SET msg_sent = msg_sent + 1 "
                        "WHERE event_id = '{0}'"
                ).format(message['event_id'])
                self._db.execute(query, retries=1)

    # async function
    def process_message(self, message, server):
        try:
            status = None
            try:
                request = self.make_request(message, server)
                if not request['url']:
                    msg = "Wrong request: {request}".format(request=request)
                    raise Exception(msg)
            except:
                self.update_error(message)
                raise sys.exc_info()[0], sys.exc_info()[1], sys.exc_info()[2]

            try:
                msg = "Send message: {message_id}, request: {request}"
                msg = msg.format(
                        message_id=message['messageid'],
                        request={'url': request['url'], 'headers': request['headers']})
                LOG.debug(msg)

                r = requests.post(
                        request['url'],
                        data=request['data'],
                        headers=request['headers'],
                        timeout=CONFIG['instances_connection_timeout'])

                if r.status_code != 201:
                    msg = "Bad response code: {code}".format(code=r.status_code)
                    raise Exception(msg)

                msg = "Message: {message_id}, delivery ok"
                msg = msg.format(message_id=message['messageid'])
                LOG.debug(msg)
                status = True
            except:
                msg = "Message: {message_id}, delivery failed, reason: {error}"
                msg = msg.format(message_id=message['messageid'], error=helper.exc_info())
                LOG.warning(msg)
                status = False

            if status:
                self.update_ok(message)
            else:
                self.update_not_ok(message)
        except:
            msg = "Unable to process message: {message_id}, server: {server}, reason: {error}"
            msg = msg.format(message_id=message['messageid'], server=server, error=helper.exc_info())
            LOG.warning(msg)
            raise sys.exc_info()[0], sys.exc_info()[1], sys.exc_info()[2]

    def do_iteration(self):
        messages = self.get_messages()
        if not messages:
            raise NothingToDoError()

        servers = self.get_servers(messages)
        servers_map = dict((server['server_id'], server) for server in servers)

        for message in messages:
            try:
                if message['server_id'] not in servers_map:
                    msg = "Server dosn't exist, set status 3"
                    self.update_error(message)
                    raise Exception(msg)

                server = servers_map[message['server_id']]
                wait_pool()
                POOL.apply_async(self.process_message, (message, server))
            except:
                msg = "Unable to process message: {message_id}, reason: {error}"
                msg = msg.format(message_id=message['messageid'], error=helper.exc_info())
                LOG.warning(msg)

        POOL.join()

    def run(self):
        while True:
            try:
                self.do_iteration()
            except NothingToDoError:
                time.sleep(5)
            except KeyboardInterrupt:
                raise KeyboardInterrupt
            except:
                LOG.exception(helper.exc_info())
                time.sleep(5)
            finally:
                time.sleep(0.2)

    def start(self, daemon=False):
        if daemon:
            super(MsgSender, self).start()
        else:
            self.run()


def configure(args=None):
    global CONFIG, SCALR_CONFIG
    helper.update_config(
            SCALR_CONFIG.get('connections', {}).get('mysql', {}), CONFIG['connections']['mysql'])
    helper.update_config(SCALR_CONFIG.get('msg_sender', {}), CONFIG)
    inst_conn_timeout = SCALR_CONFIG.get('system', {}).get('instances_connection_timeout', None)
    if inst_conn_timeout:
        CONFIG['instances_connection_timeout'] = inst_conn_timeout
    helper.update_config(config_to=CONFIG, args=args)
    helper.validate_config(CONFIG)
    helper.configure_log(
        log_level=CONFIG['verbosity'],
        log_file=CONFIG['log_file'],
        log_size=1024 * 1000)
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
        global SCALR_CONFIG
        SCALR_CONFIG = yaml.safe_load(open(args.config_file))['scalr']
        configure(args)
    except:
        if args.verbosity > 3:
            raise
        else:
            sys.stderr.write('%s\n' % helper.exc_info())
        sys.exit(1)
    try:
        daemon = MsgSender()
        if args.start:
            LOG.info('Start')
            if helper.check_pid(CONFIG['pid_file']):
                LOG.info('Another copy of process already running. Exit')
                sys.exit(0)
            daemon.start(daemon=not args.no_daemon)
        elif args.stop:
            LOG.info('Stop')
            daemon.stop()
        else:
            print 'Usage %s -h' % sys.argv[0]
    except KeyboardInterrupt:
        LOG.critical('KeyboardInterrupt')
        return
    except SystemExit:
        pass
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
