import time
import socket
import threading
import pymysql
import pymysql.err
import pymysql.cursors

from scalrpy.util import helper

from scalrpy import LOG


def make_connection(config, autocommit=True):
    connection = pymysql.connect(
        user=config['user'],
        passwd=config['pass'],
        db=config['name'],
        host=config['host'],
        port=config['port'],
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=config.get('timeout', 10)
    )
    connection.autocommit(autocommit)
    return connection


def validate_connection(connection):
    try:
        return connection.ping()
    except:
        try:
            connection.close()
        except:
            pass
        return False


class DB(object):

    def __init__(self, config, pool_size=None):
        self._local = threading.local()

        def _make_connection():
            return make_connection(config, autocommit=True)

        def _validate_connection(connection):
            return validate_connection(connection)

        self._connection_pool = helper.Pool(
            _make_connection,
            _validate_connection,
            pool_size if pool_size else config.get('pool_size', 4))

    def autocommit(self, state):
        if state and self._connection:
            self._connection_pool.put(self._local.connection)
            self._local.cursor.close()
            self._local.cursor = None
            self._local.connection = None
        self._local.autocommit = bool(state)

    @property
    def _connection(self):
        try:
            return self._local.connection
        except AttributeError:
            self._local.connection = None
            return self._local.connection

    @property
    def _autocommit(self):
        try:
            return self._local.autocommit
        except AttributeError:
            self._local.autocommit = True
            return self._local.autocommit

    def execute(self, query, retries=0, retry_timeout=10):
        while True:
            try:
                if self._autocommit or not self._connection:
                    self._local.connection = self._connection_pool.get(timeout=10)
                self._local.connection.autocommit(self._autocommit)
                self._local.cursor = self._connection.cursor()
                try:
                    start_time = time.time()
                    self._local.cursor.execute(query)
                    end_time = time.time()
                    if end_time - start_time > 1:
                        LOG.debug('Query too slow: %s\n%s...' %
                                  (end_time - start_time, query[:150]))
                    results = self._local.cursor.fetchall()
                    if results is not None:
                        results = tuple(results)
                    return results
                finally:
                    if self._autocommit:
                        self._local.cursor.close()
                        self._connection_pool.put(self._local.connection)
                        self._local.connection = None
                        self._local.cursor = None
            except (pymysql.err.OperationalError, pymysql.err.InternalError, socket.timeout):
                if not retries:
                    raise
                retries -= 1
                time.sleep(retry_timeout)

    def execute_with_limit(self, query, limit, max_limit=None, retries=0, retry_timeout=10):
        """
        :returns: generator
        """

        if max_limit:
            i, chunk_size = 0, min(limit, max_limit)
        else:
            i, chunk_size = 0, limit

        while True:
            is_last_iter = bool(max_limit) and (i + 1) * chunk_size > max_limit
            if is_last_iter:
                limit_query = query + \
                    " LIMIT %s, %s" % (i * chunk_size, max_limit - i * chunk_size)
            else:
                limit_query = query + " LIMIT %s, %s" % (i * chunk_size, chunk_size)
            results = self.execute(limit_query, retries=retries, retry_timeout=retry_timeout)
            if not results:
                break
            yield results
            if len(results) < limit or is_last_iter:
                break
            i += 1

    def commit(self):
        if self._connection:
            self._local.connection.commit()
            self._local.cursor.close()

    def rollback(self):
        if self._connection:
            self._connection.rollback()


