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

import re
import sys
import gzip
import time
import yaml
import socket
import gevent
import logging
import urllib2
import argparse
import requests
import StringIO
import datetime

from gevent.pool import Group as Pool
from pkg_resources import parse_version
from xml.dom import minidom

from scalrpy.util import rpc
from scalrpy.util import cron
from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool
from scalrpy.util import schedule_parser

from scalrpy import __version__

CONFIG = {
    'connections': {
        'mysql': {
            'user': None,
            'pass': None,
            'host': None,
            'port': 3306,
            'name': None,
            'pool_size': 4,
        },
        'szr_upd_client': {
            'port': 8008,
        },
    },
    'repos': {
        'latest': {
            'deb_repo_url': False,
            'rpm_repo_url': False,
            'win_repo_url': False,
        },
        'stable': {
            'deb_repo_url': False,
            'rpm_repo_url': False,
            'win_repo_url': False,
        },
    },
    'chunk_size': 100,
    'pool_size': 10,
    'no_daemon': False,
    'interval': False,
    'instances_connection_timeout': 5,
    'log_file': '/var/log/scalr.szr-upd-service.log',
    'pid_file': '/var/run/scalr.szr-upd-service.pid',
    'verbosity': 1,
}
SCALR_CONFIG = None
LOG = logging.getLogger('ScalrPy')
POOL = Pool()

helper.patch_gevent()


class IterationTimeoutError(Exception):
    pass


class SzrUpdClient(rpc.HttpServiceProxy):

    def __init__(self, host, port, key, headers=None):
        endpoint = 'http://%s:%s' % (host, port)
        security = rpc.Security(key)
        super(SzrUpdClient, self).__init__(endpoint, security=security, headers=headers)


