# vim: tabstop=4 shiftwidth=4 softtabstop=4
#
# Copyright 2013, 2014, 2015 Scalr Inc.
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

import uuid
import json
import datetime
import threading

from scalrpy.util import helper
from scalrpy import LOG
from scalrpy import exceptions


# Do not change this UUID
UUID = uuid.UUID('99c3db97-5c41-4113-874c-159dab36a36c')


PLATFORMS = [
    'cloudstack',
    'ec2',
    'gce',
    'idcf',
    'openstack',
    'rackspacenguk',
    'rackspacengus',
    'ocs',
    'nebula',
    'mirantis',
    'vio',
    'verizon',
    'cisco',
]

url_key_map = {
    'cloudstack': 'api_url',
    'openstack': 'keystone_url',
    'ec2': None,
    'gce': None,
}
url_key_map['idcf'] = url_key_map['cloudstack']
url_key_map['rackspacenguk'] = url_key_map['openstack']
url_key_map['rackspacengus'] = url_key_map['openstack']
url_key_map['ocs'] = url_key_map['openstack']
url_key_map['nebula'] = url_key_map['openstack']
url_key_map['mirantis'] = url_key_map['openstack']
url_key_map['vio'] = url_key_map['openstack']
url_key_map['verizon'] = url_key_map['openstack']
url_key_map['cisco'] = url_key_map['openstack']

os_map = {
    'linux': 0,
    'windows': 1,
    None: 0
}


class Credentials(dict):

    scheme = {
        'cloudstack': ['api_key', 'secret_key', 'api_url'],
        'ec2': ['access_key', 'secret_key', 'account_id', 'account_type'],
        'gce': ['service_account_name', 'key', 'project_id', 'json_key'],
        'idcf': ['api_key', 'secret_key', 'api_url'],
        'openstack': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'rackspacenguk': ['username', 'api_key', 'keystone_url'],
        'rackspacengus': ['username', 'api_key', 'keystone_url'],
        'ocs': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'nebula': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'mirantis': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'vio': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'verizon': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'cisco': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'azure': ['tenant_name', 'subscription_id'],
    }

    def __init__(self, env_id, platform, data):
        self._env_id = env_id
        self._platform = platform
        for name in self.scheme[platform]:
            if name in data:
                self[name] = data[name]
        if platform == 'ec2':
            try:
                self['account_type'] = data['account_type']
            except KeyError:
                self['account_type'] = 'regular'

    def __eq__(self, other):
        if self.platform != other.platform:
            return False
        if self.platform == 'ec2' and 'account_id' in self and 'account_id' in other:
            return self['account_id'] == other['account_id']
        return self == other

    @property
    def env_id(self):
        return self._env_id

    @property
    def platform(self):
        return self._platform

    @property
    def unique(self):
        if self.platform == 'ec2' and 'account_id' in self:
            unique_key = self['account_id']
        else:
            unique_key = '; '.join([str(self[k]) for k in self.scheme[self.platform] if k in self])
        assert unique_key, 'unique_key'
        return unique_key

    @classmethod
    def test(cls, data, platform):
        scheme = {
            'cloudstack': [data.get('api_key'), data.get('secret_key'), data.get('api_url')],
            'ec2': [data.get('access_key'), data.get('secret_key')],
            'gce': [data.get('service_account_name'), data.get('project_id'), data.get('key') or data.get('json_key')],
            'openstack': [data.get('username'), data.get('api_key') or data.get('password'), data.get('keystone_url')],
        }
        scheme['idcf'] = scheme['cloudstack']
        scheme['rackspacenguk'] = scheme['rackspacengus'] = scheme['openstack']
        scheme['ocs'] = scheme['nebula'] = scheme['mirantis'] = scheme['openstack']
        scheme['vio'] = scheme['verizon'] = scheme['cisco'] = scheme['openstack']
        if None in scheme[platform]:
            if [None] * len(scheme[platform]) == scheme[platform]:
                raise exceptions.MissingCredentialsError()
            else:
                raise exceptions.IncompleteCredentialsError()


