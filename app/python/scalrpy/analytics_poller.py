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

from gevent import monkey
monkey.patch_all()

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.join(cwd, '..')
sys.path.insert(0, scalrpy_dir)

import ssl
import time
import uuid
import json
import socket
import gevent
import urllib2
import greenlet
import urlparse
import binascii

import boto.ec2
import boto.exception
import boto.ec2.regioninfo

import oauth2client.client

import libcloud.common.types
from libcloud.compute.types import Provider, NodeState
from libcloud.compute.providers import get_driver
import libcloud.security

libcloud.security.VERIFY_SSL_CERT = False

import httplib2
import googleapiclient
from googleapiclient.discovery import build
from oauth2client.client import SignedJwtAssertionCredentials

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import analytics
from scalrpy.util import cryptotool
from scalrpy.util import application

from scalrpy import LOG
from scalrpy import exceptions


helper.patch_gevent()


app = None


os.environ['EC2_USE_SIGV4'] = 'TRUE'


@helper.retry(1, 5, urllib2.URLError, socket.timeout)
def _libcloud_list_locations(driver):
    return driver.list_locations()


@helper.retry(1, 5, urllib2.URLError, socket.timeout)
def _libcloud_list_nodes(driver):
    return driver.list_nodes()


@helper.retry(1, 5, urllib2.URLError, socket.timeout)
def _ec2_get_only_instances(ec2conn):
    return ec2conn.get_only_instances(filters={'instance-state-name': 'running'})


@helper.retry(1, 5, urllib2.URLError, socket.timeout)
def _libcloud_get_service_catalog(driver):
    return driver.connection.get_service_catalog()


def _handle_exception(e, msg):
    if isinstance(e, boto.exception.EC2ResponseError) and e.status in (401, 403):
        LOG.warning(msg)
    elif isinstance(e, (libcloud.common.types.InvalidCredsError,
                        libcloud.common.types.LibcloudError,
                        libcloud.common.types.MalformedResponseError,
                        oauth2client.client.AccessTokenRefreshError,
                        gevent.timeout.Timeout,
                        socket.timeout,
                        socket.gaierror)):
        LOG.warning(msg)
    elif isinstance(e, socket.error) and e.errno in (110, 111, 113):
        LOG.warning(msg)
    elif isinstance(e, googleapiclient.errors.HttpError) and e.resp['status'] in ('403',):
        LOG.warning(msg)
    elif isinstance(e, ssl.SSLError):
        LOG.warning(msg)
    elif isinstance(e, greenlet.GreenletExit):
        pass
    elif 'userDisabled' in str(e):
        LOG.warning(msg)
    elif isinstance(e, exceptions.MissingCredentialsError):
        LOG.debug(msg)
    else:
        LOG.exception(msg)


def _ec2_region(region, cred):
    try:
        access_key = cryptotool.decrypt_scalr(app.crypto_key, cred['access_key'])
        secret_key = cryptotool.decrypt_scalr(app.crypto_key, cred['secret_key'])
        kwds = {
            'aws_access_key_id': access_key,
            'aws_secret_access_key': secret_key
        }
        proxy_settings = app.proxy_settings[cred.platform]
        kwds['proxy'] = proxy_settings.get('host', None)
        kwds['proxy_port'] = proxy_settings.get('port', None)
        kwds['proxy_user'] = proxy_settings.get('user', None)
        kwds['proxy_pass'] = proxy_settings.get('pass', None)

        conn = boto.ec2.connect_to_region(region, **kwds)
        cloud_nodes = _ec2_get_only_instances(conn)
        timestamp = int(time.time())
        nodes = list()
        for cloud_node in cloud_nodes:
            node = {
                'instance_id': cloud_node.id,
                'instance_type': cloud_node.instance_type,
                'os': cloud_node.platform if cloud_node.platform else 'linux'
            }
            nodes.append(node)
        return {
            'region': region,
            'timestamp': timestamp,
            'nodes': nodes
        } if nodes else dict()
    except:
        e = sys.exc_info()[1]
        msg = 'platform: {platform}, region: {region}, env_id: {env_id}, reason: {error}'
        msg = msg.format(platform=cred.platform, region=region, env_id=cred.env_id,
                         error=helper.exc_info(where=False))
        _handle_exception(e, msg)