class SzrUpdService(cron.Cron):

    def __init__(self):
        super(SzrUpdService, self).__init__(CONFIG['pid_file'])
        self._db = dbmanager.ScalrDB(CONFIG['connections']['mysql'], pool_size=1)
        self.iteration_timestamp = None
        self.szr_repo_ver = None

    def get_szr_ver_from_repo(self):
        out = {}
        for repo_type in CONFIG['repos']:
            out[repo_type] = {}

            # deb
            if CONFIG['repos'][repo_type]['deb_repo_url']:
                deb_repo_url = '/'.join(CONFIG['repos'][repo_type]['deb_repo_url'].strip().split())
                r = requests.get('%s/Packages' % deb_repo_url)
                r.raise_for_status()
                out[repo_type][CONFIG['repos'][repo_type]['deb_repo_url']] = re.findall(
                        'Package: scalarizr\n.*?Version:(.*?)\n.*?\n\n',
                        r.text,
                        re.DOTALL)[0].strip()

            # rpm
            if CONFIG['repos'][repo_type]['rpm_repo_url']:
                rpm_repo_url_template = CONFIG['repos'][repo_type]['rpm_repo_url'].strip()
                for release in ['5', '6']:
                    rpm_repo_url = rpm_repo_url_template.replace('$releasever', release)
                    rpm_repo_url = rpm_repo_url.replace('$basearch', 'x86_64')
                    r = requests.get('%s/repodata/primary.xml.gz' % rpm_repo_url)
                    s = StringIO.StringIO(r.content)
                    f = gzip.GzipFile(fileobj=s, mode='r')
                    f.seek(0)
                    xml = minidom.parse(f)
                    out[repo_type][CONFIG['repos'][repo_type]['rpm_repo_url']] = re.findall(
                            '<package type="rpm"><name>scalarizr-base</name>.*?ver="(.*?)".*?</package>\n',
                            xml.toxml(),
                            re.DOTALL)[0].strip()

            # win
            if CONFIG['repos'][repo_type]['win_repo_url']:
                win_repo_url = CONFIG['repos'][repo_type]['win_repo_url'].strip()
                r = requests.get('%s/x86_64/index' % win_repo_url)
                out[repo_type][CONFIG['repos'][repo_type]['win_repo_url']] = re.findall(
                        'scalarizr *scalarizr_(.*).x86_64.exe*\n',
                        r.text,
                        re.DOTALL)[0].split('-')[0]

        return out

    def _get_db_servers(self):
        query = (
                "SELECT server_id, farm_id, farm_roleid, "
                "remote_ip, local_ip, platform "
                "FROM servers "
                "WHERE status IN ('Running') "
                "ORDER BY server_id")
        return self._db.execute_with_limit(query, 200, max_limit=CONFIG['chunk_size'], retries=1)

    def get_servers_for_update(self):
        for servers in self._get_db_servers():
            props = ['scalarizr.key', 'scalarizr.updc_port', 'scalarizr.version']
            self._db.load_server_properties(servers, props)

            farms = [{'id': __} for __ in set(_['farm_id'] for _ in servers)]
            props = ['szr.upd.schedule']
            self._db.load_farm_settings(farms, props)
            farms_map = dict((_['id'], _) for _ in farms)

            farms_roles = [{'id': __} for __ in set(_['farm_roleid'] for _ in servers)]
            props = ['base.upd.schedule', 'user-data.scm_branch']
            self._db.load_farm_role_settings(farms_roles, props)
            farms_roles_map = dict((_['id'], _) for _ in farms_roles)

            servers_for_update = []
            for server in servers:
                if 'scalarizr.key' not in server:
                    msg = "Server: {0}, reason: Missing scalarizr key".format(server['server_id'])
                    LOG.warning(msg)
                    continue
                if 'scalarizr.updc_port' not in server:
                    server['scalarizr.updc_port'] = CONFIG['connections']['szr_upd_client']['port']
                try:
                    version = server['scalarizr.version']
                    if not version or parse_version(version) < parse_version('2.7.7'):
                        continue
                    schedule = farms_roles_map.get(
                            server['farm_roleid'], {}).get('base.upd.schedule', None)
                    if not schedule:
                        schedule = farms_map.get(
                                server['farm_id'], {}).get('szr.upd.schedule', '* * *')

                    next_update_dt = time.strftime('%Y-%m-%d %H:%M:%S', self._scheduled_on(schedule))
                    query = (
                            """INSERT INTO farm_role_settings """
                            """(farm_roleid, name, value) """
                            """VALUES ({0}, 'scheduled_on', '{1}') """
                            """ON DUPLICATE KEY UPDATE value='{1}'"""
                    ).format(server['farm_roleid'], next_update_dt)
                    msg = "Set next update datetime for server: {0} to: {1}"
                    msg = msg.format(server['server_id'], next_update_dt)
                    LOG.debug(msg)
                    try:
                        self._db.execute(query, retries=1)
                    except:
                        msg = 'Unable to set next update datetime for server: {0}, reason: {1}'
                        msg = msg.format(server['server_id'], helper.exc_info())
                        LOG.warning(msg)

                    if not schedule_parser.Schedule(schedule).intime():
                        continue
                    servers_for_update.append(server)
                except:
                    msg = "Server: {0}, reason: {1}".format(server['server_id'], helper.exc_info())
                    LOG.warning(msg)
                    continue

            if not servers_for_update:
                continue

            self._db.load_vpc_settings(servers_for_update)
            yield servers_for_update

    def update_server(self, server):
        try:
            key = cryptotool.decrypt_key(server['scalarizr.key'])
            headers = {'X-Server-Id': server['server_id']}
            instances_connection_policy = SCALR_CONFIG.get(server['platform'], {}).get(
                    'instances_connection_policy', SCALR_CONFIG['instances_connection_policy'])
            ip, port, proxy_headers = helper.get_szr_updc_conn_info(server, instances_connection_policy)
            headers.update(proxy_headers)
            szr_upd_client = SzrUpdClient(ip, port, key, headers=headers)
            timeout = CONFIG['instances_connection_timeout']
            try:
                status = szr_upd_client.status(cached=True, timeout=timeout)
            except:
                msg = 'Unable to get update client status, reason: {0}'.format(helper.exc_info())
                raise Exception(msg)
            szr_ver_repo = self.szr_ver_repo[status['repository']][status['repo_url']]
            if parse_version(server['scalarizr.version']) >= parse_version(szr_ver_repo):
                return
            msg = "Trying to update server: {0}, version: {1}".format(
                    server['server_id'], server['scalarizr.version'])
            LOG.debug(msg)
            try:
                result_id = szr_upd_client.update(async=True, timeout=timeout)
            except:
                msg = 'Unable to update, reason: {0}'.format(helper_exc_info())
                raise Exception(msg)
            LOG.debug("Server: {0}, result: {1}".format(server['server_id'], result_id))
        except:
            msg = "Server failed: {0}, reason: {1}".format(server['server_id'], helper.exc_info())
            LOG.warning(msg)

    def _scheduled_on(self, schedule):
        dt = datetime.datetime.utcnow()
        delta = datetime.timedelta(hours=1)
        for _ in xrange(0, 24*31*365):
            dt = dt + delta
            if schedule_parser.Schedule(schedule).intime(now=dt.timetuple()):
                return dt.replace(minute=0, second=0, microsecond=0).timetuple()

    @helper.greenlet
    def do_iteration(self):
        try:
            self.iteration_timestamp = time.time()
            self.szr_ver_repo = self.get_szr_ver_from_repo()
            for servers_for_update in self.get_servers_for_update():
                for server in servers_for_update:
                    while len(POOL) >= CONFIG['pool_size']:
                        gevent.sleep(0.3)
                    POOL.apply_async(self.update_server, (server,))
                    gevent.sleep(0)  # force switch
        except:
            LOG.error("Iteration failed, reason: %s" % helper.exc_info())
        POOL.join()

    def _run(self):
        while True:
            LOG.info('Start iteration')
            try:
                g = self.do_iteration()
                if CONFIG['interval']:
                    timeout = self.iteration_timestamp + CONFIG['interval'] - time.time()
                else:
                    timeout = 60 * 20
                try:
                    g.get(timeout=timeout)
                except gevent.Timeout:
                    raise IterationTimeoutError(timeout)
                finally:
                    if not g.ready():
                        g.kill()
            except KeyboardInterrupt:
                raise KeyboardInterrupt
            except:
                LOG.error(helper.exc_info())
                POOL.kill()
            finally:
                msg= 'End iteration: {0:.1f} seconds'.format(time.time() - self.iteration_timestamp)
                LOG.info(msg)
                if CONFIG['interval']:
                    sleep_time = self.iteration_timestamp + CONFIG['interval'] - time.time()
                    time.sleep(sleep_time)
                else:
                    break


def configure(args=None):
    global CONFIG
    helper.update_config(
            SCALR_CONFIG.get('connections', {}).get('mysql', {}), CONFIG['connections']['mysql'])
    helper.update_config(SCALR_CONFIG.get('scalarizr_update', {}).get('service', {}), CONFIG)
    inst_conn_timeout = SCALR_CONFIG.get('system', {}).get('instances_connection_timeout', None)
    if inst_conn_timeout:
        CONFIG['instances_connection_timeout'] = inst_conn_timeout
    helper.update_config(SCALR_CONFIG['scalarizr_update']['repos'], CONFIG['repos'])
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
    parser.add_argument('-i', '--interval', type=int, default=None,
            help="execution interval in seconds. Default is once")
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
        app = SzrUpdService()
        if args.start:
            if helper.check_pid(CONFIG['pid_file']):
                msg = "Application with pid file '%s' already running. Exit" % CONFIG['pid_file']
                LOG.info(msg)
                sys.exit(0)
            if not args.no_daemon:
                helper.daemonize()
            app.start()
        elif args.stop:
            app.stop()
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