class Analytics(object):

    def __init__(self, scalr_db, analytics_db):
        self.scalr_db = scalr_db
        self.analytics_db = analytics_db

        self._quarters_calendar = None

        self.usage_types = {}
        self.usage_items = {}

        self.usage_items_lock = threading.Lock()
        self.farm_usage_d_lock = threading.Lock()
        self.lock = threading.Lock()

    def get_server_id_by_instance_id(self, servers, platform, cloud_location=None, envs_ids=None, url=None):
        if platform != 'ec2':
            assert cloud_location
            assert envs_ids
        instances_ids = list(set([str(server['instance_id']) for server in servers]))
        instances_ids = map(str, instances_ids)
        if not instances_ids:
            return tuple()
        query = (
            "SELECT sh.server_id, sh.cloud_server_id instance_id, sh.env_id "
            "FROM servers_history sh "
        )
        if url:
            query += "JOIN client_environment_properties cep ON sh.env_id=cep.env_id "
        query += (
            "WHERE sh.platform='{platform}' "
            "AND sh.cloud_server_id IN ({instances_ids}) "
        )
        if url:
            query += (
                "AND cep.name='{platform}.{url_key}' "
                "AND cep.value='{url}' "
            )
        if cloud_location:
            query += "AND sh.cloud_location='{cloud_location}' "

        kwds = {
            'platform': platform,
            'cloud_location': cloud_location,
            'url_key': url_key_map[platform],
            'url': url,
            'instances_ids': str(instances_ids)[1:-1]
        }
        query = query.format(**kwds)
        results = self.scalr_db.execute(query, retries=1)

        if envs_ids:
            envs_ids = map(int, envs_ids)
            results_map = dict((result['instance_id'], result)
                               for result in results if int(result['env_id']) in envs_ids)
        else:
            results_map = dict((result['instance_id'], result)
                               for result in results)
        for server in servers:
            server.update(results_map.get(server['instance_id'], {}))

    def load_usage_types(self):
        query = (
            "SELECT HEX(id) id, cost_distr_type, name "
            "FROM usage_types")
        results = self.analytics_db.execute(query)
        for result in results:
            self.usage_types[result['id']] = result

    def load_usage_items(self):
        query = (
            "SELECT HEX(id) id, HEX(usage_type) usage_type, name "
            "FROM usage_items")
        results = self.analytics_db.execute(query)
        for result in results:
            self.usage_items[result['id']] = result

    def get_quarters_calendar(self):
        if self._quarters_calendar is None:
            query = (
                "SELECT value "
                "FROM settings "
                "WHERE id='budget_days'")
            self._quarters_calendar = QuartersCalendar(
                json.loads(self.analytics_db.execute(query, retries=1)[0]['value']))
        return self._quarters_calendar

    def load_envs(self, limit=500, platform=None):
        """
        :returns: generator
        """
        platforms = [platform] if platform else PLATFORMS
        query = (
            "SELECT ce.id, ce.client_id "
            "FROM client_environments ce "
            "JOIN clients c ON ce.client_id=c.id "
            "WHERE c.status='Active' "
            "AND ce.status='Active' "
            "ORDER BY ce.id ASC")
        for envs in self.scalr_db.execute_with_limit(query, limit, retries=1):
            names = ['%s.is_enabled' % platform for platform in platforms]
            self.scalr_db.load_client_environment_properties(envs, names)
            yield envs

    def load_env_credentials(self, envs, platform=None):
        envs_ids = list(set(int(env['id']) for env in envs))
        if not envs_ids:
            return
        platforms = [platform] if platform else PLATFORMS
        names = list(set([name for pl in platforms for name in Credentials.scheme[pl]]))
        if platform in (None, 'ec2'):
            names += [
                'detailed_billing.bucket',
                'detailed_billing.region',
                'detailed_billing.enabled',
                'detailed_billing.payer_account',
            ]
        query = (
            "SELECT ecc.env_id, ecc.cloud platform, ccp.name, ccp.value "
            "FROM environment_cloud_credentials ecc "
            "JOIN cloud_credentials_properties ccp "
            "ON ecc.cloud_credentials_id=ccp.cloud_credentials_id "
            "WHERE name IN ({name}) "
            "AND ecc.env_id IN ({env_id}) "
        ).format(name=str(names)[1:-1], env_id=str(envs_ids)[1:-1])
        if platform:
            query += "AND ecc.cloud='{platform}'".format(platform=platform)
        results = self.scalr_db.execute(query)
        for env in envs:
            env.update({'%s.%s' % (result['platform'], result['name']): result['value']
                       for result in results
                       if result['env_id'] == env['id'] and
                       '%s.%s' % (result['platform'], result['name']) not in env})

    def load_aws_accounts_ids(self):
        query = (
            "SELECT DISTINCT ccp.value account_id "
            "FROM cloud_credentials_properties ccp "
            "JOIN cloud_credentials cc "
            "ON ccp.cloud_credentials_id=cc.id "
            "WHERE cc.cloud='ec2' "
            "AND ccp.name='account_id' "
            "AND ccp.value IS NOT NULL "
            "AND ccp.value != ''")
        results = self.scalr_db.execute(query)
        return [result['account_id'] for result in results]

    def load_aws_payers_accounts(self):
        query = (
            "SELECT DISTINCT ccp.value payer_account "
            "FROM cloud_credentials_properties ccp "
            "JOIN cloud_credentials cc "
            "ON ccp.cloud_credentials_id=cc.id "
            "WHERE cc.cloud='ec2' "
            "AND ccp.name='detailed_billing.payer_account' "
            "AND ccp.value IS NOT NULL "
            "AND ccp.value != ''")
        results = self.scalr_db.execute(query)
        return [result['payer_account'] for result in results]

    def load_aws_accounts_ids_envs(self, accounts_ids):
        query = (
            "SELECT ce.id, ce.client_id "
            "FROM client_environments ce "
            "JOIN clients c ON ce.client_id=c.id "
            "JOIN environment_cloud_credentials ecc "
            "ON ce.id=ecc.env_id AND ecc.cloud='ec2' "
            "JOIN cloud_credentials_properties ccp "
            "ON ecc.cloud_credentials_id=ccp.cloud_credentials_id "
            "WHERE c.status='Active' "
            "AND ce.status='Active' "
            "AND ecc.cloud='ec2' "
            "AND ccp.name='account_id' "
            "AND ccp.value IN ({account_id})"
        ).format(account_id=str(accounts_ids)[1:-1])
        results = self.scalr_db.execute(query)
        envs = {}
        for result in results:
            envs.setdefault(result['id'], {'id': result['id'], 'client_id': result['client_id']})
        envs = envs.values()
        names = [
            'ec2.is_enabled',
        ]
        self.scalr_db.load_client_environment_properties(envs, names)
        return envs

    def load_aws_payers_accounts_envs(self, payers_accounts):
        query = (
            "SELECT ce.id, ce.client_id "
            "FROM client_environments ce "
            "JOIN clients c ON ce.client_id=c.id "
            "JOIN environment_cloud_credentials ecc "
            "ON ce.id=ecc.env_id AND ecc.cloud='ec2' "
            "JOIN cloud_credentials_properties ccp "
            "ON ecc.cloud_credentials_id=ccp.cloud_credentials_id "
            "WHERE c.status='Active' "
            "AND ce.status='Active' "
            "AND ecc.cloud='ec2' "
            "AND ccp.name='detailed_billing.payer_account' "
            "AND ccp.value IN ({payer_account})"
        ).format(payer_account=str(payers_accounts)[1:-1])
        results = self.scalr_db.execute(query)
        envs = {}
        for result in results:
            envs.setdefault(result['id'], {'id': result['id'], 'client_id': result['client_id']})
        envs = envs.values()
        names = [
            'ec2.is_enabled',
        ]
        self.scalr_db.load_client_environment_properties(envs, names)
        return envs

    def load_azure_subscriptions_ids(self):
        query = (
            "SELECT DISTINCT ccp.value subscription_id "
            "FROM cloud_credentials_properties ccp "
            "JOIN cloud_credentials cc "
            "ON ccp.cloud_credentials_id=cc.id "
            "WHERE ccp.name='subscription_id'")
        results = self.scalr_db.execute(query)
        return [result['subscription_id'] for result in results]

    def load_azure_subscriptions_ids_envs(self, subscriptions_ids):
        query = (
            "SELECT ce.id, ce.client_id "
            "FROM client_environments ce "
            "JOIN clients c ON ce.client_id=c.id "
            "JOIN environment_cloud_credentials ecc "
            "ON ce.id=ecc.env_id AND ecc.cloud='azure' "
            "JOIN cloud_credentials_properties ccp "
            "ON ecc.cloud_credentials_id=ccp.cloud_credentials_id "
            "WHERE c.status='Active' "
            "AND ce.status='Active' "
            "AND ecc.cloud='azure' "
            "AND ccp.name='subscription_id' "
            "AND ccp.value IN ({subscription_id})"
        ).format(subscription_id=str(subscriptions_ids)[1:-1])
        results = self.scalr_db.execute(query)
        envs = {}
        for result in results:
            envs.setdefault(result['id'], {'id': result['id'], 'client_id': result['client_id']})
        envs = envs.values()
        names = [
            'azure.is_enabled',
        ]
        self.scalr_db.load_client_environment_properties(envs, names)
        return envs

    def get_credentials(self, envs, platforms=None):
        platforms = platforms or Credentials.scheme.keys()
        credentials = []
        for env in envs:
            for platform in Credentials.scheme:
                key = '%s.%s' % (platform, 'is_enabled')
                if key not in env or env[key] == '0':
                    continue
                data = {}
                for name in Credentials.scheme[platform]:
                    key = '%s.%s' % (platform, name)
                    if key in env:
                        data[name] = env[key]
                credentials.append(Credentials(env['id'], platform, data))
        return credentials

    def load_servers_data(self, servers):
        servers_ids = list(set(server['server_id'] for server in servers if server['server_id']))
        servers_ids = map(str, servers_ids)
        if not servers_ids:
            return
        query = (
            "SELECT sh.server_id, sh.env_id, sh.client_id account_id, sh.farm_id, "
            "sh.farm_roleid farm_role_id, sh.role_id, sh.type instance_type, sh.os_type os, "
            "sh.cloud_location, sh.cloud_server_id instance_id, sh.farm_created_by_id user, "
            "HEX(sh.project_id) project_id, HEX(sh.cc_id) cc_id "
            "FROM servers_history sh "
            "WHERE sh.server_id IN ({server_id})"
        ).format(server_id=str(servers_ids)[1:-1])
        results = self.scalr_db.execute(query, retries=1)

        results_map = {result['server_id']: result for result in results}

        # remove not managed servers
        alien_servers = [server for server in servers if server['server_id'] not in results_map]
        for alien in alien_servers:
            servers.remove(alien)

        servers_without_project_id = []
        servers_without_cc_id = []
        servers_without_role_id = []
        for server in servers:
            # skip already existed data
            for k, v in results_map[server['server_id']].items():
                if k in server and server[k]:
                    continue
                server[k] = v

            self.convert_os(server)

            if not server.get('project_id'):
                servers_without_project_id.append(server)
            if not server.get('cc_id'):
                servers_without_cc_id.append(server)
            if not server.get('role_id'):
                servers_without_role_id.append(server)

        if servers_without_project_id:
            self.load_project_id(servers_without_project_id)
        if servers_without_cc_id:
            self.load_cc_id(servers_without_cc_id)
        if servers_without_role_id:
            self.load_role_id(servers_without_role_id)

    def convert_os(self, server):
        if server.get('os') in ('linux', 'windows', None):
            server['os'] = os_map[server.get('os')]

    def load_server_properties(self, servers, names):
        self.scalr_db.load_server_properties(servers, names)
        name_map = {
            'farm.created_by_id': 'user',
            'farm.project_id': 'project_id',
            'os_type': 'os',
        }
        for server in servers:
            for k, v in name_map.items():
                if k in names:
                    server[v] = server.get(v) or server.pop(k, None)
            self.convert_os(server)

    def load_project_id(self, servers):
        self.load_server_properties(servers, ['farm.project_id'])
        for server in servers:
            if not server.get('project_id', None):
                msg = "Unable to load project_id for server: {}".format(server)
                LOG.warning(msg)

    def load_role_id(self, servers):
        farm_roles_ids = list(set(int(server['farm_role_id']) for server in servers))
        query = (
            "SELECT id farm_role_id, role_id "
            "FROM farm_roles "
            "WHERE id IN ({farm_role_id})"
        ).format(farm_role_id=str(farm_roles_ids)[1:-1])
        results = self.scalr_db.execute(query, retries=1)
        for server in servers:
            server.update({'role_id': result['role_id'] for result in results
                          if result['farm_role_id'] == server['farm_role_id']
                          and not server.get('role_id')})
            if not server.get('role_id'):
                msg = "Can't find role_id for farm_role_id: {}".format(server['farm_role_id'])
                LOG.warning(msg)

    def load_cc_id(self, servers):
        projects_ids = list(set(uuid.UUID(server['project_id']).hex for server in servers
                            if server.get('project_id')))
        if projects_ids:
            query = (
                "SELECT HEX(cc_id) cc_id, HEX(project_id) project_id "
                "FROM projects "
                "WHERE HEX(project_id) IN ({project_id})"
            ).format(project_id=str(projects_ids)[1:-1])
            results = self.scalr_db.execute(query, retries=1)
            for server in servers:
                server.update({'cc_id': result['cc_id'] for result in results
                              if result['project_id'] == server.get('project_id')
                              and not server.get('cc_id')})

        envs_ids = list(set(int(server['env_id']) for server in servers if not server.get('cc_id')))
        envs = [{'id': env_id} for env_id in envs_ids]
        self.scalr_db.load_client_environment_properties(envs, ['cc_id'])

        for server in servers:
            if not server.get('cc_id', None):
                msg = "Unable to load cc_id for server: {}".format(server)
                LOG.warning(msg)

    def _get_processing_dtime(self, date, hour=None):
        date = datetime.datetime.strptime(str(date), '%Y-%m-%d').date()
        if hour is None:
            dtime_from = datetime.datetime(date.year, date.month, date.day, 0, 0, 0)
        else:
            dtime_from = datetime.datetime(date.year, date.month, date.day, hour, 0, 0)
        if hour is None:
            dtime_to = datetime.datetime(date.year, date.month, date.day, 23, 59, 59)
        else:
            dtime_to = datetime.datetime(date.year, date.month, date.day, hour, 59, 59)
        return dtime_from, dtime_to

    def get_poller_servers(self, date, hour, platform=None, limit=500, force=False):
        """
        :returns: generator
        """
        dtime_from, dtime_to = self._get_processing_dtime(date, hour)

        query = (
            "SELECT DISTINCT HEX(m.server_id) as server_id, m.instance_type, m.os,"
            "ps.account_id, ps.env_id, ps.platform, ps.cloud_location, ps.url "
            "FROM managed m "
            "JOIN poller_sessions ps ON m.sid=ps.sid "
            "WHERE ps.dtime BETWEEN '{dtime_from}' "
            "AND '{dtime_to}' "
        ).format(dtime_from=dtime_from, dtime_to=dtime_to)
        if platform:
            query += "AND ps.platform='{platform}' ".format(platform=platform)
        query += "ORDER BY m.sid, m.server_id"

        for servers in self.analytics_db.execute_with_limit(query, limit, retries=1):
            for server in servers:
                server['server_id'] = str(uuid.UUID(server['server_id']))
                server['dtime'] = dtime_from

            if not force:
                servers = [server for server in servers if not self.record_exists(server)]

            self.load_servers_data(servers)

            yield servers

    def record_exists(self, record):
        query = (
            "SELECT HEX(u.usage_id) "
            "FROM usage_h u "
            "JOIN usage_servers_h us "
            "ON u.usage_id=us.usage_id "
            "WHERE u.env_id={env_id} "
            "AND u.dtime='{dtime}' "
            "AND us.server_id=UNHEX('{server_id}')"
        ).format(env_id=record['env_id'], dtime=record['dtime'],
                 server_id=record['server_id'].replace('-', ''))
        return bool(self.analytics_db.execute(query, retries=1))

    def get_records(self, date, hour, platform, amazon_billing=False, limit=1000):
        """
        :returns: generator
        """
        dtime_from, dtime_to = self._get_processing_dtime(date, hour)

        query = (
            "SELECT HEX(usage_id) as usage_id, account_id, dtime, platform, url, cloud_location, "
            "HEX(usage_item) usage_item, os, HEX(cc_id) cc_id, HEX(project_id) project_id, "
            "env_id, farm_id, farm_role_id, role_id, num, cost "
            "FROM usage_h "
            "WHERE dtime='{dtime}' "
            "AND platform='{platform}' "
            "ORDER BY usage_id"
        ).format(dtime=dtime_from, platform=platform)

        for results in self.analytics_db.execute_with_limit(query, limit, retries=1):
            if not amazon_billing:
                envs_ids = list(set(result['env_id'] for result in results))
                envs = [{'id': env_id} for env_id in envs_ids]
                names = ['ec2.detailed_billing.enabled']
                self.scalr_db.load_client_environment_properties(envs, names)
                amazon_billing_envs_ids = [env['id'] for env in envs
                                           if env.get('ec2.detailed_billing.enabled', None) == '1']
                amazon_billing_envs_ids = map(int, amazon_billing_envs_ids)
                results = [r for r in results if r['env_id'] not in amazon_billing_envs_ids]
            yield list(results)

    def _get_raw_prices(self, servers):
        """
        :returns: generator
        """
        date_map = {}
        for server in servers:
            date_map.setdefault(server['dtime'].date(), set([0])).add(server['account_id'])

        for date, accounts_ids in date_map.items():
            accounts_ids = list(accounts_ids)
            base_query = (
                "SELECT ph1.account_id, ph1.platform, ph1.cloud_location, ph1.url, "
                "p1.instance_type, p1.cost, p1.os "
                "FROM "
                "( "
                "(price_history AS ph1 JOIN prices AS p1 ON p1.price_id=ph1.price_id) "
                "LEFT JOIN "
                "(price_history AS ph2 JOIN prices AS p2 ON p2.price_id=ph2.price_id) "
                "ON ph2.platform=ph1.platform "
                "AND ph2.cloud_location=ph1.cloud_location "
                "AND ph2.url=ph1.url "
                "AND ph2.account_id=ph1.account_id "
                "AND p2.instance_type=p1.instance_type "
                "AND p2.os=p1.os "
                "AND ph2.applied>ph1.applied "
                "AND ph2.applied<='{date}' "
                ") "
                "WHERE ph2.price_id IS NULL "
                "AND ph1.applied<='{date}' "
                "AND ph1.account_id IN ({account_id})"
            )
            i, chunk_size = 0, 100
            while True:
                chunk_accounts_ids = accounts_ids[i * chunk_size:(i + 1) * chunk_size]
                if len(chunk_accounts_ids) == 0:
                    break
                query = base_query.format(
                    date=date, account_id=str(chunk_accounts_ids).replace('L', '')[1:-1])
                results = self.analytics_db.execute(query, retries=1)
                if not results:
                    break
                i += 1
                yield results

    def get_prices(self, servers):
        """
        :returns: dict {account_id: {platform_url: {cloud_location: {instance_type: {os: cost}}}}}
        """
        prices = dict()
        for raw_prices in self._get_raw_prices(servers):
            for raw_price in raw_prices:
                try:
                    account_id = raw_price['account_id']
                    platform = raw_price['platform']
                    url = raw_price['url']
                    platform_url = '%s;%s' % (platform, url)
                    cloud_location = raw_price['cloud_location']
                    instance_type = raw_price['instance_type']
                    os_type = raw_price['os']
                    cost = raw_price['cost']
                    prices.setdefault(account_id, dict())
                    prices[account_id].setdefault(platform_url, dict())
                    prices[account_id][platform_url].setdefault(cloud_location, dict())
                    prices[account_id][platform_url][
                        cloud_location].setdefault(instance_type, dict())
                    prices[account_id][platform_url][cloud_location][instance_type][os_type] = cost
                except KeyError:
                    msg = "Unable to get price from raw price. Reason: {}"
                    msg = msg.format(helper.exc_info())
                    LOG.warning(msg)
        return prices

    def get_cost_from_prices(self, server, prices):
        account_id = server['account_id']
        platform = server['platform']
        url = server['url']
        cloud_location = server['cloud_location']
        if 'instance_type' in server:
            instance_type = server['instance_type']
        else:
            if server['usage_item'] not in self.usage_items:
                self.load_usage_items()
            instance_type = self.usage_items[server['usage_item']]['name']
        os = server['os']
        platform_url = '%s;%s' % (platform, url)
        try:
            if account_id in prices:
                cost = prices[account_id][platform_url][cloud_location][instance_type][os]
            else:
                cost = prices[0][platform_url][cloud_location][instance_type][os]
        except KeyError:
            cost = None
            msg = (
                "Unable to get cost for account_id: {}, platform: {}, url: {}, "
                "cloud_location: {}, instance_type: {}, os: {}. Use 0.0. Reason: {}"
            ).format(account_id, platform, url, cloud_location,
                     instance_type, os, helper.exc_info())
            LOG.debug(msg)
        return cost

    def set_usage_item(self, record):
        with self.usage_items_lock:
            data = {
                'cost_distr_type': record.get('cost_distr_type', 1),
                'name': record.get('usage_type_name', 'BoxUsage'),
                'display_name': None,
            }

            def get_usage_type_id():
                for key, row in self.usage_types.items():
                    if data['cost_distr_type'] == row['cost_distr_type'] and data['name'] == row['name']:
                        return key
                return None

            while True:
                usage_type_id = get_usage_type_id()
                if usage_type_id:
                    break
                usage_type_id = uuid.uuid4().hex[0:8]
                data['id'] = usage_type_id
                usage_types = Usage_types(data)
                self.analytics_db.execute(usage_types.insert_query())
                self.load_usage_types()

            data = {
                'usage_type': usage_type_id,
                'name': record.get('usage_item_name', False) or record['instance_type'],
                'display_name': None,
            }
            assert data['name'], record

            def get_usage_item_id():
                for key, row in self.usage_items.items():
                    if data['usage_type'] == row['usage_type'] and data['name'] == row['name']:
                        return key
                return None

            while True:
                usage_item_id = get_usage_item_id()
                if usage_item_id:
                    break
                usage_item_id = uuid.uuid4().hex[0:8]
                data['id'] = usage_item_id
                usage_items = Usage_items(data)
                self.analytics_db.execute(usage_items.insert_query())
                self.load_usage_items()

        record['usage_item'] = usage_item_id.lower()

    def insert_record(self, record, callback=None):
        try:
            record = record.copy()
            self.set_usage_item(record)

            LOG.debug('Insert record: %s' % record)

            if record['platform'] == 'gce':
                record['instance_id'] = record['server_id']
            record['date'] = record['dtime'].date()

            self.analytics_db.autocommit(False)
            try:
                if record['platform'] in ('ec2', 'azure') and record.get('record_id'):
                    query = (
                        """INSERT INTO aws_billing_records (record_id, date) """
                        """VALUES ('{record_id}', '{record_date}') """
                    ).format(**record)
                    self.analytics_db.execute(query, retries=1)

                # insert in usage_h table
                usage_h = Usage_h(record)
                self.analytics_db.execute(usage_h.insert_query(), retries=1)

                record['usage_id'] = usage_h['usage_id']

                if record['cost_distr_type'] == 1:
                    # insert in usage_servers_h table
                    usage_servers_h = Usage_servers_h(record)
                    self.analytics_db.execute(usage_servers_h.insert_query(), retries=1)

                    value_id_map = {6: record.get('user')}
                    for tag_id, value_id in value_id_map.items():
                        record['tag_id'] = tag_id
                        record['value_id'] = value_id

                        # insert account_tag_values table
                        account_tag_values = Account_tag_values(record)
                        self.analytics_db.execute(account_tag_values.insert_query(), retries=1)

                        # insert usage_h_tags table
                        usage_h_tags = Usage_h_tags(record)
                        self.analytics_db.execute(usage_h_tags.insert_query(), retries=1)

                # insert usage_d table
                usage_d = Usage_d(record)
                self.analytics_db.execute(usage_d.insert_query(), retries=1)

                # insert quarterly_budget
                quarters_calendar = self.get_quarters_calendar()
                record['year'] = quarters_calendar.year_for_date(record['date'])
                record['quarter'] = quarters_calendar.quarter_for_date(record['date'])
                record['cumulativespend'] = record['cost']
                record['spentondate'] = record['dtime']
                if record['cc_id']:
                    record['subject_type'] = 1
                    record['subject_id'] = record['cc_id']
                    quarterly_budget = Quarterly_budget(record)
                    self.analytics_db.execute(quarterly_budget.insert_query(), retries=1)
                if record['project_id']:
                    record['subject_type'] = 2
                    record['subject_id'] = record['project_id']
                    quarterly_budget = Quarterly_budget(record)
                    self.analytics_db.execute(quarterly_budget.insert_query(), retries=1)

                self.analytics_db.commit()
            except:
                self.analytics_db.rollback()
                raise
            finally:
                self.analytics_db.autocommit(True)
        except:
            msg = "Unable to insert record: {}"
            msg = msg.format(record=record)
            helper.handle_error(message=msg)
            raise

        if callable(callback):
            callback(record)

    def remove_existing_records_with_record_id(self, records):
        records_ids = [record['record_id'] for record in records if record.get('record_id')]
        if records_ids:
            query = (
                """SELECT record_id """
                """FROM aws_billing_records """
                """WHERE record_id IN ({records_ids})"""
            ).format(records_ids=str(records_ids)[1:-1])
            results = self.analytics_db.execute(query, retries=1)
            existing_records_ids = [result['record_id'] for result in results]
            records = [record for record in records if record['record_id'] not in existing_records_ids]
        return records

    def insert_records(self, records, callback=None):
        try:
            records = [record for record in records if record.get('cloud_location')]
            if not records:
                return

            cost_distr_type_1_records = []
            records_with_record_id = []
            for record in records:
                self.set_usage_item(record)
                record['usage_id'] = Usage_h(record)['usage_id']
                if record['platform'] == 'gce':
                    record['instance_id'] = record['server_id']
                if record['cost_distr_type'] == 1:
                    cost_distr_type_1_records.append(record)
                if record['platform'] in ('ec2', 'azure') and record.get('record_id'):
                    records_with_record_id.append(record)
                record['date'] = record['dtime'].date()

            # remove dulplicate records
            records = {'%s;%s' % (r['usage_id'], r['server_id']): r for r in records}.values()

            LOG.debug('Insert {} records'.format(len(records)))

            with self.lock:
                try:
                    self.analytics_db.autocommit(False)

                    # billing_records table
                    if records_with_record_id:
                        query = Billing_records.insert_many_query(records_with_record_id)
                        self.analytics_db.execute(query, retries=1)

                    # usage_h table
                    query = Usage_h.insert_many_query(records)
                    self.analytics_db.execute(query, retries=1)

                    # usage_servers_h, account_tag_values, usage_h_tags tables
                    if cost_distr_type_1_records:
                        value_id_map = {6: 'user'}
                        for tag_id, value_id in value_id_map.items():
                            for record in cost_distr_type_1_records:
                                record['tag_id'] = tag_id
                                record['value_id'] = record.get(value_id)
                            query = Account_tag_values.insert_many_query(cost_distr_type_1_records)
                            self.analytics_db.execute(query, retries=1)
                            query = Usage_h_tags.insert_many_query(cost_distr_type_1_records)
                            self.analytics_db.execute(query, retries=1)
                        query = Usage_servers_h.insert_many_query(cost_distr_type_1_records)
                        self.analytics_db.execute(query, retries=1)

                    # usage_d table
                    query = Usage_d.insert_many_query(records)
                    self.analytics_db.execute(query, retries=1)

                    # quarterly_budget
                    quarters_calendar = self.get_quarters_calendar()
                    for record in records:
                        record['year'] = quarters_calendar.year_for_date(record['date'])
                        record['quarter'] = quarters_calendar.quarter_for_date(record['date'])
                        record['cumulativespend'] = record['cost']
                        record['spentondate'] = record['dtime']

                    subject_type_1_records = [r for r in records if r.get('cc_id')]
                    if subject_type_1_records:
                        for record in subject_type_1_records:
                            record['subject_type'] = 1
                            record['subject_id'] = record['cc_id']
                        query = Quarterly_budget.insert_many_query(subject_type_1_records)
                        self.analytics_db.execute(query, retries=1)

                    subject_type_2_records = [r for r in records if r.get('project_id')]
                    if subject_type_2_records:
                        for record in subject_type_1_records:
                            record['subject_type'] = 2
                            record['subject_id'] = record['project_id']
                        query = Quarterly_budget.insert_many_query(subject_type_2_records)
                        self.analytics_db.execute(query, retries=1)

                    self.analytics_db.commit()
                except:
                    self.analytics_db.rollback()
                    raise
                finally:
                    self.analytics_db.autocommit(True)

            if callable(callback):
                callback(records)
        except:
            helper.handle_error('Unable to insert records')

    def _find_subject_id(self, record):
        nm_subjects_h = NM_subjects_h(record)
        r = self.analytics_db.execute(nm_subjects_h.subject_id_query(), retries=1)
        return r[0]['subject_id'] if r else None

    def fill_farm_usage_d(self, date, hour, platform=None):
        query = (
            "INSERT INTO farm_usage_d "
            "(account_id, farm_role_id, usage_item, cc_id, project_id, date, "
            "platform, cloud_location, env_id, "
            "farm_id, role_id, cost, min_usage, max_usage, usage_hours, working_hours) "
            "SELECT account_id, farm_role_id, usage_item, cc_id, project_id, '{date}', "
            "platform, cloud_location, env_id, farm_id, role_id, "
            "cost, min_usage, max_usage, usage_hours, working_hours "
            "FROM ("
            "SELECT account_id, farm_role_id, usage_item, "
            "IFNULL(cc_id, '') cc_id, IFNULL(project_id, '') project_id, platform, "
            "cloud_location, env_id, IFNULL(farm_id, 0) farm_id, role_id, SUM(cost) cost, "
            "(CASE WHEN COUNT(dtime)={hour} THEN MIN(num) ELSE 0 END) min_usage, "
            "MAX(num) max_usage, SUM(num) usage_hours, COUNT(dtime) working_hours "
            "FROM usage_h uh "
            "WHERE uh.dtime BETWEEN '{date} 00:00:00' AND '{date} 23:59:59'"
            "AND uh.farm_id > 0 AND uh.farm_role_id > 0 "
        )

        if platform:
            query += "AND platform='{platform}' ".format(platform=platform)

        query += (
            "GROUP BY uh.account_id, uh.farm_role_id, uh.usage_item "
            ") t "
            "ON DUPLICATE KEY UPDATE "
            "cost = t.cost, "
            "min_usage = t.min_usage, "
            "max_usage = t.max_usage, "
            "usage_hours = t.usage_hours, "
            "working_hours = t.working_hours"
        )
        query = query.format(date=date, hour=hour + 1)
        self.analytics_db.execute(query, retries=1)

    def recalculate_usage_d(self, date, platform):
        dtime_from, dtime_to = self._get_processing_dtime(date)

        query = ("SELECT MIN(dtime) dtime FROM usage_h")
        min_dtime = self.analytics_db.execute(query, retries=1)[0]['dtime']
        if min_dtime and min_dtime.date() > dtime_from.date():
            LOG.warning("Recalculating not available or data has been deleted")
            return

        usage_d = Usage_d({'platform': platform, 'date': dtime_from.date()})
        self.analytics_db.execute(usage_d.delete_query(), retries=1)

        query = (
            "INSERT INTO usage_d "
            "(date, platform, cc_id, project_id, farm_id, env_id, cost) "
            "SELECT date, platform, cc_id, project_id, farm_id, env_id, cost "
            "FROM (SELECT DATE(u.dtime) date, u.platform,"
            "u.cc_id, u.project_id, u.farm_id, u.env_id, SUM(u.cost) cost "
            "FROM usage_h u "
            "WHERE u.dtime BETWEEN '{dtime_from}' AND '{dtime_to}' "
            "AND u.platform='{platform}' "
            "GROUP BY u.cc_id, u.project_id, u.farm_id) t "
            "ON DUPLICATE KEY UPDATE usage_d.cost=usage_d.cost+t.cost"
        ).format(platform=platform, dtime_from=dtime_from, dtime_to=dtime_to)
        self.analytics_db.execute(query, retries=1)

    def recalculate_quarterly_budget(self, year, quarter):
        date_from, date_to = self.get_quarters_calendar().date_for_quarter(quarter, year)

        quarterly_budget = Quarterly_budget({'year': year, 'quarter': quarter})

        try:
            self.analytics_db.autocommit(False)
            self.analytics_db.execute(quarterly_budget.clear_query(), retries=1)

            query = (
                "SELECT YEAR(date) year, {quarter} quarter, 1 subject_type, "
                "HEX(cc_id) subject_id, SUM(cost) cumulativespend, date spentondate "
                "FROM usage_d "
                "WHERE date BETWEEN '{date_from}' AND '{date_to}' "
                "AND cc_id IS NOT NULL "
                "GROUP BY cc_id, date "
                "ORDER BY date ASC"
            ).format(quarter=quarter, date_from=date_from, date_to=date_to)
            cc_results = self.analytics_db.execute(query, retries=1)

            query = (
                "SELECT YEAR(date) year, {quarter} quarter, 2 subject_type, "
                "HEX(project_id) subject_id, SUM(cost) cumulativespend, date spentondate "
                "FROM usage_d "
                "WHERE date BETWEEN '{date_from}' AND '{date_to}' "
                "AND project_id IS NOT NULL "
                "GROUP BY project_id, date "
                "ORDER BY date ASC"
            ).format(quarter=quarter, date_from=date_from, date_to=date_to)
            prj_results = self.analytics_db.execute(query, retries=1)

            query = (
                "INSERT INTO quarterly_budget "
                "(year, quarter, subject_type, subject_id, cumulativespend) "
                "VALUES ({year}, {quarter}, {subject_type}, UNHEX('{subject_id}'), {cumulativespend}) "
                "ON DUPLICATE KEY UPDATE "
                "cumulativespend=cumulativespend+{cumulativespend}, "
                "spentondate=IF(budget>0 "
                "AND spentondate IS NULL "
                "AND cumulativespend>=budget, '{spentondate}', spentondate)")

            for result in cc_results + prj_results:
                self.analytics_db.execute(query.format(**result), retries=1)

            self.analytics_db.commit()
        except:
            self.analytics_db.rollback()
            raise
        finally:
            self.analytics_db.autocommit(True)

    def update_record(self, record, callback=None):
        try:
            LOG.debug('Update record: %s' % record)
            query = Usage_h(record).update_query()
            self.analytics_db.execute(query, retries=1)
        except:
            msg = 'Unable to update usage_h record: {record}'
            msg = msg.format(record=record)
            helper.handle_error(message=msg, level='error')
            raise
        if callable(callback):
            callback(record)