class ScalrDB(DB):

    def load_server_properties(self, servers, names):
        names = list(names)
        servers_id = list(set(server['server_id'] for server in servers if server['server_id']))
        if not servers_id:
            return
        query = (
            "SELECT server_id, name, value "
            "FROM server_properties "
            "WHERE name IN ({0}) "
            "AND server_id IN ({1})"
        ).format(str(names)[1:-1], str(servers_id)[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp.setdefault(result['server_id'], {}).update({result['name']: result['value']})
        for server in servers:
            if server['server_id'] not in tmp:
                continue
            for k, v in tmp[server['server_id']].iteritems():
                if k in server and server[k]:
                    continue
                server[k] = v
        return

    def load_client_environment_properties(self, envs, names):
        names = list(names)
        envs_ids = list(set(env['id'] for env in envs if env['id'] or env['id'] == 0))
        if not envs_ids:
            return tuple()
        query = (
            "SELECT env_id, name, value "
            "FROM client_environment_properties "
            "WHERE name IN ({0}) "
            "AND env_id IN ({1})"
        ).format(str(names)[1:-1], str(envs_ids).replace('L', '')[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp.setdefault(result['env_id'], {}).update({result['name']: result['value']})
        for env in envs:
            if env['id'] not in tmp:
                continue
            for k, v in tmp[env['id']].iteritems():
                if k in env and env[k]:
                    continue
                env[k] = v
        return

    def load_farm_settings(self, farms, names):
        names = list(names)
        farms_ids = list(set(farm['id'] for farm in farms if farm['id'] or farm['id'] == 0))
        if not farms_ids:
            return dict()
        query = (
            "SELECT farmid farm_id, name, value "
            "FROM farm_settings "
            "WHERE name IN({0}) "
            "AND farmid IN ({1})"
        ).format(str(names)[1:-1], str(farms_ids).replace('L', '')[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp.setdefault(result['farm_id'], {}).update({result['name']: result['value']})
        for farm in farms:
            if farm['id'] not in tmp:
                continue
            for k, v in tmp[farm['id']].iteritems():
                if k in farm and farm[k]:
                    continue
                farm[k] = v
        return

    def load_farm_role_settings(self, farms_roles, names):
        names = list(names)
        farms_roles_ids = list(set(_['id'] for _ in farms_roles if _['id'] or _['id'] == 0))
        if not farms_roles_ids:
            return dict()
        query = (
            "SELECT farm_roleid, name, value "
            "FROM farm_role_settings "
            "WHERE name IN ({0}) "
            "AND farm_roleid IN ({1})"
        ).format(str(names)[1:-1], str(farms_roles_ids).replace('L', '')[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp.setdefault(result['farm_roleid'], {}).update({result['name']: result['value']})
        for farm_role in farms_roles:
            if farm_role['id'] not in tmp:
                continue
            for k, v in tmp[farm_role['id']].iteritems():
                if k in farm_role and farm_role[k]:
                    continue
                farm_role[k] = v
        return

    def load_vpc_settings(self, servers):
        # ec2.vpc.id
        farms_id = list(set([_['farm_id'] for _ in servers if _['farm_id'] or _['farm_id'] == 0]))
        if not farms_id:
            return
        query = (
            "SELECT farmid, value "
            "FROM farm_settings "
            "WHERE name = 'ec2.vpc.id' "
            "AND farmid IN ({0})"
        ).format(str(farms_id).replace('L', '')[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp[result['farmid']] = result['value']
        for server in servers:
            if server['farm_id'] not in tmp:
                continue
            server['ec2.vpc.id'] = tmp[server['farm_id']]

        # router_role_id
        farms_role_id = list(set([_['farm_roleid'] for _ in servers if 'ec2.vpc.id' in _]))
        if not farms_role_id:
            return
        # get router role id from farm_role_settings
        query = (
            "SELECT farm_roleid, value "
            "FROM farm_role_settings "
            "WHERE name = 'router.scalr.farm_role_id' "
            "AND farm_roleid IN ({0}) "
        ).format(str(farms_role_id).replace('L', '')[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp[result['farm_roleid']] = int(result['value'])
        for server in servers:
            if server['farm_roleid'] not in tmp:
                continue
            # router.scalr.farm_role_id has int type
            server['router_role_id'] = int(tmp[server['farm_roleid']])

        # get router role id from farm_roles
        query = (
            "SELECT id router_role_id, farmid "
            "FROM farm_roles "
            "WHERE role_id IN "
            "(SELECT role_id FROM role_behaviors WHERE behavior='router') "
            "AND farmid IN ({0})"
        ).format(str(farms_id).replace('L', '')[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp[result['farmid']] = result['router_role_id']
        for server in servers:
            if 'router_role_id' not in server and server['farm_id'] in tmp:
                server['router_role_id'] = tmp[server['farm_id']]

        # router_vpc_ip
        routers_role_id = list(set(_['router_role_id']
                                   for _ in servers if 'ec2.vpc.id' in _ and 'router_role_id' in _))
        if not routers_role_id:
            return
        query = (
            "SELECT farm_roleid, value "
            "FROM farm_role_settings "
            "WHERE name = 'router.vpc.ip' "
            "AND farm_roleid IN ({0})"
        ).format(str(routers_role_id).replace('L', '')[1:-1])
        results = self.execute(query)
        tmp = dict()
        for result in results:
            tmp[result['farm_roleid']] = result['value']
        for server in servers:
            if 'router_role_id' in server and server['router_role_id'] in tmp:
                server['router.vpc.ip'] = tmp[server['router_role_id']]

        return
