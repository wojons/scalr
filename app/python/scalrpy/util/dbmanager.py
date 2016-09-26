import time
import random
import socket
import threading
import pymysql
import pymysql.err
import pymysql.cursors

from scalrpy.util import helper

from scalrpy import LOG
from scalrpy import exceptions


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
        self.pool_size = pool_size or config.get('pool_size', 50)

        def _make_connection():
            connection = make_connection(config, autocommit=True)
            connection.timestamp = time.time()
            return connection

        def _validate_connection(connection):
            return validate_connection(connection)

        self._connection_pool = helper.Pool(_make_connection, _validate_connection, self.pool_size)

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

    def _get_connection_from_pool(self, timeout=None):
        connection = self._connection_pool.get(timeout=timeout)
        if time.time() - connection.timestamp > 3600:
            self._connection_pool.remove(connection)
            connection.close()
            connection = self._connection_pool.get(timeout=timeout)
        return connection

    def execute(self, query, retries=0, retry_timeout=10):
        while True:
            try:
                if self._autocommit or not self._connection:
                    self._local.connection = self._get_connection_from_pool(timeout=30)
                self._local.connection.autocommit(self._autocommit)
                self._local.cursor = self._connection.cursor()
                start_time = time.time()
                if len(query) > 2000:
                    msg = '%s...' % query[:2000]
                else:
                    msg = query

                LOG.debug(msg)
                try:
                    self._local.cursor.execute(query)
                    results = self._local.cursor.fetchall()
                finally:
                    end_time = time.time()
                    try:
                        if self._autocommit:
                            self._connection_pool.put(self._local.connection)
                            self._local.cursor.close()
                            self._local.connection = None
                            self._local.cursor = None
                    except:
                        msg = 'MySQL finalize connection error'
                        helper.handle_error(message=msg, level='error')
                if end_time - start_time > 5:
                    LOG.debug('Query too slow: %s\n%s...' % (end_time - start_time, query[:250]))
                if results is not None:
                    results = tuple(results)
                else:
                    results = tuple()
                return results
            except exceptions.TimeoutError as e:
                LOG.warning(e)
            except (pymysql.err.InternalError, pymysql.err.OperationalError, socket.timeout) as e:
                if isinstance(e, pymysql.err.InternalError) and e.args[0] == 1213:
                    LOG.warning('MySQL 1213 error, retry')
                    time.sleep(random.randint(0, 20) / 100.0)
                    continue
                if isinstance(e, pymysql.err.OperationalError) and e.args[0] == 2013:
                    LOG.warning('MySQL 2013 error during query: %s' % query[0:150])
                    if self._local.connection:
                        self._connection_pool.remove(self._local.connection)
                        self._local.connection.close()
                        self._local.connection = None
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
        names = map(str, list(names))
        servers_ids = list(set(str(server['server_id']) for server in servers if server['server_id']))
        if not servers_ids:
            return
        query = (
            "SELECT server_id, name, value "
            "FROM server_properties "
            "WHERE name IN ({}) "
            "AND server_id IN ({})"
        ).format(str(names)[1:-1], str(servers_ids)[1:-1])
        results = self.execute(query)
        for server in servers:
            server.update({result['name']: result['value'] for result in results
                          if result['server_id'] == server['server_id'] and not server.get(result['name'])})

    def load_client_environment_properties(self, envs, names):
        names = map(str, list(names))
        envs_ids = list(set(int(env['id']) for env in envs))
        if not envs_ids:
            return
        query = (
            "SELECT env_id, name, value "
            "FROM client_environment_properties "
            "WHERE name IN ({}) "
            "AND env_id IN ({})"
        ).format(str(names)[1:-1], str(envs_ids)[1:-1])
        results = self.execute(query)
        for env in envs:
            env.update({result['name']: result['value'] for result in results
                       if result['env_id'] == env['id'] and not env.get(result['name'])})

    def load_farm_settings(self, farms, names):
        names = map(str, list(names))
        farms_ids = list(set(int(farm['id']) for farm in farms if farm['id'] or farm['id'] == 0))
        if not farms_ids:
            return
        query = (
            "SELECT farmid farm_id, name, value "
            "FROM farm_settings "
            "WHERE name IN({}) "
            "AND farmid IN ({})"
        ).format(str(names)[1:-1], str(farms_ids)[1:-1])
        results = self.execute(query)
        for farm in farms:
            farm.update({result['name']: result['value'] for result in results
                        if result['farm_id'] == farm['id'] and not farm.get(result['name'])})

    def load_farm_role_settings(self, farm_roles, names):
        names = map(str, list(names))
        farm_roles_ids = list(set(int(farm_role['id']) for farm_role in farm_roles
                              if farm_role['id'] or farm_role['id'] == 0))
        if not farm_roles_ids:
            return
        query = (
            "SELECT farm_roleid farm_role_id, name, value "
            "FROM farm_role_settings "
            "WHERE name IN ({}) "
            "AND farm_roleid IN ({})"
        ).format(str(names)[1:-1], str(farm_roles_ids)[1:-1])
        results = self.execute(query)
        for farm_role in farm_roles:
            farm_role.update({result['name']: result['value'] for result in results
                             if result['farm_role_id'] == farm_role['id'] and not farm_role.get(result['name'])})

    def load_vpc_settings(self, servers):
        if not servers:
            return

        farms_ids = list(set(int(server['farm_id']) for server in servers))

        # 1. ec2.vpc.id
        farms = [{'id': farm_id} for farm_id in farms_ids]
        names = ['ec2.vpc.id']
        self.load_farm_settings(farms, names)
        for server in servers:
            server.update({k: v for farm in farms for k, v in farm.items()
                          if farm['id'] == server['farm_id']
                          and k not in server and k != 'id'})

        # 2. router_role_id
        # 2.1 get router_role_id from farm_role_settings
        farm_roles_ids = list(set(server['farm_role_id']
                              for server in servers if 'ec2.vpc.id' in server))
        farm_roles = [{'id': farm_role_id} for farm_role_id in farm_roles_ids]
        names = ['router.scalr.farm_role_id']
        self.load_farm_role_settings(farm_roles, names)
        for server in servers:
            server.update({k: v for farm_role in farm_roles for k, v in farm_role.items()
                          if farm_role['id'] == server['farm_role_id'] and k not in server})
            if 'router.scalr.farm_role_id' in server:
                server['router_role_id'] = int(server.pop('router.scalr.farm_role_id'))

        # 2.2 get router_role_id from farm_roles
        query = (
            "SELECT id router_role_id, farmid farm_id "
            "FROM farm_roles "
            "WHERE role_id IN "
            "(SELECT role_id FROM role_behaviors WHERE behavior='router') "
            "AND farmid IN ({0})"
        ).format(str(farms_ids)[1:-1])
        results = self.execute(query)
        for server in servers:
            server.update({k: v for result in results for k, v in result.items()
                          if result['farm_id'] == server['farm_id'] and k not in server})

        # 3. router_vpc_ip
        router_roles_ids = list(set(server['router_role_id']
                                for server in servers
                                if 'ec2.vpc.id' in server and 'router_role_id' in server))
        router_roles = [{'id': router_role_id} for router_role_id in router_roles_ids]
        names = ['router.vpc.ip']
        self.load_farm_role_settings(router_roles, names)
        for server in servers:
            if 'router_role_id' not in server:
                continue
            server.update({k: v for router_role in router_roles for k, v in router_role.items()
                          if router_role['id'] == server['router_role_id'] and k not in server})