class QuartersCalendar(object):

    def __init__(self, calendar):
        self.calendar = calendar

        self._start_year_correction = []
        self._end_year_correction = []

        tmp = 0
        for i in range(4):
            if calendar[i] < calendar[i - 1]:
                tmp = 1
            self._start_year_correction.append(tmp)
        tmp = 0
        for i in range(4):
            if calendar[(i + 1) % 4] < calendar[i]:
                tmp = 1
            self._end_year_correction.append(tmp)

        if sum(self._start_year_correction) >= 2:
            self._start_year_correction = [i - 1 for i in self._start_year_correction]
            if sum(self._end_year_correction) >= 2:
                self._end_year_correction = [i - 1 for i in self._end_year_correction]

    def quarter_for_date(self, date):
        date = str(date)
        date = '-'.join(date.split('-')[-2:])
        for i in range(4):
            date_1 = self.calendar[i]
            date_2 = self.calendar[(i + 1) % 4]

            if date >= date_1 and date < date_2:
                return i + 1
            if date >= date_1 and date > date_2 and date_1 > date_2:
                return i + 1
            if date < date_1 and date < date_2 and date_1 > date_2:
                return i + 1

    def date_for_quarter(self, quarter, year=None):
        year = year or datetime.date.today().year

        start_y = year + self._start_year_correction[quarter - 1]
        start_m, start_d = map(int, self.calendar[quarter - 1].split('-'))
        start_date = datetime.date(start_y, start_m, start_d)

        next_year_correction = self._end_year_correction[quarter - 1]
        next_start_y = year + next_year_correction
        next_start_m, next_start_d = map(int, self.calendar[quarter % 4].split('-'))
        next_start_date = datetime.date(next_start_y, next_start_m, next_start_d)

        end_date = next_start_date + datetime.timedelta(seconds=-1)
        return start_date, end_date

    def dtime_for_quarter(self, quarter, year=None):
        start_date, end_date = self.date_for_quarter(quarter, year=year)
        start_dtime = datetime.datetime.combine(start_date, datetime.time())
        end_dtime = datetime.datetime.combine(end_date, datetime.time()).replace(
                hour=23, minute=59, second=59)
        return start_dtime, end_dtime

    def year_for_date(self, date):
        quarter_number = self.quarter_for_date(date)
        if '-'.join(str(date).split('-')[-2:]) >= self.calendar[quarter_number - 1]:
            year_correction = self._start_year_correction[quarter_number - 1]
        else:
            year_correction = self._end_year_correction[quarter_number - 1]
        year = int(str(date).split('-')[0])
        return year - year_correction

    def next_quarter(self, quarter, year):
        year += quarter // 4
        quarter = quarter % 4 + 1
        return quarter, year


