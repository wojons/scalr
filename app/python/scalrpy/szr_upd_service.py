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

import re
import gzip
import socket
import gevent
import pymysql
import requests
import StringIO
import datetime

from pkg_resources import parse_version
from xml.dom import minidom

from scalrpy.util import rpc
from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool
from scalrpy.util import application
from scalrpy.util import schedule_parser

from scalrpy import LOG
from scalrpy import exceptions


helper.patch_gevent()


app = None

eol_os = ['ubuntu-10-04']


class SzrUpdClient(rpc.HttpServiceProxy):

    def __init__(self, host, port, key, headers=None):
        endpoint = 'http://%s:%s' % (host, port)
        security = rpc.Security(key)
        super(SzrUpdClient, self).__init__(endpoint, security=security, headers=headers)


class SzrUpdService(application.ScalrIterationApplication):

    def __init__(self, argv=None):
        self.description = "Scalr scalarizr update service"

        super(SzrUpdService, self).__init__(argv=argv, interval=True)

        self.config.update({
            'interval': 600,
            'chunk_size': 100,
            'pool_size': 100,
        })

        self._db = None
        self._pool = None

    def configure(self):
        helper.update_config(
            self.scalr_config.get('scalarizr_update', {}).get('service', {}), self.config)
        helper.validate_config(self.config)
        if self.config['interval']:
            self.iteration_timeout = int(self.config['interval'])
        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

        self._db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self._pool = helper.GPool(pool_size=self.config['pool_size'])

    def clear_cache(self):
        if hasattr(self.get_szr_ver_from_repo.im_func, 'cache'):
            delattr(self.get_szr_ver_from_repo.im_func, 'cache')
        if hasattr(self.get_szr_ver_from_repo.im_func, 'devel_cache'):
            delattr(self.get_szr_ver_from_repo.im_func, 'devel_cache')

    deb_pattern = re.compile(r'Package: scalarizr\n.*?Version:([ A-Za-z0-9.]*)-?.*\n.*?', re.DOTALL)

    def ver_from_deb_repo(self, repo, branch=None):
        out = {}
        deb_repo_url_template = repo['deb_repo_url']
        if deb_repo_url_template:
            deb_repo_url_template = deb_repo_url_template.strip()
            if branch:
                deb_repo_url_template = deb_repo_url_template % branch
            deb_repo_url = '/'.join(deb_repo_url_template.split())
            url = os.path.join(deb_repo_url, 'Packages')
            try:
                r = requests.get(url)
                r.raise_for_status()
                assert r.text, 'Empty Packages file'
                out[deb_repo_url_template] = self.deb_pattern.findall(r.text)[0].strip()
            except (requests.exceptions.HTTPError, requests.exceptions.InvalidSchema):
                msg = 'Deb repository {0} failed, file not found: {1}'
                msg = msg.format(repo['deb_repo_url'], url)
                LOG.warning(msg)
        return out

    rpm_pattern_1 = re.compile(
            r'<package type="rpm">[\n ]*<name>scalarizr-base</name>.*?ver="([A-Za-z0-9.]*)-?.*".*?</package>',
            re.DOTALL)
    rpm_pattern_2 = re.compile(
            r'<package type="rpm">[\n ]*<name>scalarizr</name>.*ver="([ A-Za-z0-9.]*)-?.*".*</package>',
            re.DOTALL)

    def ver_from_rpm_repo(self, repo, branch=None):
        out = {}
        rpm_repo_url_template = repo['rpm_repo_url']
        if rpm_repo_url_template:
            rpm_repo_url_template = rpm_repo_url_template.strip()
            if branch:
                rpm_repo_url_template = rpm_repo_url_template % branch
            for release in ['5', '6', '7']:
                rpm_repo_url = rpm_repo_url_template.replace('$releasever', release)
                rpm_repo_url = rpm_repo_url.replace('$basearch', 'x86_64')
                url = os.path.join(rpm_repo_url, 'repodata/primary.xml.gz')
                try:
                    r = requests.get(url)
                    r.raise_for_status()
                    assert r.text, 'Empty primary.xml file'
                    s = StringIO.StringIO(r.content)
                    f = gzip.GzipFile(fileobj=s, mode='r')
                    f.seek(0)
                    xml = minidom.parse(f)
                    try:
                        out[rpm_repo_url_template] = self.rpm_pattern_1.findall(xml.toxml())[0].strip()
                    except:
                        out[rpm_repo_url_template] = self.rpm_pattern_2.findall(xml.toxml())[0].strip()
                except (requests.exceptions.HTTPError, requests.exceptions.InvalidSchema):
                    msg = 'RPM repository {0} failed, file not found: {1}'
                    msg = msg.format(repo['rpm_repo_url'], url)
                    LOG.warning(msg)
            return out

    win_pattern = re.compile(r'scalarizr *scalarizr_(.*).exe*', re.DOTALL)

    def ver_from_win_repo(self, repo, branch=None):
        out = {}
        win_repo_url_template = repo['win_repo_url']
        if win_repo_url_template:
            win_repo_url_template = win_repo_url_template.strip()
            if branch:
                win_repo_url = win_repo_url_template % branch
            else:
                win_repo_url = win_repo_url_template
            url = os.path.join(win_repo_url, 'x86_64/index')
            try:
                r = requests.get(url)
                r.raise_for_status()
                assert r.text, 'Empty index file'
                out[win_repo_url] = self.win_pattern.findall(r.text)[0].split('-')[0]
            except (requests.exceptions.HTTPError, requests.exceptions.InvalidSchema):
                msg = 'Win repository {0} failed, file not found: {1}'
                msg = msg.format(repo['win_repo_url'], url)
                LOG.warning(msg)
        return out

    def get_szr_ver_from_repo(self, devel_branch=None, force=False):
        out = {}

        if devel_branch:
            if 'devel_repos' not in self.scalr_config['scalarizr_update']:
                return out
            if not force:
                try:
                    return self.get_szr_ver_from_repo.im_func.devel_cache[devel_branch]
                except AttributeError:
                    self.get_szr_ver_from_repo.im_func.devel_cache = {}
                except KeyError:
                    pass
            norm_branch = devel_branch.replace('/', '-').replace('_', '-')
            repos = self.scalr_config['scalarizr_update']['devel_repos']
        else:
            if not force:
                try:
                    return self.get_szr_ver_from_repo.im_func.cache
                except AttributeError:
                    pass
            norm_branch = None
            repos = self.scalr_config['scalarizr_update']['repos']

        for repo_type, repo in repos.iteritems():
            for k, func in {
                    'deb': self.ver_from_deb_repo,
                    'rpm': self.ver_from_rpm_repo,
                    'win': self.ver_from_win_repo}.iteritems():
                try:
                    data = func(repo, branch=norm_branch)
                    if data:
                        out.update(data)
                        out.setdefault(repo_type, {})[k] = data.values()[0]
                except:
                    msg = '{0} repository {1} failed'.format(k, repo_type)
                    LOG.exception(msg)

        if devel_branch:
            self.get_szr_ver_from_repo.im_func.devel_cache[devel_branch] = out
        else:
            self.get_szr_ver_from_repo.im_func.cache = out
        return out

    def update_scalr_repo_data(self):
        info = {}
        vers = self.get_szr_ver_from_repo()
        repos = self.scalr_config['scalarizr_update']['repos']
        for repo_url in vers:
            for repo in repos:
                if repo_url in repos[repo].values():
                    info[repo] = vers[repo_url]
                    break
        if not info:
            return

        query = (
            "INSERT INTO settings "
            "(id, value) "
            "VALUES ('szr.repo.{name}', '{value}') "
            "ON DUPLICATE KEY "
            "UPDATE value = '{value}'"
        )
        for repo, vers in info.iteritems():
            repo = pymysql.escape_string(repo)
            self._db.execute(query.format(name=repo, value=vers))

    def _get_db_servers(self):
        if eol_os:
            query = (
                "SELECT s.server_id, s.farm_id, s.farm_roleid farm_role_id, s.remote_ip, s.local_ip, "
                "s.platform, r.os_id "
                "FROM servers s "
                "JOIN farm_roles fr ON s.farm_roleid=fr.id "
                "JOIN roles r ON fr.role_id=r.id "
                "WHERE r.os_id NOT IN ({}) "
                "AND s.status IN ('Running') "
                "ORDER BY s.server_id".format(str(eol_os)[1:-1]))
        else:
            query = (
                "SELECT server_id, farm_id, farm_roleid farm_role_id, "
                "remote_ip, local_ip, platform "
                "FROM servers "
                "WHERE status IN ('Running') "
                "ORDER BY server_id")
        return self._db.execute_with_limit(query, 500, retries=1)

    def _get_szr_upd_client(self, server):
        key = cryptotool.decrypt_key(server['scalarizr.key'])
        headers = {'X-Server-Id': server['server_id']}
        instances_connection_policy = self.scalr_config.get(server['platform'], {}).get(
            'instances_connection_policy', self.scalr_config['instances_connection_policy'])
        ip, port, proxy_headers = helper.get_szr_updc_conn_info(
            server, instances_connection_policy)
        headers.update(proxy_headers)
        szr_upd_client = SzrUpdClient(ip, port, key, headers=headers)
        return szr_upd_client

    def _get_status(self, server):
        szr_upd_client = self._get_szr_upd_client(server)
        timeout = self.config['instances_connection_timeout']
        status = szr_upd_client.status(cached=True, timeout=timeout)
        return status

    def _get_statuses(self, servers):
        async_results = {}
        for server in servers:
            if 'scalarizr.key' not in server:
                msg = "Server: {0}, reason: Missing scalarizr key".format(server['server_id'])
                LOG.warning(msg)
                continue
            if 'scalarizr.updc_port' not in server:
                api_port = self.scalr_config['scalarizr_update'].get('api_port', 8008)
                server['scalarizr.updc_port'] = api_port
            self._pool.wait()
            async_results[server['server_id']] = self._pool.apply_async(
                self._get_status, (server,))
            gevent.sleep(0)  # force switch

        statuses = {}
        timeout = self.config['instances_connection_timeout']
        for server in servers:
            try:
                server_id = server['server_id']
                statuses[server_id] = async_results[server_id].get(timeout=timeout)
            except:
                msg = 'Unable to get update client status, server: {0}, reason: {1}'
                msg = msg.format(server['server_id'], helper.exc_info())
                LOG.warning(msg)
        return statuses

    def _load_servers_data(self, servers):
        props = ('scalarizr.key', 'scalarizr.updc_port', 'scalarizr.version')
        self._db.load_server_properties(servers, props)

        farms = [{'id': __} for __ in set(_['farm_id'] for _ in servers)]
        props = ('szr.upd.schedule',)
        self._db.load_farm_settings(farms, props)
        farms_map = dict((_['id'], _) for _ in farms)

        farms_roles = [{'id': __} for __ in set(_['farm_role_id'] for _ in servers)]
        props = ['base.upd.schedule', 'scheduled_on', 'user-data.scm_branch']
        self._db.load_farm_role_settings(farms_roles, props)
        farms_roles_map = dict((_['id'], _) for _ in farms_roles)

        for server in servers:
            schedule = farms_roles_map.get(
                server['farm_role_id'], {}).get('base.upd.schedule', None)
            if not schedule:
                schedule = farms_map.get(
                    server['farm_id'], {}).get('szr.upd.schedule', '* * *')
            server['schedule'] = schedule
            server['scheduled_on'] = str(farms_roles_map.get(
                server['farm_role_id'], {}).get('scheduled_on', None))
            server['user-data.scm_branch'] = farms_roles_map.get(
                server['farm_role_id'], {}).get('user-data.scm_branch', None)
        return servers

    def _set_next_update_dt(self, servers):
        for server in servers:
            next_update_dt = str(self._scheduled_on(server['schedule']))
            if next_update_dt != server['scheduled_on']:
                query = (
                    """INSERT INTO farm_role_settings """
                    """(farm_roleid, name, value) """
                    """VALUES ({0}, 'scheduled_on', '{1}') """
                    """ON DUPLICATE KEY UPDATE value='{1}'"""
                ).format(server['farm_role_id'], next_update_dt)
                msg = "Set next update datetime for server: {0} to: {1}"
                msg = msg.format(server['server_id'], next_update_dt)
                LOG.debug(msg)
                try:
                    self._db.execute(query, retries=1)
                except:
                    msg = 'Unable to update next update datetime for server: {0}, reason: {1}'
                    msg = msg.format(server['server_id'], helper.exc_info())
                    LOG.warning(msg)

    def _get_servers_scheduled_for_update(self, servers):
        servers_scheduled_for_update = []
        for server in servers:
            try:
                version = server['scalarizr.version']
                if not version or parse_version(version) < parse_version('2.7.7'):
                    continue
                if not schedule_parser.Schedule(server['schedule']).intime():
                    continue
                servers_scheduled_for_update.append(server)
            except:
                msg = "Server: {0}, reason: {1}".format(server['server_id'], helper.exc_info())
                LOG.warning(msg)
                continue
        return servers_scheduled_for_update

    def _is_server_for_update(self, server, status):
        repo_url = status['repo_url']
        devel_branch = server.get('user-data.scm_branch', None)
        ver_info = self.get_szr_ver_from_repo(devel_branch=devel_branch)

        try:
            szr_ver_repo = ver_info[repo_url]
        except KeyError:
            pkg_type = helper.pkg_type_by_name(status['dist'].split()[0])
            szr_ver_repo = ver_info[status['repository']][pkg_type]

        if parse_version(server['scalarizr.version']) >= parse_version(szr_ver_repo):
            return False
        if 'in-progress' in status['state']:
            # skip in-progress server
            return False
        if status['executed_at']:
            last_update_dt = datetime.datetime.strptime(
                status['executed_at'], '%a %d %b %Y %H:%M:%S %Z')
            last_update_dt = last_update_dt.replace(minute=0, second=0, microsecond=0)
            utcnow_dt = datetime.datetime.utcnow()
            utcnow_dt = utcnow_dt.replace(minute=0, second=0, microsecond=0)
            if last_update_dt == utcnow_dt and status['state'] == 'error':
                # skip failed server
                LOG.debug(
                    'Skip server: {0}, reason: server in error state'.format(server['server_id']))
                return False
        return True

    def _scheduled_on(self, schedule):
        dt = datetime.datetime.utcnow()
        delta = datetime.timedelta(hours=1)
        for _ in xrange(0, 24 * 31 * 365):
            dt = dt + delta
            if schedule_parser.Schedule(schedule).intime(now=dt.timetuple()):
                return dt.replace(minute=0, second=0, microsecond=0)

    def get_servers_for_update(self):
        servers_for_update_high_pri = []
        servers_for_update_low_pri = []
        for servers in self._get_db_servers():
            self._load_servers_data(servers)
            self._set_next_update_dt(servers)
            servers_scheduled_for_update = self._get_servers_scheduled_for_update(servers)
            self._db.load_vpc_settings(servers_scheduled_for_update)
            statuses = self._get_statuses(servers_scheduled_for_update)
            for server in servers_scheduled_for_update:
                try:
                    if server['server_id'] not in statuses:
                        continue
                    if not self._is_server_for_update(server, statuses[server['server_id']]):
                        continue
                    if server['schedule'] == '* * *':
                        free = self.config['chunk_size'] - len(servers_for_update_high_pri)
                        if len(servers_for_update_low_pri) < free:
                            servers_for_update_low_pri.append(server)
                    else:
                        servers_for_update_high_pri.append(server)
                        if len(servers_for_update_high_pri) >= self.config['chunk_size']:
                            break
                except:
                    msg = "Server: {0}, reason: {1}".format(server['server_id'], helper.exc_info())
                    LOG.warning(msg)
            else:
                continue
            break

        if len(servers_for_update_high_pri) < self.config['chunk_size']:
            servers_for_update = servers_for_update_high_pri + servers_for_update_low_pri
            servers_for_update = servers_for_update[0:self.config['chunk_size']]
        else:
            servers_for_update = servers_for_update_high_pri

        return servers_for_update

    def update_server(self, server):
        try:
            szr_upd_client = self._get_szr_upd_client(server)
            timeout = self.config['instances_connection_timeout']
            msg = "Trying to update server: {0}, version: {1}".format(
                server['server_id'], server['scalarizr.version'])
            LOG.debug(msg)
            try:
                result_id = szr_upd_client.update(async=True, timeout=timeout)
            except:
                msg = 'Unable to update, reason: {0}'.format(helper.exc_info())
                raise Exception(msg)
            LOG.debug("Server: {0}, result: {1}".format(server['server_id'], result_id))
        except:
            msg = "Server failed: {0}, reason: {1}".format(server['server_id'], helper.exc_info())
            LOG.warning(msg)

    def before_iteration(self):
        self.load_config()
        self.configure()
        self.clear_cache()

    def do_iteration(self):
        servers = self.get_servers_for_update()
        for server in servers:
            try:
                self._pool.wait()
                self._pool.apply_async(self.update_server, (server,))
                gevent.sleep(0)  # force switch
            except:
                LOG.warning(helper.exc_info())
        self._pool.join()
        try:
            self.update_scalr_repo_data()
        except:
            msg = 'Unable to update scalr.settings table, reason: {0}'.format(helper.exc_info())
            LOG.error(msg)

    def after_iteration(self):
        self._pool.kill()


def main():
    global app
    app = SzrUpdService()
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
