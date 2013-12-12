import urllib
import sqlsoup
import sqlalchemy
import pymysql
import pymysql.cursors

from scalrpy.util import helper

from sqlalchemy.orm import sessionmaker
from sqlalchemy.orm import scoped_session


class DBManager(object):
    """Database manager class"""

    def __init__(self, config, autoflush=True, **kwargs):
        """
        :type config: dictionary
        :param config: Database connection info. Example:
            {
                'user':'user',
                'pass':'pass',
                'host':'localhost',
                'port':1234,
                'name':'scalr',
                'driver':'mysql+pymysql',
                'pool_recycle':120,
                'pool_size':4
            }
        """

        self.db = None
        self.db_engine = None
        self.kwargs = kwargs
        self.autoflush = autoflush

        try:
            host = '%s:%s' % (config['host'], int(config['port']))
        except Exception:
            host = config['host']

        self.connection = '%s://%s:%s@%s/%s' % (
                    config['driver'],
                    config['user'],
                    urllib.quote_plus(config['pass']),
                    host,
                    config['name'])


    def get_db(self):
        if not self.db_engine:
            self.db_engine = sqlalchemy.create_engine(self.connection, **self.kwargs)
        if not self.db:
            self.db = sqlsoup.SQLSoup(self.db_engine,
                    session=scoped_session(
                    sessionmaker(bind=self.db_engine, autoflush=self.autoflush)))
        return self.db


    def get_connection(self):
        if not self.db_engine:
            self.db_engine = sqlalchemy.create_engine(self.connection, **self.kwargs)
        return self.db_engine.connect()


def make_connection(config, autocommit=True):
    connection = pymysql.connect(
            user=config['user'],
            passwd=config['pass'],
            db=config['name'],
            host=config['host'],
            port=config['port'],
            cursorclass=pymysql.cursors.DictCursor,
            connect_timeout=10
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


class ScalrDB(object):

    def __init__(self, config, pool_size=None):

        def _make_connection():
            return make_connection(config)

        def _validate_connection(connection):
            return validate_connection(connection)

        self.connection_pool = helper.Pool(
                _make_connection,
                _validate_connection,
                pool_size if pool_size else config['pool_size']
                )


    def execute_query(self, query):
        connection = self.connection_pool.get(timeout=10)
        cursor = connection.cursor()
        try:
            cursor.execute(query)
            return cursor.fetchall()
        finally:
            cursor.close()
            self.connection_pool.put(connection)


    def get_envs_status_by_servers(self, servers):
        envs_id = list(set([_['env_id'] for _ in servers if _['env_id']]))
        if not envs_id:
            return dict()
        query = """SELECT `id`, `status` FROM `client_environments` WHERE `id` IN ( %s )""" \
                % str(envs_id).replace('L', '')[1:-1]
        return dict((_['id'], _['status']) for _ in self.execute_query(query))


    def vpc_farms_id_by_servers(self, servers):
        farms_id = list(set([_['farm_id'] for _ in servers if _['farm_id']]))
        if not farms_id:
            return list()
        query = """SELECT `farmid` """ +\
                """FROM `farm_settings` """ +\
                """WHERE `name`='ec2.vpc.id' AND `value` IS NOT NULL AND `farmid` IN ( %s )"""
        query = query % (str(farms_id).replace('L', '')[1:-1])
        result = self.execute_query(query)
        return [_ for _ in farms_id if _ in [e['farmid'] for e in result]]


    def get_router_roles_by_farms_id(self, farms_id):
        farms_id = list(set([_ for _ in farms_id if _]))
        if not farms_id:
            return tuple()
        query = """SELECT `id`, `farmid` """ +\
                """FROM `farm_roles` """ +\
                """WHERE `role_id` IN """ +\
                """(SELECT `role_id` FROM `role_behaviors` WHERE `behavior`='router') AND""" +\
                """`farmid` IN ( %s )"""
        query = query % (str(farms_id).replace('L', '')[1:-1])
        return self.execute_query(query)


    def get_router_roles_ip(self, router_roles):
        router_roles_id = [_['id'] for _ in router_roles if _['id']]
        if not router_roles_id:
            return dict()
        query = """SELECT `farm_roleid`, `value` """ +\
                """FROM `farm_role_settings` """ +\
                """WHERE `name`='router.vpc.ip' AND `value` IS NOT NULL AND """ + \
                """`farm_roleid` IN ( %s )"""
        query = query % str(router_roles_id).replace('L', '')[1:-1]
        result = self.execute_query(query)
        tmp = dict((_['id'], _['farmid']) for _ in router_roles)
        return dict((tmp[_['farm_roleid']], _['value']) for _ in result)


    def get_servers_vpc_ip(self, servers):
        vpc_farms_id = self.vpc_farms_id_by_servers(servers)
        router_roles = self.get_router_roles_by_farms_id(vpc_farms_id)
        router_roles_ip = self.get_router_roles_ip(router_roles)
        tmp = dict()
        for server in servers:
            if server['farm_id'] in vpc_farms_id:
                vpc_ip = None
                if server['farm_id'] in router_roles_ip:
                    if server['remote_ip']:
                        vpc_ip = server['remote_ip']
                    else:
                        vpc_ip = router_roles_ip[server['farm_id']]
                tmp.update({server['server_id']:vpc_ip})
        return tmp