class QuoteType(object):

    def __init__(self, value):
        self._value = value

    def __str__(self):
        if self._value is not None:
            return "'%s'" % str(self._value)
        else:
            return 'NULL'

    def __repr__(self):
        return self.__str__()


class NoQuoteType(object):

    def __init__(self, value):
        self._value = value

    def __str__(self):
        if self._value != '' and self._value is not None:
            return str(self._value)
        else:
            return 'NULL'

    def __repr__(self):
        return self.__str__()


class UUIDType(object):

    def __init__(self, value):
        self._value = value

    def __str__(self):
        if self._value != '' and self._value is not None:
            return "'%s'" % uuid.UUID(self._value).hex
        else:
            return 'NULL'

    def __repr__(self):
        return self.__str__()


class Table(dict):

    types = {}

    def __init__(self, record=None):
        if record is None:
            record = {}
        self._fill(record)

    def format(self):
        formatted = {}
        for k, v in self.items():
            if k in self.types:
                formatted[k] = self.types[k](v)
        return formatted

    def _fill(self, record):
        for k, v in record.items():
            if k in self.types:
                self[k] = v


class Usage_h(Table):

    types = {
        'usage_id': UUIDType,
        'account_id': NoQuoteType,
        'dtime': QuoteType,
        'platform': QuoteType,
        'url': QuoteType,
        'cloud_location': QuoteType,
        'usage_item': QuoteType,
        'os': NoQuoteType,
        'num': NoQuoteType,
        'cost': NoQuoteType,
        'env_id': NoQuoteType,
        'farm_id': NoQuoteType,
        'farm_role_id': NoQuoteType,
        'role_id': NoQuoteType,
        'cc_id': UUIDType,
        'project_id': UUIDType,
    }

    def __init__(self, record=None):
        super(Usage_h, self).__init__(record)
        if 'usage_id' not in self:
            try:
                formatted = self.format()
                unique = '; '.join(
                    [
                        str(formatted['account_id']).strip(), str(formatted['dtime']).strip(),
                        str(formatted['platform']).strip(),
                        str(formatted['cloud_location']).strip(),
                        str(formatted['usage_item']).strip(), str(formatted['os']).strip(),
                        str(formatted['cc_id']).strip(), str(formatted['project_id']).strip(),
                        str(formatted['env_id']).strip(), str(formatted['farm_id']).strip(),
                        str(formatted['farm_role_id']).strip(), str(formatted['url']).strip(),
                    ]
                )
                self['usage_id'] = uuid.uuid5(UUID, unique).hex
            except KeyError:
                msg = "Can't set managed usage_id for record: {}. Reason: {}"
                msg = msg.format(record, helper.exc_info())
                LOG.warning(msg)

    def insert_query(self):
        query = (
            "INSERT INTO usage_h "
            "(usage_id, account_id, dtime, platform, url, cloud_location, usage_item, "
            "os, cc_id, project_id, env_id, farm_id, farm_role_id, role_id, num, cost) "
            "VALUES (UNHEX({usage_id}), {account_id}, {dtime}, {platform}, {url}, "
            "{cloud_location}, UNHEX({usage_item}), {os}, UNHEX({cc_id}), UNHEX({project_id}), "
            "{env_id}, {farm_id}, {farm_role_id}, {role_id}, {num}, {cost}) "
            "ON DUPLICATE KEY UPDATE num=num+{num}, cost=cost+{cost}"
        ).format(**self.format())
        return query

    def update_query(self):
        query = (
            "INSERT INTO usage_h "
            "(usage_id, account_id, dtime, platform, url, cloud_location, usage_item, "
            "os, cc_id, project_id, env_id, farm_id, farm_role_id, role_id, num, cost) "
            "VALUES (UNHEX({usage_id}), {account_id}, {dtime}, {platform}, {url}, "
            "{cloud_location}, UNHEX({usage_item}), {os}, UNHEX({cc_id}), UNHEX({project_id}), "
            "{env_id}, {farm_id}, {farm_role_id}, {role_id}, {num}, {cost}) "
            "ON DUPLICATE KEY UPDATE cost={cost}"
        ).format(**self.format())
        return query

    @classmethod
    def insert_many_query(cls, records):
        values = ','.join([(
            "(UNHEX({usage_id}), {account_id}, {dtime}, {platform}, {url}, "
            "{cloud_location}, UNHEX({usage_item}), {os}, UNHEX({cc_id}), UNHEX({project_id}), "
            "{env_id}, {farm_id}, {farm_role_id}, {role_id}, {num}, {cost})"
        ).format(**Usage_h(record).format()) for record in records])
        query = (
            "INSERT INTO usage_h "
            "(usage_id, account_id, dtime, platform, url, cloud_location, usage_item, "
            "os, cc_id, project_id, env_id, farm_id, farm_role_id, role_id, num, cost) "
            "VALUES {} "
            "ON DUPLICATE KEY UPDATE num=num+VALUES(num), cost=cost+VALUES(cost)"
        ).format(values)
        return query


