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
import pymysql
import datetime
import requests
import requests.exceptions
import requests.packages.urllib3.exceptions

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool
from scalrpy.util import application

from scalrpy import LOG
from scalrpy import exceptions


helper.patch_gevent()


app = None


class DBQueueEvent(application.ScalrIterationApplication):

    nothing_todo_sleep = 5

    def __init__(self, argv=None):
        self.description = "Scalr queue event application"

        options = (
            """  --disable-ssl-verification        """
            """disable ssl certificate verification""")
        self.add_options(options)

        super(DBQueueEvent, self).__init__(argv=argv)

        self.config.update({
            'pool_size': 100,
            'retry_interval': 180,
            '_scalr_mail_service_url': 'https://my.scalr.com/webhook_mail.php',
        })
        self._db = None
        self._pool = None

        self.iteration_timeout = 180

        self.https_session = requests.Session()
        self.https_session.mount('https://', helper.HttpsAdapter())

        self.proxy = None

    def configure(self):
        helper.update_config(
            self.scalr_config.get('dbqueue_event', {}), self.config)
        helper.validate_config(self.config)
        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

        self._db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self._pool = helper.GPool(pool_size=self.config['pool_size'])

        proxy_settings = helper.get_proxy_settings(self.scalr_config, 'system.webhooks')
        if proxy_settings:
            self.proxy = {
                'http': proxy_settings['url'],
                'https': proxy_settings['url']
            }

    def get_webhooks(self):
        query = (
            "SELECT HEX(wh.history_id) as history_id, HEX(wh.webhook_id) as webhook_id, "
            "HEX(wh.endpoint_id) as endpoint_id, wh.payload, wh.handle_attempts, "
            "wh.dtlasthandleattempt, wh.error_msg, wh.event_id, we.url, "
            "we.security_key, wc.timeout, wc.attempts "
            "FROM webhook_history wh "
            "JOIN webhook_endpoints we ON wh.endpoint_id=we.endpoint_id "
            "LEFT JOIN webhook_configs wc ON wh.webhook_id=wc.webhook_id "
            "WHERE wh.status=0 "
            "LIMIT 250"
        )
        results = self._db.execute(query)
        for result in results:
            if result['attempts'] is None:
                result['attempts'] = 3
            if result['timeout'] is None:
                result['timeout'] = 3
            if result['dtlasthandleattempt'] is None:
                result['dtlasthandleattempt'] = datetime.datetime.utcnow()
            if result['handle_attempts'] is None:
                result['handle_attempts'] = 0
            if result['error_msg'] is None:
                result['error_msg'] = ''
        return results

    def post_webhook(self, webhook, headers=None):
        if headers is None:
            signature, date = cryptotool.sign(
                webhook['payload'], webhook['security_key'], version=2)
            headers = {
                'Date': date,
                'X-Signature': signature,
                'X-Scalr-Webhook-Id': webhook['history_id'],
                'Content-type': 'application/json',
            }

        if webhook['url'] == 'SCALR_MAIL_SERVICE':
            url = self.config['_scalr_mail_service_url']
        else:
            url = webhook['url']

        webhook['dtlasthandleattempt'] = str(datetime.datetime.utcnow().replace(microsecond=0))

        resp = self.https_session.post(
            url, data=webhook['payload'], headers=headers, timeout=webhook['timeout'],
            allow_redirects=False, verify=not self.args['--disable-ssl-verification'],
            proxies=self.proxy)

        if resp.status_code > 205:
            webhook['error_msg'] = resp.text.encode('ascii', 'replace')

        return resp

    def update_webhook(self, webhook):
        webhook['response_code'] = webhook.get('response_code', 'NULL')
        webhook['error_msg'] = pymysql.escape_string(webhook['error_msg'])[0:255]
        while True:
            try:
                query = (
                    """UPDATE webhook_history """
                    """SET status={status}, response_code={response_code}, """
                    """error_msg="{error_msg}", handle_attempts={handle_attempts}, """
                    """dtlasthandleattempt='{dtlasthandleattempt}' """
                    """WHERE history_id=UNHEX('{history_id}')"""
                ).format(**webhook)
                self._db.execute(query)
                break
            except KeyboardInterrupt:
                raise
            except:
                msg = "Webhook update failed, history_id: {0}, reason: {1}"
                msg = msg.format(webhook['history_id'], helper.exc_info())
                LOG.warning(msg)
                time.sleep(5)

    def update_event(self, webhook):
        try:
            assert webhook['history_id'], 'event_id is null'
            if webhook['status'] == 1:
                query = (
                    """UPDATE events """
                    """SET wh_completed=wh_completed+1 """
                    """WHERE events.event_id='{event_id}'"""
                ).format(**webhook)
            elif webhook['status'] == 2:
                query = (
                    """UPDATE events """
                    """SET wh_failed=wh_failed+1 """
                    """WHERE events.event_id='{event_id}'"""
                ).format(**webhook)
            else:
                return
            self._db.execute(query, retries=3)
        except:
            msg = "Events update failed, history_id: {0}, reason: {1}"
            msg = msg.format(webhook['history_id'], helper.exc_info())
            LOG.warning(msg)

    def do_iteration(self):
        webhooks = self.get_webhooks()

        webhooks_to_post = list()
        for webhook in webhooks:
            attempt = int(webhook['handle_attempts']) + 1
            delta = datetime.timedelta(seconds=((attempt - 1) * self.config['retry_interval']))
            if webhook['dtlasthandleattempt'] + delta <= datetime.datetime.utcnow():
                webhooks_to_post.append(webhook)

        if not webhooks_to_post:
            time.sleep(self.nothing_todo_sleep)
            return

        for webhook in webhooks_to_post:
            webhook['handle_attempts'] += 1
            self._pool.wait()
            webhook['async_result'] = self._pool.apply_async(self.post_webhook, (webhook,))

        webhooks_to_iterate = webhooks_to_post

        # loop while there are webhooks to post or redirect
        while webhooks_to_iterate:
            for webhook in webhooks_to_iterate[:]:
                try:
                    resp = webhook['async_result'].get(timeout=60)
                    webhook['response_code'] = resp.status_code
                    if webhook['response_code'] <= 205:
                        webhook['status'] = 1
                        msg = 'Webhook: {history_id}, url: {url} Ok'.format(**webhook)
                        LOG.debug(msg)
                    elif resp.is_redirect:
                        webhook.setdefault('num_redirects', 0)
                        webhook['num_redirects'] += 1
                        if webhook['num_redirects'] > 5:
                            raise requests.exceptions.TooManyRedirects()
                        else:
                            # do redirect
                            webhook['url'] = resp.headers['location']
                            msg = 'Webhook: {history_id}, redirect to {url}'.format(**webhook)
                            LOG.debug(msg)
                            self._pool.wait()
                            webhook['async_result'] = self._pool.apply_async(
                                self.post_webhook,
                                (webhook,),
                                {'headers': {}})
                            continue
                    else:
                        if 500 <= webhook['response_code'] < 600:
                            if webhook['handle_attempts'] < webhook['attempts']:
                                webhook['status'] = 0
                            else:
                                webhook['status'] = 2
                        else:
                            webhook['status'] = 2
                        msg = 'Webhook: {history_id}, url: {url}, response code: {response_code}'
                        msg = msg.format(**webhook)
                        LOG.warning(msg)
                except:
                    self._handle_webhook_exception(webhook)

                self._pool.wait()
                self._pool.apply_async(self.update_webhook, (webhook,))
                if webhook['status'] in [1, 2]:
                    self._pool.wait()
                    self._pool.apply_async(self.update_event, (webhook,))
                webhooks_to_iterate.remove(webhook)

        self._pool.join()

    def _handle_webhook_exception(self, webhook):
        exc = sys.exc_info()[1]
        if isinstance(exc, (
                requests.exceptions.Timeout,
                requests.exceptions.ProxyError,
                requests.exceptions.ConnectionError)):
            if webhook['handle_attempts'] < webhook['attempts']:
                webhook['status'] = 0
            else:
                webhook['status'] = 2
            webhook['error_msg'] = str(sys.exc_info()[0].__name__)
            msg = "Unable to process webhook: {0}, reason: {1}"
            msg = msg.format(webhook['history_id'], helper.exc_info())
            LOG.warning(msg)
        elif isinstance(exc, (
                requests.exceptions.RequestException,
                requests.packages.urllib3.exceptions.HTTPError,
                requests.packages.urllib3.exceptions.HTTPWarning)):
            webhook['status'] = 2
            webhook['error_msg'] = str(sys.exc_info()[0].__name__)
            msg = "Unable to process webhook: {0}, reason: {1}"
            msg = msg.format(webhook['history_id'], helper.exc_info())
            LOG.warning(msg)
        else:
            webhook['status'] = 2
            webhook['error_msg'] = 'Internal error'
            msg = "Unable to process webhook: {0}, reason: {1}"
            msg = msg.format(webhook['history_id'], helper.exc_info())
            LOG.error(msg)

    def on_iteration_error(self):
        self._pool.kill()


def main():
    global app
    app = DBQueueEvent()
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
