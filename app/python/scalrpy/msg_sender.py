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

import time
import socket
import requests
import multiprocessing

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool
from scalrpy.util import application

from scalrpy import LOG
from scalrpy import exceptions


helper.patch_gevent()


app = None

debug_rate_counter = 0
debug_rate_timestamp = time.time()


class MsgSender(application.ScalrIterationApplication):

    nothing_todo_sleep = 5

    def __init__(self, argv=None):
        self.description = "Scalr messaging application"

        super(MsgSender, self).__init__(argv=argv)

        self.config.update({
                'cratio': 120,
                'pool_size': 250,
                'interval': 1,
                'workers': 1,
        })
        self.iteration_timeout = 180

        self._db = None
        self._pool = None
        self._processing_messages = set()

        self._limit = 1000
        self._max_processing_messages = 1000

    def configure(self):
        helper.update_config(
            self.scalr_config.get('msg_sender', {}), self.config)
        helper.validate_config(self.config)
        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

        self._db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self._pool = helper.GPool(pool_size=self.config['pool_size'])
        self._limit = self._max_processing_messages = min(1000, self.config['pool_size'])

    def _encrypt(self, server_id, crypto_key, data, headers=None):
        assert server_id, 'server_id'
        assert crypto_key, 'scalarizr.key'
        assert data, 'data to encrypt'
        crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)
        data = cryptotool.encrypt_scalarizr(crypto_algo, data, cryptotool.decrypt_key(crypto_key))
        headers = headers or dict()
        headers['X-Signature'], headers['Date'] = cryptotool.sign(data, crypto_key)
        headers['X-Server-Id'] = server_id
        return data, headers

    def get_messages(self):
        exclude = str(list(self._processing_messages))[1:-1]
        if exclude:
            exclude = 'AND m.messageid NOT IN ({})'.format(exclude)
        query = (
            "SELECT m.messageid message_id, m.server_id message_server_id, m.event_id, "
            "m.message_format, m.handle_attempts, m.message, m.message_name, m.status, "
            "s.server_id, s.farm_id, s.farm_roleid farm_role_id, s.remote_ip, s.local_ip, "
            "s.platform, s.status server_status "
            "FROM messages m "
            "LEFT JOIN servers s ON m.server_id = s.server_id "
            "WHERE m.type = 'out' "
            "AND m.status = 0 "
            "AND m.messageid IS NOT NULL "
            "AND m.messageid != '' "
            "AND m.message_version = 2 "
            "AND m.dtlasthandleattempt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL m.handle_attempts*{cratio} SECOND) "
            "{exclude} "
            "ORDER BY m.dtadded ASC "
            "LIMIT {limit}"
        ).format(cratio=self.config['cratio'], exclude=exclude, limit=self._limit)
        return self._db.execute(query)

    def load_servers_data(self, messages):
        props = ['scalarizr.ctrl_port', 'scalarizr.key']
        self._db.load_server_properties(messages, props)
        for message in messages:
            if 'scalarizr.ctrl_port' not in message:
                message['scalarizr.ctrl_port'] = 8013
            if 'scalarizr.key' not in message:
                message['scalarizr.key'] = None
        self._db.load_vpc_settings(messages)
        return message

    def make_request(self, message):
        data, headers = self._encrypt(
            message['server_id'],
            message['scalarizr.key'],
            message['message'])
        instances_connection_policy = self.scalr_config.get(message['platform'], {}).get(
            'instances_connection_policy', self.scalr_config['instances_connection_policy'])
        ip, port, proxy_headers = helper.get_szr_ctrl_conn_info(
            message, instances_connection_policy)
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
        try:
            if message['status'] == 1:
                if message['event_id']:
                    query = (
                        "UPDATE events "
                        "SET msg_sent = msg_sent + 1 "
                        "WHERE event_id = '{event_id}'"
                    ).format(**message)
                    self._db.execute(query, retries=1)
                if message['message_name'] == 'ExecScript':
                    query = "DELETE FROM messages WHERE messageid = '{message_id}'".format(**message)
                    self._db.execute(query, retries=1)
                    return
                query = (
                    "UPDATE messages "
                    "SET status=1, message='', handle_attempts=handle_attempts+1, "
                    " dtlasthandleattempt=UTC_TIMESTAMP() "
                    "WHERE messageid='{message_id}'").format(**message)
            else:
                query = (
                    "UPDATE messages "
                    "SET status={status}, handle_attempts=handle_attempts+1, "
                    "dtlasthandleattempt=UTC_TIMESTAMP() "
                    "WHERE messageid='{message_id}'").format(**message)
            self._db.execute(query, retries=1)
        finally:
            global debug_rate_counter
            if message['message_id'] in self._processing_messages:
                debug_rate_counter += 1
                self._processing_messages.remove(message['message_id'])

    def process_message(self, message):
        try:
            try:
                request = self.make_request(message)
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
                message_id=message['message_id'],
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
            msg = msg.format(**message)
            LOG.debug(msg)
        except:
            if message['status'] == 0 and int(message['handle_attempts']) >= 2:
                message['status'] = 3
            msg = "Delivery failed, message: {message}"
            message['scalarizr.key'] = '******'
            message['message'] = '******'
            msg = msg.format(message=message)
            helper.handle_error(message=msg, level='warning')
        self.update(message)

    def do_iteration(self):
        global debug_rate_counter
        global debug_rate_timestamp
        debug_rate_time = time.time() - debug_rate_timestamp
        rate = round(debug_rate_counter / debug_rate_time, 2)
        LOG.info('Average rate: %s, %s' % (rate, rate * 60))
        debug_rate_counter = 0
        debug_rate_timestamp = time.time()

        while len(self._processing_messages) > self._max_processing_messages:
            LOG.warning('Reached the limit of simultaneously processed messages')
            time.sleep(1)

        messages = self.get_messages()
        messages = [m for m in messages if m['message_id'] not in self._processing_messages]

        num, idx = int(self.config['workers']), int(self.config['index'])

        def filter_messages(message):
            if message.get('server_id') and message.get('farm_id'):
                return int(message['farm_id']) % num == idx - 1
            else:
                return idx == 1

        if num > 1:
            messages = filter(filter_messages, messages)

        if not messages:
            time.sleep(self.nothing_todo_sleep)
            return

        self.load_servers_data(messages)

        server_statuses = [
            'Running',
            'Initializing',
            'Importing',
            'Temporary',
            'Pending terminate',
            'Pending suspend',
        ]

        for message in messages:
            try:
                self._processing_messages.add(message['message_id'])
                if message.get('server_id') is None or \
                            message['server_status'] not in server_statuses or (
                            message['server_status'] in ('Pending terminate', 'Pending suspend') and
                            int(message['handle_attempts']) >= 1):
                    msg = (
                        "Server {message_server_id} doesn't exist or not in right status, "
                        "set message {message_id} status to 3").format(**message)
                    LOG.warning(msg)
                    message['status'] = 3
                    self._pool.wait()
                    self._pool.apply_async(self.update, (message,))
                else:
                    self._pool.wait()
                    self._pool.apply_async(self.process_message, (message,))
            except:
                msg = "Unable to process message: {message_id}, reason: {error}"
                msg = msg.format(message_id=message['message_id'], error=helper.exc_info())
                LOG.warning(msg)

        LOG.info('Messages still in processing: %s' % len(self._processing_messages))

    def on_iteration_error(self):
        self._pool.kill()
        self._processing_messages = set()

    def __call__(self):
        workers = []
        for worker_idx in range(1, self.config['workers'] + 1):
            self.config['index'] = worker_idx
            worker = multiprocessing.Process(target=super(MsgSender, self).__call__)
            worker.start()
            workers.append(worker)
        for worker in workers:
            worker.join()


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