class Usage_types(Table):

    types = {
        'id': QuoteType,
        'cost_distr_type': NoQuoteType,
        'name': QuoteType,
        'display_name': QuoteType,
    }

    def insert_query(self):
        query = (
            "INSERT IGNORE INTO usage_types "
            "(id, cost_distr_type, name, display_name) "
            "VALUES (UNHEX({id}), {cost_distr_type}, {name}, {display_name})"
        ).format(**self.format())
        return query


class Usage_items(Table):

    types = {
        'id': QuoteType,
        'usage_type': QuoteType,
        'name': QuoteType,
        'display_name': QuoteType,
    }

    def insert_query(self):
        query = (
            "INSERT IGNORE INTO usage_items "
            "(id, usage_type, name, display_name) "
            "VALUES (UNHEX({id}), UNHEX({usage_type}), {name}, {display_name})"
        ).format(**self.format())
        return query


class Usage_servers_h(Table):

    types = {
        'instance_id': QuoteType,
        'usage_id': UUIDType,
        'server_id': UUIDType,
    }

    def insert_query(self):
        query = (
            "INSERT INTO usage_servers_h "
            "(usage_id, server_id, instance_id) "
            "VALUES (UNHEX({usage_id}), UNHEX({server_id}), {instance_id})"
        ).format(**self.format())
        return query

    @classmethod
    def insert_many_query(cls, records):
        values = ','.join([(
            "(UNHEX({usage_id}), UNHEX({server_id}), {instance_id})"
        ).format(**Usage_servers_h(record).format()) for record in records])
        query = (
            "INSERT INTO usage_servers_h "
            "(usage_id, server_id, instance_id) "
            "VALUES {}"
        ).format(values)
        return query


