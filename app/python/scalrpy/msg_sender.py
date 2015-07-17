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

import socket
import requests

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool
from scalrpy.util import exceptions
from scalrpy.util import application

from scalrpy import LOG


helper.patch_gevent()


app = None


class MsgSender(application.ScalrIterationApplication):

    def __init__(self, argv=None):
        self.description = "Scalr messaging application"

        super(MsgSender, self).__init__(argv=argv)

        self.config.update({'cratio': 120, 'pool_size': 100})
        self.iteration_timeout = 120

        self._db = None
        self._pool = None

    def configure(self):
        helper.update_config(
            self.scalr_config.get('msg_sender', {}), self.config)
        helper.validate_config(self.config)
        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

        self._db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self._pool = helper.GPool(pool_size=self.config['pool_size'])

    def _encrypt(self, server_id, crypto_key, data, headers=None):
        assert server_id, 'server_id'
        assert crypto_key, 'scalarizr.key'
        assert data, 'data to encrypt'
        crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)
        data = cryptotool.encrypt_scalarizr(crypto_algo, data, cryptotool.decrypt_key(crypto_key))
        headers = headers or dict()
        headers['X-Signature'], headers['Date'] = cryptotool.sign(data, crypto_key)
        headers['X-Server-Id'] = server_id
        msg = "Server: {0}, key: {1} ... {2}".format(server_id, crypto_key[0:5], crypto_key[-5:])
        LOG.debug(msg)
        return data, headers

    def get_messages(self):
        query = (
            "SELECT messageid, server_id, event_id, message_format, "
            "handle_attempts ,message, message_name, status "
            "FROM messages "
            "WHERE type = 'out' "
            "AND status = 0 "
            "AND messageid IS NOT NULL "
            "AND messageid != '' "
            "AND message_version = 2 "
            "AND UNIX_TIMESTAMP(dtlasthandleattempt)+handle_attempts*{cratio}<UNIX_TIMESTAMP() "
            "ORDER BY dtadded ASC "
            "LIMIT 250"
        ).format(cratio=self.config['cratio'])
        return self._db.execute(query)

    def get_servers(self, messages):
        servers = ()
        servers_id = list(set([_['server_id'] for _ in messages if _['server_id']]))
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
            "SELECT server_id, farm_id, farm_roleid, remote_ip, local_ip, platform "
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
        instances_connection_policy = self.scalr_config.get(server['platform'], {}).get(
            'instances_connection_policy', self.scalr_config['instances_connection_policy'])
        ip, port, proxy_headers = helper.get_szr_ctrl_conn_info(
            server, instances_connection_policy)
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

    def update(self, message):
        if message['status'] == 1:
            if message['event_id']:
                query = (
                    "UPDATE events "
                    "SET msg_sent = msg_sent + 1 "
                    "WHERE event_id = '{0}'"
                ).format(message['event_id'])
                self._db.execute(query, retries=1)
            if message['message_name'] == 'ExecScript':
                query = "DELETE FROM messages WHERE messageid = '{0}'".format(message['messageid'])
                self._db.execute(query, retries=1)
                return
        query = (
            "UPDATE messages "
            "SET status = {0}, handle_attempts = handle_attempts + 1, dtlasthandleattempt = NOW() "
            "WHERE messageid = '{1}'"
        ).format(message['status'], message['messageid'])
        self._db.execute(query, retries=1)

    def process_message(self, message, server):
        try:
            try:
                request = self.make_request(message, server)
            except:
                message['status'] = 3
                msg = "Make request failed, reason: {error}".format(error=helper.exc_info())
                raise Exception(msg)
            if not request['url']:
                message['status'] = 3
                msg = "Wrong request: {request}".format(request=request)
                raise Exception(msg)
            msg = "Send message: {message_id}, request: {request}"
            msg = msg.format(
                message_id=message['messageid'],
                request={'url': request['url'], 'headers': request['headers']})
            LOG.debug(msg)

            r = requests.post(
                request['url'],
                data=request['data'],
                headers=request['headers'],
                timeout=self.config['instances_connection_timeout'])

            if r.status_code != 201:
                msg = "Bad response code: {code}".format(code=r.status_code)
                raise Exception(msg)
            message['status'] = 1
            msg = "Delivery Ok, message: {message_id}"
            msg = msg.format(message_id=message['messageid'])
            LOG.debug(msg)
        except:
            if message['status'] == 0 and int(message['handle_attempts']) >= 2:
                message['status'] = 3
            msg = "Delivery failed, message: {message_id}, server: {server}, reason: {error}"
            server['scalarizr.key'] = '******'
            msg = msg.format(
                message_id=message['messageid'], server=server, error=helper.exc_info())
            LOG.warning(msg)
        self.update(message)

    def do_iteration(self):
        messages = self.get_messages()
        if not messages:
            raise exceptions.NothingToDoError()

        servers = self.get_servers(messages)
        servers_map = dict((server['server_id'], server) for server in servers)

        for message in messages:
            try:
                if message['server_id'] not in servers_map:
                    msg = (
                        "Server '{server_id}' doesn't exist or not in right status, set message "
                        "status to 3"
                    ).format(server_id=message['server_id'])
                    LOG.warning(msg)
                    message['status'] = 3
                    self._pool.wait()
                    self._pool.apply_async(self.update, (message,))
                else:
                    server = servers_map[message['server_id']]
                    self._pool.wait()
                    self._pool.apply_async(self.process_message, (message, server))
            except:
                msg = "Unable to process message: {message_id}, reason: {error}"
                msg = msg.format(message_id=message['messageid'], error=helper.exc_info())
                LOG.warning(msg)
        self._pool.join()

    def on_iteration_error(self):
        self._pool.kill()


def main():
    global app
    app = MsgSender()
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