def ec2(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """
    result = list()

    regions = {
        'regular': [
            'us-east-1',
            'us-west-1',
            'us-west-2',
            'eu-west-1',
            'eu-central-1',
            'ap-southeast-1',
            'ap-southeast-2',
            'ap-northeast-1',
            'sa-east-1',
        ],
        'gov-cloud': ['us-gov-west-1'],
        'cn-cloud': ['cn-north-1'],
    }.get(cred.get('account_type'))
    if not regions:
        msg = 'Unsupported account type for ec2 platform: {}'.format(cred.get('account_type'))
        raise Exception(msg)

    app.pool.wait()
    async_results = dict(
        (region, app.pool.apply_async(_ec2_region, args=(region, cred,)))
        for region in regions
    )
    gevent.sleep(0)  # force switch
    timeout = app.config['cloud_connection_timeout']
    for region, async_result in async_results.iteritems():
        try:
            region_nodes = async_result.get(timeout=timeout)
            if region_nodes:
                result.append(region_nodes)
        except gevent.timeout.Timeout:
            async_result.kill()
            msg = 'platform: {platform}, region: {region}, env_id: {env_id}. Reason: timeout'
            msg = msg.format(platform=cred.platform, region=region, env_id=cred.env_id)
            LOG.warning(msg)
    return result


def _cloudstack(cred):
    try:
        result = list()
        api_key = cryptotool.decrypt_scalr(app.crypto_key, cred['api_key'])
        secret_key = cryptotool.decrypt_scalr(app.crypto_key, cred['secret_key'])
        api_url = cryptotool.decrypt_scalr(app.crypto_key, cred['api_url'])
        url = urlparse.urlparse(api_url)
        splitted_netloc = url.netloc.split(':')
        host = splitted_netloc[0]
        try:
            port = splitted_netloc[1]
        except:
            port = 443 if url.scheme == 'https' else None
        path = url.path
        secure = url.scheme == 'https'

        cls = get_driver(Provider.CLOUDSTACK)
        driver = cls(key=api_key, secret=secret_key, host=host, port=port, path=path, secure=secure)
        locations = driver.list_locations()
        cloud_nodes = _libcloud_list_nodes(driver)
        timestamp = int(time.time())
        for location in locations:
            nodes = list()
            for cloud_node in cloud_nodes:
                if cloud_node.state != NodeState.RUNNING or cloud_node.extra['zone_id'] != location.id:
                    continue
                node = {
                    'instance_id': cloud_node.id,
                    'instance_type': cloud_node.extra['size_id'],
                    'os': None
                }
                nodes.append(node)
            if nodes:
                result.append(
                    {
                        'region': location.name,
                        'timestamp': timestamp,
                        'nodes': nodes
                    }
                )
        return result
    except:
        e = sys.exc_info()[1]
        msg = 'platform: {platform}, env_id: {env_id}, reason: {error}'
        msg = msg.format(platform=cred.platform, env_id=cred.env_id,
                         error=helper.exc_info(where=False))
        _handle_exception(e, msg)


def cloudstack(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """
    result = list()

    app.pool.wait()
    async_result = app.pool.apply_async(_cloudstack, args=(cred,))
    gevent.sleep(0)  # force switch
    try:
        result = async_result.get(timeout=app.config['cloud_connection_timeout'])
    except gevent.timeout.Timeout:
        async_result.kill()
        msg = 'platform: {platform}, env_id: {env_id}. Reason: timeout'
        msg = msg.format(platform=cred.platform, env_id=cred.env_id)
        LOG.warning(msg)
    return result


def idcf(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return cloudstack(cred)


def _gce_key(cred):
    if cred.get('json_key'):
        key = json.loads(cryptotool.decrypt_scalr(app.crypto_key, cred['json_key']))['private_key']
    else:
        key = cryptotool.decrypt_scalr(app.crypto_key, cred['key'])
        # convert pkcs12 to rsa
        out, err, ret_code = helper.call(
            "openssl pkcs12 -nodes -nocerts -passin pass:notasecret | openssl rsa",
            input=binascii.a2b_base64(key),
            shell=True
        )
        key = out.strip()
    return key


def _gce_conn(cred, key=None):
    service_account_name = cryptotool.decrypt_scalr(app.crypto_key, cred['service_account_name'])
    if key is None:
        key = _gce_key(cred)

    signed_jwt_assert_cred = SignedJwtAssertionCredentials(
        service_account_name,
        key,
        ['https://www.googleapis.com/auth/compute']
    )
    http = httplib2.Http()
    http = signed_jwt_assert_cred.authorize(http)
    return build('compute', 'v1', http=http), http


def _gce_zone(zone, key, cred):
    try:
        conn, http = _gce_conn(cred, key=key)
        project_id = cryptotool.decrypt_scalr(app.crypto_key, cred['project_id'])
        request = conn.instances().list(
            project=project_id,
            zone=zone,
            filter='status eq RUNNING'
        )
        resp = request.execute(http=http)
        timestamp = int(time.time())
        cloud_nodes = resp['items'] if 'items' in resp else []
        nodes = list()
        for cloud_node in cloud_nodes:
            node = {
                'instance_id': cloud_node['id'],
                'server_name': cloud_node['name'],
                'instance_type': cloud_node['machineType'].split('/')[-1],
                'os': None,
            }
            for item in cloud_node['metadata'].get('items', []):
                meta = dict(tuple(element.split('=', 1))
                            for element in item['value'].split(';') if '=' in element)
                if 'serverid' in meta:
                    node['server_id'] = meta['serverid']
                if 'env_id' in meta:
                    node['env_id'] = int(meta['env_id'])
                    break
            nodes.append(node)
        return {
            'region': zone,
            'timestamp': timestamp,
            'nodes': nodes
        } if nodes else dict()
    except:
        e = sys.exc_info()[1]
        msg = 'platform: {platform}, zone: {zone}, env_id: {env_id}, reason: {error}'
        msg = msg.format(platform=cred.platform, zone=zone, env_id=cred.env_id,
                         error=helper.exc_info(where=False))
        _handle_exception(e, msg)


def gce(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """
    result = list()

    project_id = cryptotool.decrypt_scalr(app.crypto_key, cred['project_id'])
    key = _gce_key(cred)
    conn, http = _gce_conn(cred, key=key)
    request = conn.zones().list(project=project_id)
    resp = request.execute(http=http)
    zones = [_['name'] for _ in resp['items']] if 'items' in resp else []

    app.pool.wait()
    async_results = dict(
        (zone, app.pool.apply_async(_gce_zone, args=(zone, key, cred,)))
        for zone in zones
    )
    gevent.sleep(0)  # force switch
    for zone, async_result in async_results.iteritems():
        try:
            zone_nodes = async_result.get(timeout=app.config['cloud_connection_timeout'] + 1)
            if zone_nodes:
                result.append(zone_nodes)
        except gevent.timeout.Timeout:
            async_result.kill()
            msg = 'platform: {platform}, zone: {zone}, env_id: {env_id}. Reason: timeout'
            msg = msg.format(platform=cred.platform, zone=zone, env_id=cred.env_id)
            LOG.warning(msg)
    return result


def _openstack_cred(cred):
    username = cryptotool.decrypt_scalr(app.crypto_key, cred['username'])
    if 'password' in cred:
        password = cryptotool.decrypt_scalr(app.crypto_key, cred['password'])
        auth_version = '2.0_password'
    else:
        password = cryptotool.decrypt_scalr(app.crypto_key, cred['api_key'])
        auth_version = '2.0_apikey'
    keystone_url = cryptotool.decrypt_scalr(app.crypto_key, cred['keystone_url'])
    if not keystone_url.rstrip('/').endswith('/tokens'):
        keystone_url = os.path.join(keystone_url, 'tokens')
    if 'tenant_name' in cred:
        tenant_name = cryptotool.decrypt_scalr(app.crypto_key, cred['tenant_name'])
    else:
        tenant_name = None
    return username, password, auth_version, keystone_url, tenant_name


def _openstack_region(provider, service_name, region, cred):
    try:
        username, password, auth_version, keystone_url, tenant_name = _openstack_cred(cred)
        url = urlparse.urlparse(keystone_url)
        service_type = 'compute'

        cls = get_driver(provider)
        driver = cls(
            username,
            password,
            ex_force_auth_url=url.geturl(),
            ex_tenant_name=tenant_name,
            ex_force_auth_version=auth_version,
            ex_force_service_region=region,
            ex_force_service_type=service_type,
            ex_force_service_name=service_name,
        )
        driver.connection.set_http_proxy(proxy_url=app.proxy_url[cred.platform])
        cloud_nodes = _libcloud_list_nodes(driver)
        try:
            cloud_nodes = [node for node in cloud_nodes
                           if node.driver.region.upper() == region.upper()]
        except AttributeError:
            pass
        timestamp = int(time.time())
        nodes = list()
        for cloud_node in cloud_nodes:
            if cloud_node.state != NodeState.RUNNING:
                continue
            node = {
                'instance_id': cloud_node.id,
                'instance_type': cloud_node.extra['flavorId'],
                'os': None
            }
            nodes.append(node)
        return {
            'region': region,
            'timestamp': timestamp,
            'nodes': nodes
        } if nodes else dict()
    except:
        e = sys.exc_info()[1]
        msg = (
            'platform: {platform}, env_id: {env_id}, url: {url}, '
            'tenant_name: {tenant_name}, service_name={service_name}, '
            'region: {region}, auth_version: {auth_version}, reason: {error}')
        msg = msg.format(
            platform=cred.platform, env_id=cred.env_id, url=url, tenant_name=tenant_name,
            service_name=service_name, region=region, auth_version=auth_version,
            error=helper.exc_info(where=False))
        _handle_exception(e, msg)


def _openstack(provider, cred):
    result = list()

    username, password, auth_version, keystone_url, tenant_name = _openstack_cred(cred)
    url = urlparse.urlparse(keystone_url)

    cls = get_driver(provider)
    driver = cls(
        username,
        password,
        ex_force_auth_url=url.geturl(),
        ex_force_base_url='%s://%s' % (url.scheme, url.netloc),
        ex_tenant_name=tenant_name,
        ex_force_auth_version=auth_version,
    )
    driver.connection.set_http_proxy(proxy_url=app.proxy_url[cred.platform])

    service_catalog = _libcloud_get_service_catalog(driver)
    service_names = service_catalog.get_service_names(service_type='compute')
    regions = service_catalog.get_regions(service_type='compute')

    for service_name in service_names:
        app.pool.wait()
        async_results = dict(
            (
                region,
                app.pool.apply_async(
                    _openstack_region,
                    args=(provider, service_name, region, cred)
                )
            ) for region in regions
        )
        gevent.sleep(0)  # force switch
        for region, async_result in async_results.iteritems():
            try:
                region_nodes = async_result.get(timeout=app.config['cloud_connection_timeout'] + 1)
                if region_nodes:
                    result.append(region_nodes)
            except gevent.timeout.Timeout:
                async_result.kill()
                msg = (
                    'platform: {platform}, env_id: {env_id}, url: {url}, '
                    'tenant_name: {tenant_name}, service_name={service_name}, '
                    'region: {region}, auth_version: {auth_version}. Reason: timeout')
                msg = msg.format(
                    platform=cred.platform, env_id=cred.env_id, url=url, tenant_name=tenant_name,
                    service_name=service_name, region=region, auth_version=auth_version)
                LOG.warning(msg)
    return result


def openstack(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.OPENSTACK, cred)


def rackspacenguk(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.RACKSPACE, cred)


def rackspacengus(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.RACKSPACE, cred)


def ocs(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.OPENSTACK, cred)


def nebula(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.OPENSTACK, cred)


def mirantis(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.OPENSTACK, cred)


def vio(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.OPENSTACK, cred)


def verizon(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.OPENSTACK, cred)


def cisco(cred):
    """
    :returns: list
        [{'region': str, 'timestamp': int, 'nodes': list}]
    """

    return _openstack(Provider.OPENSTACK, cred)


def sort_nodes(cloud_data, cred, envs_ids):
    platform = cred.platform

    # gce
    if platform == 'gce':
        query = (
            "SELECT EXISTS "
            "(SELECT 1 FROM servers s "
            "JOIN servers_history h "
            "ON s.server_id=h.server_id "
            "WHERE s.server_id='{server_id}') AS value"
        )
        for region_data in cloud_data:
            region_data['managed'] = list()
            region_data['not_managed'] = list()
            for node in region_data['nodes']:
                if node.get('server_id', '') and \
                        app.scalr_db.execute(query.format(**node))[0]['value'] and \
                        node['env_id'] in envs_ids:
                    region_data['managed'].append(node)
                else:
                    region_data['not_managed'].append(node)
            del region_data['nodes']
        return cloud_data

    # all platforms exclude gce
    url_key = analytics.url_key_map[platform]
    url = cred[url_key] if url_key else ''
    for region_data in cloud_data:
        cloud_location = region_data['region']
        for chunk in helper.chunks(region_data['nodes'], 200):
            app.analytics.get_server_id_by_instance_id(chunk, platform, cloud_location,
                                                       envs_ids=envs_ids, url=url)
        region_data['managed'] = list()
        region_data['not_managed'] = list()
        for node in region_data['nodes']:
            if 'server_id' in node:
                region_data['managed'].append(node)
            else:
                region_data['not_managed'].append(node)
        del region_data['nodes']

    return cloud_data


def sorted_data_update(sorted_data):
    for region_data in sorted_data:
        for server in region_data['managed']:
            if server.get('os', None) is not None:
                continue
            query = (
                "SELECT os_type os "
                "FROM servers "
                "WHERE server_id='{server_id}'"
            ).format(server_id=server['server_id'])
            result = app.scalr_db.execute(query, retries=1)
            if not result:
                query = (
                    "SELECT value AS os "
                    "FROM server_properties "
                    "WHERE server_id='{server_id}' "
                    "AND name='os_type'"
                ).format(server_id=server['server_id'])
                result = app.scalr_db.execute(query, retries=1)
            if not result:
                server['os'] = 'linux'
                msg = "Can't detect OS type for server: {0}, set 'linux'".format(
                    server['server_id'])
                LOG.warning(msg)
            else:
                server['os'] = result[0]['os']
        for server in region_data['managed']:
            server['os'] = analytics.os_map[server.get('os', None)]
        for server in region_data['not_managed']:
            server['os'] = analytics.os_map[server.get('os', None)]


def db_update(sorted_data, envs_ids, cred):
    platform = cred.platform

    for env_id in envs_ids:
        for region_data in sorted_data:
            try:
                sid = uuid.uuid4()
                if platform == 'ec2':
                    cloud_account = cred.get('account_id')
                else:
                    cloud_account = None

                if analytics.url_key_map[platform]:
                    url = urlparse.urlparse(cryptotool.decrypt_scalr(
                        app.crypto_key, cred[analytics.url_key_map[platform]]).rstrip('/'))
                    url = '%s%s' % (url.netloc, url.path)
                else:
                    url = ''

                query = (
                    "SELECT client_id "
                    "FROM client_environments "
                    "WHERE id={env_id}"
                ).format(env_id=env_id)
                results = app.scalr_db.execute(query, retries=1)
                account_id = results[0]['client_id']

                query = (
                    "INSERT IGNORE INTO poller_sessions "
                    "(sid, account_id, env_id, dtime, platform, url, cloud_location, cloud_account) "
                    "VALUES "
                    "(UNHEX('{sid}'), {account_id}, {env_id}, '{dtime}', '{platform}', '{url}',"
                    "'{cloud_location}', '{cloud_account}')"
                ).format(
                    sid=sid.hex, account_id=account_id, env_id=env_id,
                    dtime=time.strftime(
                        "%Y-%m-%d %H:%M:%S", time.gmtime(region_data['timestamp'])),
                    platform=platform, url=url, cloud_location=region_data['region'],
                    cloud_account=cloud_account
                )
                app.analytics_db.execute(query, retries=1)

                # managed
                for managed in region_data['managed']:
                    if managed['env_id'] != env_id:
                        continue
                    query = (
                        "INSERT IGNORE INTO managed "
                        "(sid, server_id, instance_type, os) VALUES "
                        "(UNHEX('{sid}'), UNHEX('{server_id}'), '{instance_type}', {os})"
                    ).format(
                        sid=sid.hex,
                        server_id=uuid.UUID(managed['server_id']).hex,
                        instance_type=managed['instance_type'],
                        os=managed['os'])
                    app.analytics_db.execute(query, retries=1)
            except:
                helper.handle_error(message='Database update failed')


def process_credential(cred, envs_ids=None):
    if envs_ids is None:
        envs_ids = [cred.env_id]

    try:
        analytics.Credentials.test(cred, cred.platform)
        cloud_data = eval(cred.platform)(cred)
        if cloud_data:
            sorted_data = sort_nodes(cloud_data, cred, envs_ids)
            sorted_data_update(sorted_data)
            db_update(sorted_data, envs_ids, cred)
    except:
        e = sys.exc_info()[1]
        msg = 'platform: {platform}, environments: {envs}, reason: {error}'
        msg = msg.format(platform=cred.platform, envs=envs_ids, error=helper.exc_info(where=False))
        _handle_exception(e, msg)


class AnalyticsPoller(application.ScalrIterationApplication):

    def __init__(self, argv=None):
        self.description = "Scalr Cost Analytics poller application"

        super(AnalyticsPoller, self).__init__(argv=argv)

        self.config['connections'].update({
            'analytics': {
                'user': None,
                'pass': None,
                'host': None,
                'port': 3306,
                'name': None,
                'pool_size': 50,
            },
        })
        self.config.update({
            'pool_size': 100,
            'interval': 300,
            'cloud_connection_timeout': 10
        })

        self.scalr_db = None
        self.analytics_db = None
        self.analytics = None
        self.pool = None
        self.crypto_key = None
        self.proxy_settings = {}
        self.proxy_url = {}

    def set_proxy(self):
        for platform in analytics.PLATFORMS:
            if platform == 'ec2':
                use_proxy = self.scalr_config.get('aws', {}).get('use_proxy', False)
            else:
                use_proxy = self.scalr_config.get(platform, {}).get('use_proxy', False)
            use_on = self.scalr_config['connections'].get('proxy', {}).get('use_on', 'both')
            if use_proxy in [True, 'yes'] and use_on in ['both', 'scalr']:
                proxy_settings = self.scalr_config['connections']['proxy']
                proxy_url = 'http://{user}:{pass}@{host}:{port}'.format(**proxy_settings)
                self.proxy_settings[platform] = proxy_settings
                self.proxy_url[platform] = proxy_url
            else:
                self.proxy_settings[platform] = {}
                self.proxy_url[platform] = None

    def configure(self):
        enabled = self.scalr_config.get('analytics', {}).get('enabled', False)
        if not enabled:
            sys.stdout.write('Analytics is disabled. Exit\n')
            sys.exit(0)
        helper.update_config(
            self.scalr_config.get('analytics', {}).get('connections', {}).get('scalr', {}),
            self.config['connections']['mysql'])
        helper.update_config(
            self.scalr_config.get('analytics', {}).get('connections', {}).get('analytics', {}),
            self.config['connections']['analytics'])
        helper.update_config(
            self.scalr_config.get('analytics', {}).get('poller', {}),
            self.config)
        helper.validate_config(self.config)

        self.config['pool_size'] = max(11, self.config['pool_size'])
        self.iteration_timeout = self.config['interval'] - self.error_sleep

        crypto_key_path = os.path.join(os.path.dirname(self.args['--config']), '.cryptokey')
        self.crypto_key = cryptotool.read_key(crypto_key_path)
        self.scalr_db = dbmanager.ScalrDB(self.config['connections']['mysql'])
        self.analytics_db = dbmanager.ScalrDB(self.config['connections']['analytics'])
        self.analytics = analytics.Analytics(self.scalr_db, self.analytics_db)
        self.pool = helper.GPool(pool_size=self.config['pool_size'])

        self.set_proxy()

        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

    def do_iteration(self):
        for envs in self.analytics.load_envs():
            try:
                self.analytics.load_env_credentials(envs)
                unique = {}
                for env in envs:
                    try:
                        credentials = self.analytics.get_credentials([env])
                        for cred in credentials:
                            if cred.platform == 'ec2' and env.get('ec2.detailed_billing.enabled', '0') == '1':
                                continue
                            unique.setdefault(cred.unique, {'envs_ids': [], 'cred': cred})
                            unique[cred.unique]['envs_ids'].append(env['id'])
                    except:
                        msg = 'Processing environment: {} failed'.format(env['id'])
                        LOG.exception(msg)
                for data in unique.values():
                    while len(self.pool) > self.config['pool_size'] * 5 / 10:
                        gevent.sleep(0.1)
                    self.pool.apply_async(process_credential,
                                          args=(data['cred'],),
                                          kwds={'envs_ids': data['envs_ids']})
                    gevent.sleep(0)  # force switch
            except:
                msg = 'Processing environments: {} failed'.format([env['id'] for env in envs])
                LOG.exception(msg)
        self.pool.join()

    def on_iteration_error(self):
        self.pool.kill()


def main():
    global app
    app = AnalyticsPoller()
    try:
        app.load_config()
        app.configure()
        app.run()
    except exceptions.AlreadyRunningError:
        LOG.info(helper.exc_info())
    except (SystemExit, KeyboardInterrupt):
        pass
    except:
        LOG.exception('Oops')


if __name__ == '__main__':
    main()