class Usage_h_tags(Table):

    types = {
        'tag_id': NoQuoteType,
        'value_id': QuoteType,
        'usage_id': UUIDType,
    }

    def insert_query(self):
        query = (
            "INSERT IGNORE INTO usage_h_tags "
            "(usage_id, tag_id, value_id) "
            "VALUES (UNHEX({usage_id}), {tag_id}, {value_id})"
        ).format(**self.format())
        return query

    @classmethod
    def insert_many_query(cls, records):
        values = ','.join([(
            "(UNHEX({usage_id}), {tag_id}, {value_id})"
        ).format(**Usage_h_tags(record).format()) for record in records])
        query = (
            "INSERT IGNORE INTO usage_h_tags "
            "(usage_id, tag_id, value_id) "
            "VALUES {}"
        ).format(values)
        return query


class Account_tag_values(Table):

    types = {
        'account_id': NoQuoteType,
        'tag_id': NoQuoteType,
        'value_id': QuoteType,
    }

    def insert_query(self):
        query = (
            "INSERT IGNORE INTO account_tag_values "
            "(account_id, tag_id, value_id) "
            "VALUES ({account_id}, {tag_id}, {value_id})"
        ).format(**self.format())
        return query

    @classmethod
    def insert_many_query(cls, records):
        values = ','.join([(
            "({account_id}, {tag_id}, {value_id})"
        ).format(**Account_tag_values(record).format()) for record in records])
        query = (
            "INSERT IGNORE INTO account_tag_values "
            "(account_id, tag_id, value_id) "
            "VALUES {}"
        ).format(values)
        return query


