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

import uuid
import json
import datetime
import threading

from scalrpy.util import helper
from scalrpy import LOG


# Do not change this UUID
UUID = uuid.UUID('99c3db97-5c41-4113-874c-159dab36a36c')


platforms = [
    'cloudstack',
    'ec2',
    'ecs',
    #'eucalyptus',
    'gce',
    'idcf',
    'openstack',
    'rackspacenguk',
    'rackspacengus',
    'ocs',
    'nebula',
]


class Credentials(dict):

    scheme = {
        'cloudstack': ['api_key', 'secret_key', 'api_url'],
        'ec2': ['access_key', 'secret_key', 'account_id'],
        'ecs': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'eucalyptus': ['access_key', 'secret_key', 'ec2_url', 'group'],
        'gce': ['service_account_name', 'key', 'project_id', 'json_key'],
        'idcf': ['api_key', 'secret_key', 'api_url'],
        'openstack': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'rackspacenguk': ['username', 'api_key', 'keystone_url'],
        'rackspacengus': ['username', 'api_key', 'keystone_url'],
        'ocs': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
        'nebula': ['username', 'api_key', 'password', 'keystone_url', 'tenant_name'],
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


    def _unique(self):
        if self.platform == 'ec2' and 'account_id' in self:
            unique_key = self['account_id']
        else:
            unique_key = ';'.join([self[k] for k in self.scheme[self.platform] if k in self])
        assert unique_key, 'unique_key'
        return unique_key


    @property
    def env_id(self):
        return self._env_id


    @property
    def platform(self):
        return self._platform


    @property
    def unique(self):
        return self._unique()



class Analytics(object):

    server_id_map = {
        'cloudstack': 'cloudstack.server_id',
        'ec2': 'ec2.instance-id',
        'ecs': 'openstack.server-id',
        'eucalyptus': 'euca.instance-id',
        'gce': 'gce.server-id',
        'idcf': 'cloudstack.server_id',
        'openstack': 'openstack.server-id',
        'rackspacenguk': 'rs.server-id',
        'rackspacengus': 'rs.server-id',
        'ocs': 'openstack.server-id',
        'nebula': 'openstack.server-id',
    }


    def __init__(self, scalr_db, analytics_db):
        self.scalr_db = scalr_db
        self.analytics_db = analytics_db

        self._quarters_calendar = None
        self._nm_usage_lock = threading.Lock()


    def get_quarters_calendar(self):
        if self._quarters_calendar is None:
            query = (
                    "SELECT value "
                    "FROM settings "
                    "WHERE id='budget_days'")
            self._quarters_calendar = QuartersCalendar(
                    json.loads(self.analytics_db.execute(query, retries=1)[0]['value']))
        return self._quarters_calendar


    def load_envs(self, limit=500):
        """
        :returns: generator
        """

        query = (
                "SELECT ce.id, c.id AS client_id "
                "FROM client_environments ce "
                "JOIN clients c ON ce.client_id=c.id "
                "WHERE c.status='Active' "
                "AND ce.status='Active' "
                "ORDER BY c.id ASC")
        return self.scalr_db.execute_with_limit(query, limit, retries=1)


    def load_creds(self, envs, platforms=None):
        out = list()
        envs_ids = [env['id'] for env in envs if env['id'] or env['id'] == 0]
        if not envs_ids:
            return out
        if platforms is None:
            platforms = Credentials.scheme.keys()
        for platform in platforms:
            names = ['%s.%s' % (platform, element) for element in Credentials.scheme[platform]]
            names += ['%s.is_enabled' % platform]
            if platform == 'ec2':
                names += ['ec2.account_type']
            if platform == 'eucalyptus':
                query = (
                        "SELECT env_id, `group`, name, value "
                        "FROM client_environment_properties "
                        "WHERE name in ({name}) "
                        "AND value IS NOT NULL "
                        "AND value!='' "
                        "AND env_id IN ({env_id})")
            else:
                query = (
                        "SELECT env_id, name, value "
                        "FROM client_environment_properties "
                        "WHERE name in ({name}) "
                        "AND value IS NOT NULL "
                        "AND value!='' "
                        "AND env_id IN ({env_id})")
            platform_query = query.format(
                    name=str(names)[1:-1],
                    env_id=str(envs_ids).replace('L', '')[1:-1])
            results = self.scalr_db.execute(platform_query, retries=1)
            if not results:
                continue
            tmp = dict()
            for result in results:
                name = result['name'].split('.')[-1]
                value = result['value']
                tmp.setdefault(result['env_id'], {})[name] = value
                if result['name'] == 'eucalyptus.ec2_url':
                    tmp[result['env_id']]['group'] = result['group']
            for env_id, data in tmp.iteritems():
                if data['is_enabled'] == '0':
                    continue
                out.append(Credentials(env_id, platform, data))
        return out


    def filter_creds(self, creds):
        tmp = dict()
        for cred in creds:
            try:
                unique = cred.unique
                if unique not in tmp:
                    tmp[unique] = {
                        'env_id': [cred.env_id],
                        'cred': cred,
                    }
                else:
                    tmp[unique]['env_id'].append(cred.env_id)
            except:
                msg = 'Unable to filter credentials, env_id: {0}, platform: {1}, reason: {2}'
                msg = msg.format(cred.env_id, cred.platform, helper.exc_info())
                LOG.warning(msg)
        return tmp.values()


    def _load_managed_servers_properties(self, servers):
        names = [
            'farm.created_by_id',
            'farm.project_id',
            'env.cc_id',
            'role.id',
        ]
        names += self.server_id_map.values()
        self.scalr_db.load_server_properties(servers, names)

        for server in servers:
            for k in self.server_id_map.values():
                if k not in server:
                    continue
                server['instance_id'] = server.pop(k)
                break
            try:
                server['user'] = server.pop('farm.created_by_id')
            except:
                server['user'] = None
            try:
                server['project_id'] = server.pop('farm.project_id')
            except:
                server['project_id'] = None
            try:
                server['cc_id'] = server.pop('env.cc_id')
            except:
                server['cc_id'] = None
            try:
                server['role_id'] = server.pop('role.id')
            except:
                server['role_id'] = None

            # cc_id
            # first attempt
            if not server['cc_id']:
                if server['project_id']:
                    query = (
                            "SELECT cc_id "
                            "FROM projects "
                            "WHERE project_id = UNHEX('{project_id}')"
                    ).format(project_id=server['project_id'])
                    result = self.scalr_db.execute(query, retries=1)
                    if result and result[0]['cc_id']:
                        server['cc_id'] = result[0]['cc_id']

            # cc_id
            # second attempt
            if not server['cc_id']:
                environment = {'id': server['env_id']}
                self.scalr_db.load_client_environment_properties([environment], ['cc_id'])
                if 'cc_id' in environment:
                    server['cc_id'] = environment['cc_id']

            if not server['cc_id']:
                msg = "Unable to load cc_id for server: {0}".format(server)
                LOG.error(msg)

        # servers table
        server_ids = list(set(_['server_id'] for _ in servers if _['server_id']))
        if not server_ids:
            return

        query = (
                "SELECT server_id, farm_id, farm_roleid as farm_role_id "
                "FROM servers "
                "WHERE server_id IN ({server_id})"
        ).format(server_id=str(server_ids)[1:-1])
        results = self.scalr_db.execute(query, retries=1)
        existing_server_ids = [_['server_id'] for _ in results]
        missing_server_ids = [_ for _ in server_ids if _ not in existing_server_ids]

        # servers_history
        if missing_server_ids:
            query = (
                    "SELECT server_id, farm_id, farm_roleid as farm_role_id, "
                    "cloud_server_id as instance_id "
                    "FROM servers_history "
                    "WHERE server_id IN ({server_id})"
            ).format(server_id=str(missing_server_ids)[1:-1])
            results += self.scalr_db.execute(query, retries=1)

        tmp = dict()
        for result in results:
            for k, v in result.iteritems():
                if k == 'server_id':
                    continue
                tmp.setdefault(result['server_id'], {}).update({k: v})
        for server in servers:
            if server['server_id'] not in tmp:
                continue
            for k, v in tmp[server['server_id']].iteritems():
                server[k] = v


    def _load_not_managed_servers_properties(self, servers):
        environments = [{'id': _['env_id']} for _ in servers if _['env_id'] or _['env_id'] == 0]
        self.scalr_db.load_client_environment_properties(environments, ['cc_id'])
        environments_map = dict((_['id'], _) for _ in environments)
        for server in servers:
            try:
                server['cc_id'] = environments_map[server['env_id']]['cc_id']
            except:
                server['cc_id'] = None


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


    def get_managed_servers(self, date, hour, limit=500, force=False):
        """
        :returns: generator
        """

        dtime_from, dtime_to = self._get_processing_dtime(date, hour)

        query = (
                "SELECT DISTINCT HEX(m.server_id) as server_id, m.instance_type, m.os,"
                "ps.account_id, ps.env_id, ps.platform, ps.cloud_location, ps.url "
                "FROM managed m "
                "JOIN poller_sessions ps ON m.sid=ps.sid "
                "WHERE ps.dtime BETWEEN '{dtime_from}' AND '{dtime_to}' "
                "ORDER BY m.sid, m.server_id"
        ).format(dtime_from=dtime_from, dtime_to=dtime_to)

        for servers in self.analytics_db.execute_with_limit(query, limit, retries=1):
            for server in servers:
                server['server_id'] = str(uuid.UUID(server['server_id']))
                server['dtime'] = dtime_from

            if force:
                filtered_servers = servers
            else:
                filtered_servers = list()
                for server in servers:
                    if not self._managed_server_exists(server):
                        filtered_servers.append(server)

            self._load_managed_servers_properties(filtered_servers)
            yield filtered_servers


    def _managed_server_exists(self, server):
        query = (
                "SELECT HEX(u.usage_id) "
                "FROM usage_h u "
                "JOIN usage_servers_h us "
                "ON u.usage_id=us.usage_id "
                "WHERE u.dtime='{dtime}' "
                "AND us.server_id=UNHEX('{server_id}')"
        ).format(dtime=server['dtime'], server_id=server['server_id'].replace('-', ''))
        return bool(self.analytics_db.execute(query, retries=1))


    def get_not_managed_servers(self, date, hour, limit=500, force=False):
        """
        :returns: generator
        """

        dtime_from, dtime_to = self._get_processing_dtime(date, hour)

        query = (
                "SELECT DISTINCT nm.instance_id, nm.instance_type, nm.os,"
                "ps.account_id, ps.env_id, ps.platform, ps.cloud_location, ps.url "
                "FROM notmanaged nm "
                "JOIN poller_sessions ps ON nm.sid=ps.sid "
                "WHERE ps.dtime BETWEEN '{dtime_from}' AND '{dtime_to}' "
                "ORDER BY nm.sid, nm.instance_id"
        ).format(dtime_from=dtime_from, dtime_to=dtime_to)

        for servers in self.analytics_db.execute_with_limit(query, limit, retries=1):
            for server in servers:
                server['dtime'] = dtime_from

            if force:
                filtered_servers = servers
            else:
                filtered_servers = list()
                for server in servers:
                    if not self._not_managed_server_exists(server):
                        filtered_servers.append(server)

            self._load_not_managed_servers_properties(servers)
            yield filtered_servers


    def _not_managed_server_exists(self, server):
        usage_id = NM_usage_h(server)['usage_id']
        instance_id = server['instance_id']
        query = (
                "SELECT HEX(nmu.usage_id) "
                "FROM nm_usage_h nmu "
                "JOIN nm_usage_servers_h nmus ON nmu.usage_id=nmus.usage_id "
                "WHERE nmu.usage_id=UNHEX('{usage_id}') "
                "AND nmus.instance_id='{instance_id}' "
                "LIMIT 1"
        ).format(usage_id=usage_id, instance_id=instance_id)
        return bool(self.analytics_db.execute(query, retries=1))


    def get_usage_h_records(self, date, hour, platform, limit=1000):
        """
        :returns: generator
        """

        dtime_from, dtime_to = self._get_processing_dtime(date, hour)

        query = (
                "SELECT HEX(usage_id) as usage_id, platform,url, cloud_location,"
                "account_id, instance_type, os,num,cost, cc_id, project_id, farm_id "
                "FROM usage_h "
                "WHERE dtime='{dtime}' "
                "AND platform='{platform}' "
                "ORDER BY usage_id"
        ).format(dtime=dtime_from, platform=platform)
        for results in self.analytics_db.execute_with_limit(query, limit, retries=1):
            for result in results:
                result['dtime'] = dtime_from
            yield list(results)


    def get_nm_usage_h_records(self, date, hour, platform, limit=1000):
        """
        :returns: generator
        """

        dtime_from, dtime_to = self._get_processing_dtime(date, hour)

        query = (
                "SELECT HEX(nm_u.usage_id) as usage_id, nm_u.platform, nm_u.url,"
                "nm_u.cloud_location, nm_u.instance_type, nm_u.os, nm_u.num, nm_u.cost,"
                "nm_s.account_id "
                "FROM nm_usage_h nm_u "
                "JOIN nm_usage_subjects_h nm_u_s ON nm_u_s.usage_id=nm_u.usage_id "
                "JOIN nm_subjects_h nm_s ON nm_s.subject_id=nm_u_s.subject_id "
                "WHERE nm_u.dtime='{dtime}' "
                "AND nm_u.platform='{platform}' "
                "ORDER BY nm_u.usage_id"
        ).format(dtime=dtime_from, platform=platform)
        for results in self.analytics_db.execute_with_limit(query, limit, retries=1):
            for result in results:
                result['dtime'] = dtime_from
            yield list(results)


    def _get_raw_prices(self, servers):
        """
        :returns: generator
        """

        date_map = {}
        for server in servers:
            date_map.setdefault(server['dtime'].date(), set([0])).add(server['account_id'])

        for date, accounts_ids in date_map.iteritems():
            accounts_ids = list(accounts_ids)
            base_query = (
                    "SELECT ph1.account_id, ph1.platform, ph1.cloud_location, ph1.url, "
                    "ph1.account_id, p1.instance_type, p1.cost,p1.os "
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
                    "AND ph1.account_id IN ({account_id})")
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
                    prices[account_id][platform_url][cloud_location].setdefault(instance_type, dict())
                    prices[account_id][platform_url][cloud_location][instance_type][os_type] = cost
                except KeyError:
                    msg = "Unable to get price from raw price, reason: {error}"
                    msg = msg.format(error=helper.exc_info())
                    LOG.warning(msg)
        return prices


    def get_cost_from_prices(self, server, prices):
        account_id = server['account_id']
        platform = server['platform']
        url = server['url']
        cloud_location = server['cloud_location']
        instance_type = server['instance_type']
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
                "Unable to get cost for account_id: {0}, platform: {1}, url: {2}, "
                "cloud_location: {3}, instance_type: {4}, os: {5}. Use 0.0, reason: {6}"
            ).format(account_id, platform, url, cloud_location,
                     instance_type, os, helper.exc_info())
            LOG.debug(msg)
        return cost


    def insert_managed_server(self, server):
        try:
            LOG.debug('Insert managed server: %s' % server)

            record = server.copy()
            if server['platform'] == 'gce':
                record['instance_id'] = server['server_id']
            record['date'] = server['dtime'].date()
            record['num'] = 1
            if server['cost'] is None:
                record['cost'] = 0
            if 'user' not in record:
                record['user'] = None

            value_id_map = {6: record['user']}

            self.analytics_db.autocommit(False)
            try:
                # insert in usage_h table
                usage_h = Usage_h(record)
                self.analytics_db.execute(usage_h.insert_query(), retries=1)

                record['usage_id'] = usage_h['usage_id']

                # insert in usage_servers_h table
                usage_servers_h = Usage_servers_h(record)
                self.analytics_db.execute(usage_servers_h.insert_query(), retries=1)

                for tag_id, value_id in value_id_map.iteritems():
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
                record['year'] = record['date'].year
                record['quarter'] = self.get_quarters_calendar().quarter_for_date(record['date'])
                record['cumulativespend'] = record['cost']
                record['spentondate'] = server['dtime']
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
            msg = "Unable to insert managed server: {server}, reason: {error}"
            msg = msg.format(server=server, error=helper.exc_info())
            LOG.error(msg)
            raise


    def _find_subject_id(self, record):
        nm_subjects_h = NM_subjects_h(record)
        r = self.analytics_db.execute(nm_subjects_h.subject_id_query(), retries=1)
        return r[0]['subject_id'] if r else None


    def insert_not_managed_server(self, server):
        try:
            LOG.debug('Insert not managed server: %s' % server)

            record = server.copy()
            record['date'] = server['dtime'].date()
            record['num'] = 1
            if server['cost'] is None:
                record['cost'] = 0

            with self._nm_usage_lock:
                server_exists = self._not_managed_server_exists(record)
                subject_id = self._find_subject_id(record)

                self.analytics_db.autocommit(False)
                try:
                    if subject_id:
                        record['subject_id'] = uuid.UUID(subject_id).hex
                    else:
                        record['subject_id'] = uuid.uuid4().hex
                        nm_subjects_h = NM_subjects_h(record)
                        self.analytics_db.execute(nm_subjects_h.insert_query(), retries=1)

                    if not server_exists:
                        nm_usage_h = NM_usage_h(record)
                        record['usage_id'] = nm_usage_h['usage_id']
                        self.analytics_db.execute(nm_usage_h.insert_query(), retries=1)

                        nm_usage_subjects_h = NM_usage_subjects_h(record)
                        self.analytics_db.execute(nm_usage_subjects_h.insert_query(), retries=1)

                        nm_usage_servers_h = NM_usage_servers_h(record)
                        self.analytics_db.execute(nm_usage_servers_h.insert_query(), retries=1)

                        # insert nm_usage_d table
                        nm_usage_d = NM_usage_d(record)
                        self.analytics_db.execute(nm_usage_d.insert_query(), retries=1)

                    self.analytics_db.commit()
                except:
                    self.analytics_db.rollback()
                    raise
                finally:
                    self.analytics_db.autocommit(True)
        except:
            msg = "Unable to insert not managed server: {server}, reason: {error}"
            msg = msg.format(server=server, error=helper.exc_info())
            LOG.error(msg)
            raise


    def fill_farm_usage_d(self, date, hour, platform=None):
        query = (
                "INSERT INTO farm_usage_d "
                "(account_id, farm_role_id, instance_type, cc_id, project_id, date, "
                "platform, cloud_location, env_id, "
                "farm_id, role_id, cost, min_instances, max_instances, instance_hours, working_hours) "
                "SELECT account_id, farm_role_id, instance_type, cc_id, project_id, '{date}', "
                "platform, cloud_location, env_id, farm_id, role_id, "
                "cost, min_instances, max_instances, instance_hours, working_hours "
                "FROM ("
                "SELECT account_id, farm_role_id, instance_type, "
                "IFNULL(cc_id, '') cc_id, IFNULL(project_id, '') project_id, platform, "
                "cloud_location, env_id, IFNULL(farm_id, 0) farm_id, role_id, SUM(cost) cost, "
                "(CASE WHEN COUNT(dtime)={hour} THEN MIN(num) ELSE 0 END) min_instances, "
                "MAX(num) max_instances, SUM(num) instance_hours, COUNT(dtime) working_hours "
                "FROM usage_h uh "
                "WHERE uh.dtime BETWEEN '{date} 00:00:00' AND '{date} 23:59:59'"
                "AND uh.farm_id > 0 AND uh.farm_role_id > 0 "
        )

        if platform:
            query += "AND platform='{platform}' ".format(platform=platform)

        query += (
                "GROUP BY uh.account_id, uh.farm_role_id, uh.instance_type "
                ") t "
                "ON DUPLICATE KEY UPDATE "
                "cost = t.cost, "
                "min_instances = t.min_instances, "
                "max_instances = t.max_instances, "
                "instance_hours = t.instance_hours, "
                "working_hours = t.working_hours"
        )
        query = query.format(date=date, hour=hour + 1)
        self.analytics_db.execute(query, retries=1)


    def recalculate_usage_d(self, date, platform):
        dtime_from, dtime_to = self._get_processing_dtime(date)

        query = ("SELECT MIN(dtime) dtime FROM usage_h")
        min_dtime = self.analytics_db.execute(query, retries=1)[0]['dtime']
        if min_dtime and min_dtime.date() > dtime_from.date():
            raise Exception("Attempt to recalculate the non-existent or deleted data")

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


    def recalculate_nm_usage_d(self, date, platform):
        dtime_from, dtime_to = self._get_processing_dtime(date)

        query = ("SELECT MIN(dtime) dtime FROM nm_usage_h")
        min_dtime = self.analytics_db.execute(query, retries=1)[0]['dtime']
        if min_dtime and min_dtime.date() > dtime_from.date():
            raise Exception("Attempt to recalculate the non-existent or deleted data")

        nm_usage_d = NM_usage_d({'platform': platform, 'date': dtime_from.date()})
        self.analytics_db.execute(nm_usage_d.delete_query(), retries=1)
        query = (
                "INSERT INTO nm_usage_d "
                "(date, platform, cc_id, env_id, cost) "
                "SELECT date, platform, cc_id, env_id, cost "
                "FROM (SELECT DATE(nu.dtime) date, nu.platform, s.cc_id, "
                "s.env_id, SUM(nu.cost) cost "
                "FROM nm_subjects_h s "
                "JOIN nm_usage_subjects_h us ON us.subject_id=s.subject_id "
                "JOIN nm_usage_h nu ON nu.usage_id=us.usage_id "
                "WHERE nu.dtime BETWEEN '{dtime_from}' AND '{dtime_to}' "
                "AND nu.platform='{platform}' "
                "GROUP BY s.cc_id, s.env_id) t "
                "ON DUPLICATE KEY UPDATE nm_usage_d.cost=nm_usage_d.cost+t.cost"
        ).format(platform=platform, dtime_from=dtime_from, dtime_to=dtime_to)
        self.analytics_db.execute(query, retries=1)


    def recalculate_quarterly_budget(self, year, quarter):
        date_from, date_to = self.get_quarters_calendar().date_for_quarter(quarter, year)

        quarterly_budget = Quarterly_budget({'year': year, 'quarter': quarter})
        self.analytics_db.execute(quarterly_budget.clear_query(), retries=1)

        query = (
                "SELECT YEAR(date) year, {quarter} quarter, 1 subject_type, "
                "HEX(cc_id) subject_id, SUM(cost) cumulativespend, date spentondate "
                "FROM usage_d "
                "WHERE date BETWEEN '{date_from}' AND '{date_to}' "
                "AND cc_id IS NOT NULL "
                "GROUP BY cc_id,date "
                "ORDER BY date ASC"
        ).format(quarter=quarter, date_from=date_from, date_to=date_to)
        cc_results = self.analytics_db.execute(query, retries=1)

        query = (
                "SELECT YEAR(date) year, {quarter} quarter, 2 subject_type, "
                "HEX(project_id) subject_id, SUM(cost) cumulativespend, date spentondate "
                "FROM usage_d "
                "WHERE date BETWEEN '{date_from}' AND '{date_to}' "
                "AND project_id IS NOT NULL "
                "GROUP BY project_id,date "
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


    def update_usage_h(self, record):
        try:
            query = Usage_h(record).update_query()
            self.analytics_db.execute(query, retries=1)
        except:
            msg = 'Unable to update usage_h record: {record}, reason: {error}'
            msg = msg.format(record=record, error=helper.exc_info())
            LOG.error(msg)
            raise


    def update_nm_usage_h(self, record):
        try:
            query = NM_usage_h(record).update_query()
            self.analytics_db.execute(query, retries=1)
        except:
            msg = 'Unable to update nm_usage_h record: {record}, reason: {error}'
            msg = msg.format(record=record, error=helper.exc_info())
            LOG.error(msg)
            raise



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


    def year_for_date(self, date):
        quarter_number = self.quarter_for_date(date)
        if '-'.join(str(date).split('-')[-2:]) >= self.calendar[quarter_number - 1]:
            year_correction = self._start_year_correction[quarter_number - 1]
        else:
            year_correction = self._end_year_correction[quarter_number - 1]
        year = int(str(date).split('-')[0])
        return year - year_correction



class Table(dict):

    def __init__(self):
        self._types = {}


    def _format(self):
        formatted = {}
        for k, v in self.iteritems():
            if k in self._types:
                formatted[k] = self._types[k](v)
        return formatted


    def _fill(self, record):
        record = record or {}
        for k, v in record.iteritems():
            if k in self._types:
                self[k] = v



class Usage_h(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'usage_id': UUIDType,
                'account_id': NoQuoteType,
                'dtime': QuoteType,
                'platform': QuoteType,
                'url': QuoteType,
                'cloud_location': QuoteType,
                'instance_type': QuoteType,
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
        self._fill(record)
        if 'usage_id' not in self:
            try:
                formatted = self._format()
                unique = '; '.join(
                    [
                        str(formatted['account_id']).strip(), str(formatted['dtime']).strip(),
                        str(formatted['platform']).strip(), str(formatted['cloud_location']).strip(),
                        str(formatted['instance_type']).strip(), str(formatted['os']).strip(),
                        str(formatted['cc_id']).strip(), str(formatted['project_id']).strip(),
                        str(formatted['env_id']).strip(), str(formatted['farm_id']).strip(),
                        str(formatted['farm_role_id']).strip(), str(formatted['url']).strip(),
                    ]
                )
                self['usage_id'] = uuid.uuid5(UUID, unique).hex
            except KeyError:
                msg = "Can't set managed usage_id for record: {record}, reason: {error}"
                msg = msg.format(record=record, error=helper.exc_info())
                LOG.warning(msg)


    def insert_query(self):
        query = (
                "INSERT INTO usage_h "
                "(usage_id, account_id, dtime, platform, url, cloud_location, instance_type,"
                "os, cc_id, project_id, env_id, farm_id, farm_role_id, role_id, num, cost) "
                "VALUES (UNHEX({usage_id}), {account_id}, {dtime}, {platform}, {url}, "
                "{cloud_location}, {instance_type}, {os}, UNHEX({cc_id}), UNHEX({project_id}), "
                "{env_id}, {farm_id}, {farm_role_id}, {role_id}, {num}, {cost}) "
                "ON DUPLICATE KEY UPDATE num=num+1,cost=cost+{cost}"
        ).format(**self._format())
        return query


    def update_query(self):
        query = (
                "UPDATE usage_h "
                "SET cost={cost} "
                "WHERE usage_id=UNHEX({usage_id})"
        ).format(**self._format())
        return query



class Usage_servers_h(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'instance_id': QuoteType,
                'usage_id': UUIDType,
                'server_id': UUIDType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT INTO usage_servers_h "
                "(usage_id, server_id, instance_id) "
                "VALUES (UNHEX({usage_id}), UNHEX({server_id}), {instance_id})"
        ).format(**self._format())
        return query



class Usage_h_tags(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'tag_id': NoQuoteType,
                'value_id': QuoteType,
                'usage_id': UUIDType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT IGNORE INTO usage_h_tags "
                "(usage_id, tag_id, value_id) "
                "VALUES (UNHEX({usage_id}), {tag_id}, {value_id})"
        ).format(**self._format())
        return query



class Account_tag_values(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'account_id': NoQuoteType,
                'tag_id': NoQuoteType,
                'value_id': QuoteType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT IGNORE INTO account_tag_values "
                "(account_id, tag_id, value_id) "
                "VALUES ({account_id}, {tag_id}, {value_id})"
        ).format(**self._format())
        return query



class Usage_d(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'date': QuoteType,
                'platform': QuoteType,
                'cc_id': UUIDType,
                'project_id': UUIDType,
                'env_id': NoQuoteType,
                'farm_id': NoQuoteType,
                'cost': NoQuoteType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT INTO usage_d "
                "(date, platform, cc_id, project_id, env_id, farm_id, cost) "
                "VALUES ({date}, {platform}, IFNULL(UNHEX({cc_id}), ''), "
                "IFNULL(UNHEX({project_id}), ''), IFNULL({env_id}, 0), IFNULL({farm_id}, 0), {cost}) "
                "ON DUPLICATE KEY UPDATE cost=cost+{cost}"
        ).format(**self._format())
        return query


    def delete_query(self):
        query = (
                "DELETE FROM usage_d "
                "WHERE platform={platform} "
                "AND date={date}"
        ).format(**self._format())
        return query



class NM_usage_d(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'date': QuoteType,
                'platform': QuoteType,
                'cc_id': UUIDType,
                'env_id': NoQuoteType,
                'cost': NoQuoteType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT INTO nm_usage_d "
                "(date, platform, cc_id, env_id, cost) "
                "VALUES ({date}, {platform}, IFNULL(UNHEX({cc_id}), ''), {env_id}, {cost}) "
                "ON DUPLICATE KEY UPDATE cost=cost+{cost}"
        ).format(**self._format())
        return query


    def delete_query(self):
        query = (
                "DELETE FROM nm_usage_d "
                "WHERE platform={platform} "
                "AND date={date}"
        ).format(**self._format())
        return query



class Quarterly_budget(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'year': NoQuoteType,
                'subject_type': NoQuoteType,
                'quarter': NoQuoteType,
                'cumulativespend': NoQuoteType,
                'spentondate': QuoteType,
                'subject_id': UUIDType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT quarterly_budget "
                "(year, quarter, subject_type, subject_id, cumulativespend) "
                "VALUES ({year}, {quarter}, {subject_type}, IFNULL(UNHEX({subject_id}), ''), "
                "{cumulativespend}) "
                "ON DUPLICATE KEY UPDATE "
                "cumulativespend=cumulativespend+{cumulativespend}, "
                "spentondate=IF(budget>0 AND spentondate IS NULL AND cumulativespend>=budget, {spentondate}, spentondate)"
        ).format(**self._format())
        return query


    def clear_query(self):
        query = (
                "UPDATE quarterly_budget "
                "SET cumulativespend=0,spentondate=NULL "
                "WHERE year={year} "
                "AND quarter={quarter}"
        ).format(**self._format())
        return query



class NM_usage_h(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
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
        self._fill(record)
        if 'usage_id' not in self:
            try:
                formatted = self._format()
                unique = '; '.join(
                    [
                        str(formatted['dtime']).strip(), str(formatted['platform']).strip(),
                        str(formatted['url']).strip(), str(formatted['cloud_location']).strip(),
                        str(formatted['instance_type']).strip(), str(formatted['os']).strip(),
                    ]
                )
                self['usage_id'] = uuid.uuid5(UUID, unique).hex
            except KeyError:
                msg = "Can't set not managed usage_id for record: {record}, reason: {error}"
                msg = msg.format(record=record, error=helper.exc_info())
                LOG.warning(msg)


    def insert_query(self):
        query = (
                "INSERT INTO nm_usage_h "
                "(usage_id, dtime, platform, url, cloud_location, instance_type, os, num, cost) "
                "VALUES (UNHEX({usage_id}), {dtime}, {platform}, {url}, "
                "{cloud_location}, {instance_type}, {os}, {num}, {cost}) "
                "ON DUPLICATE KEY UPDATE num=num+1,cost=cost+{cost}"
        ).format(**self._format())
        return query


    def update_query(self):
        query = (
                "UPDATE nm_usage_h "
                "SET cost={cost} "
                "WHERE usage_id=UNHEX({usage_id})"
        ).format(**self._format())
        return query



class NM_usage_servers_h(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'instance_id': QuoteType,
                'usage_id': UUIDType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT INTO nm_usage_servers_h "
                "(usage_id, instance_id) "
                "VALUES (UNHEX({usage_id}), {instance_id})"
        ).format(**self._format())
        return query



class NM_subjects_h(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'env_id': NoQuoteType,
                'account_id': NoQuoteType,
                'subject_id': UUIDType,
                'cc_id': UUIDType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT INTO nm_subjects_h "
                "(subject_id, env_id, cc_id, account_id) "
                "VALUES (UNHEX({subject_id}), {env_id}, UNHEX({cc_id}),{account_id})"
        ).format(**self._format())
        return query


    def subject_id_query(self):
        query = (
                "SELECT HEX(subject_id) as subject_id "
                "FROM nm_subjects_h "
                "WHERE env_id={env_id} "
                "AND cc_id=UNHEX({cc_id}) "
                "LIMIT 1"
        ).format(**self._format())
        return query



class NM_usage_subjects_h(Table):

    def __init__(self, record=None):
        Table.__init__(self)
        self._types = {
                'usage_id': UUIDType,
                'subject_id': UUIDType,
        }
        self._fill(record)


    def insert_query(self):
        query = (
                "INSERT IGNORE INTO nm_usage_subjects_h "
                "(usage_id, subject_id) "
                "VALUES (UNHEX({usage_id}), UNHEX({subject_id}))"
        ).format(**self._format())
        return query



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