class Usage_d(Table):

    types = {
        'date': QuoteType,
        'platform': QuoteType,
        'cc_id': UUIDType,
        'project_id': UUIDType,
        'env_id': NoQuoteType,
        'farm_id': NoQuoteType,
        'cost': NoQuoteType,
    }

    def insert_query(self):
        query = (
            "INSERT INTO usage_d "
            "(date, platform, cc_id, project_id, env_id, farm_id, cost) "
            "VALUES ({date}, {platform}, IFNULL(UNHEX({cc_id}), ''), "
            "IFNULL(UNHEX({project_id}), ''), IFNULL({env_id}, 0), IFNULL({farm_id}, 0), {cost}) "
            "ON DUPLICATE KEY UPDATE cost=cost+{cost}"
        ).format(**self.format())
        return query

    @classmethod
    def insert_many_query(cls, records):
        values = ','.join([(
            "({date}, {platform}, IFNULL(UNHEX({cc_id}), ''), "
            "IFNULL(UNHEX({project_id}), ''), IFNULL({env_id}, 0), IFNULL({farm_id}, 0), {cost}) "
        ).format(**Usage_d(record).format()) for record in records])
        query = (
            "INSERT INTO usage_d "
            "(date, platform, cc_id, project_id, env_id, farm_id, cost) "
            "VALUES {} "
            "ON DUPLICATE KEY UPDATE cost=cost+VALUES(cost)"
        ).format(values)
        return query

    def delete_query(self):
        query = (
            "DELETE FROM usage_d "
            "WHERE platform={platform} "
            "AND date={date}"
        ).format(**self.format())
        return query


class NM_usage_d(Table):

    types = {
        'date': QuoteType,
        'platform': QuoteType,
        'cc_id': UUIDType,
        'env_id': NoQuoteType,
        'cost': NoQuoteType,
    }

    def insert_query(self):
        query = (
            "INSERT INTO nm_usage_d "
            "(date, platform, cc_id, env_id, cost) "
            "VALUES ({date}, {platform}, IFNULL(UNHEX({cc_id}), ''), {env_id}, {cost}) "
            "ON DUPLICATE KEY UPDATE cost=cost+{cost}"
        ).format(**self.format())
        return query

    def delete_query(self):
        query = (
            "DELETE FROM nm_usage_d "
            "WHERE platform={platform} "
            "AND date={date}"
        ).format(**self.format())
        return query


class Billing_records(Table):

    types = {
        'record_id': QuoteType,
        'record_date': QuoteType,
    }

    def insert_query(self):
        query = (
            "INSERT INTO aws_billing_records (record_id, date) "
            "VALUES ({record_id}, {record_date})"
        ).format(**self.format())
        return query

    @classmethod
    def insert_many_query(cls, records):
        values = ','.join([(
            "({record_id}, {record_date})"
        ).format(**Billing_records(record).format()) for record in records])
        query = (
            "INSERT INTO aws_billing_records (record_id, date) "
            "VALUES {}"
        ).format(values)
        return query


class Quarterly_budget(Table):

    types = {
        'year': NoQuoteType,
        'subject_type': NoQuoteType,
        'quarter': NoQuoteType,
        'cumulativespend': NoQuoteType,
        'spentondate': QuoteType,
        'subject_id': UUIDType,
    }

    def insert_query(self):
        query = (
            "INSERT quarterly_budget "
            "(year, quarter, subject_type, subject_id, cumulativespend) "
            "VALUES ({year}, {quarter}, {subject_type}, IFNULL(UNHEX({subject_id}), ''), "
            "{cumulativespend}) "
            "ON DUPLICATE KEY UPDATE "
            "cumulativespend=cumulativespend+{cumulativespend}, "
            "spentondate=IF(budget>0 AND spentondate IS NULL AND cumulativespend>=budget, {spentondate}, spentondate)"
        ).format(**self.format())
        return query

    @classmethod
    def insert_many_query(cls, records):
        values = ','.join([(
            "({year},{quarter},{subject_type},IFNULL(UNHEX({subject_id}),''),{cumulativespend},"
            "IF(year IS NULL AND quarter IS NULL, NULL, {spentondate}))"
        ).format(**Quarterly_budget(record).format()) for record in records])
        query = (
            "INSERT quarterly_budget "
            "(year, quarter, subject_type, subject_id, cumulativespend, spentondate) "
            "VALUES {} "
            "ON DUPLICATE KEY UPDATE "
            "cumulativespend=cumulativespend+VALUES(cumulativespend), "
            "spentondate=IF(budget>0 AND spentondate IS NULL AND cumulativespend>=budget, VALUES(spentondate), spentondate)"
        ).format(values)
        return query

    def clear_query(self):
        query = (
            "UPDATE quarterly_budget "
            "SET cumulativespend=0,spentondate=NULL "
            "WHERE year={year} "
            "AND quarter={quarter}"
        ).format(**self.format())
        return query


class NM_usage_h(Table):

    types = {
        'usage_id': UUIDType,
        'dtime': QuoteType,
        'platform': QuoteType,
        'url': QuoteType,
        'cloud_location': QuoteType,
        'instance_type': QuoteType,
        'os': NoQuoteType,
        'num': NoQuoteType,
        'cost': NoQuoteType,
    }

    def __init__(self, record=None):
        super(NM_usage_h, self).__init__(record)
        if 'usage_id' not in self:
            try:
                formatted = self.format()
                unique = '; '.join(
                    [
                        str(formatted['dtime']).strip(), str(formatted['platform']).strip(),
                        str(formatted['url']).strip(), str(formatted['cloud_location']).strip(),
                        str(formatted['instance_type']).strip(), str(formatted['os']).strip(),
                    ]
                )
                self['usage_id'] = uuid.uuid5(UUID, unique).hex
            except KeyError:
                msg = "Can't set not managed usage_id for record: {}. Reason: {}"
                msg = msg.format(record, helper.exc_info())
                LOG.warning(msg)

    def insert_query(self):
        query = (
            "INSERT INTO nm_usage_h "
            "(usage_id, dtime, platform, url, cloud_location, instance_type, os, num, cost) "
            "VALUES (UNHEX({usage_id}), {dtime}, {platform}, {url}, "
            "{cloud_location}, {instance_type}, {os}, {num}, {cost}) "
            "ON DUPLICATE KEY UPDATE num=num+{num},cost=cost+{cost}"
        ).format(**self.format())
        return query

    def update_query(self):
        query = (
            "UPDATE nm_usage_h "
            "SET cost={cost} "
            "WHERE usage_id=UNHEX({usage_id})"
        ).format(**self.format())
        return query


class NM_usage_servers_h(Table):

    types = {
        'instance_id': QuoteType,
        'usage_id': UUIDType,
    }

    def insert_query(self):
        query = (
            "INSERT INTO nm_usage_servers_h "
            "(usage_id, instance_id) "
            "VALUES (UNHEX({usage_id}), {instance_id})"
        ).format(**self.format())
        return query


class NM_subjects_h(Table):

    types = {
        'env_id': NoQuoteType,
        'account_id': NoQuoteType,
        'subject_id': UUIDType,
        'cc_id': UUIDType,
    }

    def insert_query(self):
        query = (
            "INSERT INTO nm_subjects_h "
            "(subject_id, env_id, cc_id, account_id) "
            "VALUES (UNHEX({subject_id}), {env_id}, UNHEX({cc_id}),{account_id})"
        ).format(**self.format())
        return query

    def subject_id_query(self):
        query = (
            "SELECT HEX(subject_id) as subject_id "
            "FROM nm_subjects_h "
            "WHERE env_id={env_id} "
            "AND cc_id=UNHEX({cc_id}) "
            "LIMIT 1"
        ).format(**self.format())
        return query


class NM_usage_subjects_h(Table):

    types = {
        'usage_id': UUIDType,
        'subject_id': UUIDType,
    }

    def insert_query(self):
        query = (
            "INSERT IGNORE INTO nm_usage_subjects_h "
            "(usage_id, subject_id) "
            "VALUES (UNHEX({usage_id}), UNHEX({subject_id}))"
        ).format(**self.format())
        return query
