from gevent import monkey
monkey.patch_all()

import sys
import time
import yaml
import socket
import urllib2
import logging
import argparse
import gevent.pool

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import basedaemon
from scalrpy.util import cryptotool

from sqlalchemy import and_
from sqlalchemy import asc
from sqlalchemy import func
from sqlalchemy import exc as db_exc

from scalrpy import __version__


CONFIG = {
    'connections':{
        'mysql':{
            'user':None,
            'pass':None,
            'host':None,
            'port':3306,
            'name':None,
            'pool_size':4,
            'driver':'mysql+pymysql',
            },
        },
    'pool_size':100,
    'no_daemon':False,
    'cratio':120,
    'instances_connection_timeout':10,
    'instances_connection_policy':'public',
    'pid_file':'/var/run/scalr.messaging.pid',
    'log_file':'/var/log/scalr.messaging.log',
    'verbosity':1,
    }


LOG = logging.getLogger('ScalrPy')


class Messaging(basedaemon.BaseDaemon):

    def __init__(self):
        super(Messaging, self).__init__(pid_file=CONFIG['pid_file'])
        self._db_manager = dbmanager.DBManager(CONFIG['connections']['mysql'], autoflush=False)
        self._worker_pool = gevent.pool.Pool(CONFIG['pool_size'])


    def _encrypt(self, server_id, key, data, headers=None):
        assert server_id
        assert key
        assert data
        crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)
        data = cryptotool.encrypt(crypto_algo, data, cryptotool.decrypt_key(key))
        headers = headers or dict()
        headers['X-Signature'], headers['Date'] = cryptotool.sign(data, key)
        headers['X-Server-Id'] = server_id
        return data, headers


    def _get_messages(self):
        db = self._db_manager.get_db()
        where = and_(
                db.messages.type == 'out',
                db.messages.status == 0,
                db.messages.messageid != '',
                db.messages.message_version == 2,
                func.unix_timestamp(db.messages.dtlasthandleattempt) +
                db.messages.handle_attempts *
                CONFIG['cratio'] < func.unix_timestamp(func.now()))
        messages = db.session.query(
                db.messages.messageid,
                db.messages.server_id,
                db.messages.message_name,
                db.messages.message_format,
                db.messages.handle_attempts,
                db.messages.event_id,
                db.messages.message).filter(where).order_by(asc(db.messages.id)).limit(500)[0:500]
        return messages


    def _get_servers(self, servers_id):
        if not servers_id:
            return tuple()
        db = self._db_manager.get_db()
        status = (
                'Running',
                'Initializing',
                'Importing',
                'Temporary',
                'Pending terminate')
        servers = db.session.query(
                db.servers.server_id,
                db.servers.farm_id,
                db.servers.farm_roleid,
                db.servers.remote_ip,
                db.servers.local_ip).filter(
                and_(db.servers.server_id.in_(servers_id), db.servers.status.in_(status)))
        return servers


    def _get_ctrl_ports(self, servers_id):
        if not servers_id:
            return tuple()
        db = self._db_manager.get_db()
        where = and_(
                db.server_properties.server_id.in_(servers_id),
                db.server_properties.name == 'scalarizr.ctrl_port',
                db.server_properties.value != 'NULL')
        ctrl_ports = db.session.query(
                db.server_properties.server_id,
                db.server_properties.value).filter(where)
        return ctrl_ports


    def _get_srz_keys(self, servers_id):
        if not servers_id:
            return tuple()
        db = self._db_manager.get_db()
        where_key = and_(
                db.server_properties.server_id.in_(servers_id),
                db.server_properties.name == 'scalarizr.key',
                db.server_properties.value != 'NULL')
        srz_keys = db.session.query(
                db.server_properties.server_id,
                db.server_properties.value).filter(where_key)
        return srz_keys


    def _filter_vpc_farms(self, farms_id):
        if not farms_id:
            return tuple()
        db = self._db_manager.get_db()
        where = and_(
                db.farm_settings.farmid.in_(farms_id),
                db.farm_settings.name == 'ec2.vpc.id',
                db.farm_settings.value != 'NULL')
        return [farm.farmid for farm in
                db.session.query(db.farm_settings.farmid).filter(where)]


    def _get_vpc_router_roles(self, farms_id):
        if not farms_id:
            return dict()
        db = self._db_manager.get_db()
        where = and_(db.role_behaviors.behavior == 'router')
        vpc_roles = db.session.query(db.role_behaviors.role_id).filter(where)
        roles_id = [behavior.role_id for behavior in vpc_roles]
        if not roles_id:
            return dict()
        where = and_(
                db.farm_roles.role_id.in_(roles_id),
                db.farm_roles.farmid.in_(farms_id))
        return dict((el.farmid, el.id) for el in db.session.query(
                db.farm_roles.farmid, db.farm_roles.id).filter(where))


    def _produce_tasks(self):
        tasks = []
        db = self._db_manager.get_db()
        try:
            messages = self._get_messages()
            servers_id = [message.server_id for message in messages]
            servers = dict((server.server_id, server) for server in self._get_servers(servers_id))
            if not servers:
                return tasks
            srz_keys = dict((el.server_id, el.value) for el in self._get_srz_keys(servers_id))
            ctrl_ports = dict((el.server_id, el.value) for el in self._get_ctrl_ports(servers_id))
            vpc_farms_id = self._filter_vpc_farms(
                    list(set(server.farm_id for server in servers.values())))
            vpc_router_roles = self._get_vpc_router_roles(vpc_farms_id)
            for message in messages:
                try:
                    msg = {
                            'messageid': message.messageid,
                            'message_name': message.message_name,
                            'handle_attempts': message.handle_attempts,
                            'event_id': message.event_id}
                    if not message.message:
                        LOG.warning('Message %s for server %s set status=3 \
Reason: empty message' % (message.messageid, message.server_id))
                        msg['handle_attempts'] = 2
                        self._db_update(False, msg)
                        continue
                    if message.server_id in servers:
                        server = servers[message.server_id]
                    else:
                        LOG.warning('Message %s for server %s set status=3 \
Reason: server dosn\'t exist' % (message.messageid, message.server_id))
                        msg['handle_attempts'] = 2
                        self._db_update(False, msg)
                        continue
                    if message.server_id in srz_keys and srz_keys[message.server_id]:
                        key = srz_keys[message.server_id]
                    else:
                        LOG.error('Server %s hasn\'t scalarizr key' % message.server_id)
                        self._db_update(False, msg)
                        continue
                    if message.server_id in ctrl_ports and ctrl_ports[message.server_id]:
                        port = ctrl_ports[message.server_id]
                    else:
                        port = 8013
                    ip = {'public': server.remote_ip,
                            'local': server.local_ip,
                            'auto': server.remote_ip if server.remote_ip else server.local_ip
                            }[CONFIG['instances_connection_policy']]
                    try:
                        data, headers = self._encrypt(message.server_id, key, message.message)
                    except:
                        LOG.warning('Message %s for server %s set status=3 \
Reason: unable to encrypt message, error %s' \
                                % (message.messageid, message.server_id, helper.exc_info()))
                        msg['handle_attempts'] = 2
                        self._db_update(False, msg)
                        continue
                    if server.farm_id in vpc_farms_id and server.farm_id in vpc_router_roles:
                        if server.remote_ip:
                            ip = server.remote_ip
                        else:
                            where = and_(
                                    db.farm_role_settings.farm_roleid == vpc_router_roles[server.farm_id],
                                    db.farm_role_settings.name == 'router.vpc.ip',
                                    db.farm_role_settings.value != 'NULL')
                            ip_query = db.session.query(
                                    db.farm_role_settings.value).filter(where).first()
                            if ip_query:
                                ip = ip_query.value
                                headers['X-Receiver-Host'] = server.local_ip
                                headers['X-Receiver-Port'] = port
                                port = 80
                            else:
                                LOG.warning('Message %s for server %s set status=3 \
Reason: farm_role_settings hasn\'t ip value' % (message.messageid, message.server_id))
                                msg['handle_attempts'] = 2
                                self._db_update(False, msg)
                                continue
                    if not ip:
                        LOG.warning('Message %s for server %s set status=3 \
Reason: can\'t determine ip' % (message.messageid, message.server_id))
                        msg['handle_attempts'] = 2
                        self._db_update(False, msg)
                        continue
                    if str(message.message_format) == 'json':
                        headers['Content-type'] = 'application/json'
                    url = 'http://%s:%s/%s' % (ip, port, 'control')
                    request = urllib2.Request(url, data, headers)
                    tasks.append({'msg': msg, 'req': request})
                except:
                    LOG.error(helper.exc_info())
            db.session.commit()
        finally:
            db.session.remove()
        return tasks


    def _db_update(self, ok, msg):
        db = self._db_manager.get_db()
        try:
            while True:
                try:
                    if ok:
                        if msg['message_name'] == 'ExecScript':
                            db.messages.filter(
                                    db.messages.messageid == msg['messageid']).delete()
                        else:
                            db.messages.filter(db.messages.messageid == msg['messageid']).update({
                                    'status': 1,
                                    'message': '',
                                    'dtlasthandleattempt': func.now()},
                                    synchronize_session=False)
                        if msg['event_id']:
                            db.events.filter(db.events.event_id == msg['event_id']).update({
                                    db.events.msg_sent: db.events.msg_sent + 1})
                    else:
                        db.messages.filter(db.messages.messageid == msg['messageid']).update({
                                'status': 0 if msg['handle_attempts'] < 2 else 3,
                                'handle_attempts': msg['handle_attempts'] + 1,
                                'dtlasthandleattempt': func.now()},
                                synchronize_session=False)
                    db.session.commit()
                    break
                except db_exc.SQLAlchemyError:
                    db.session.remove()
                    LOG.error(helper.exc_info())
                    time.sleep(5)
        finally:
            db.session.remove()


    def _send(self, task):
        if not task:
            return
        try:
            msg = task['msg']
            req = task['req']
            try:

                LOG.debug('Send message %s host %s header %s'
                        % (msg['messageid'], req.get_host(), req.header_items()))
                code = urllib2.urlopen(
                    req, timeout=CONFIG['instances_connection_timeout']).getcode()
                if code != 201:
                    raise Exception('Server response code %s' % code)
                LOG.debug('Delivery ok, message %s, host %s'
                        % (msg['messageid'], req.get_host()))
                try:
                    self._db_update(True, msg)
                except:
                    LOG.error('Unable to update database %s' %helper.exc_info())
            except:
                e = sys.exc_info()[1]
                if type(e) in (urllib2.URLError, socket.timeout) and\
                        ('Connection refused' in str(e) or 'timed out' in str(e)):
                    LOG.warning('Delivery failed message id %s host %s error %s'
                            % (msg['messageid'], req.get_host(), helper.exc_info()))
                else:
                    LOG.error('Delivery failed message id %s host %s error %s'
                            % (msg['messageid'], req.get_host(), helper.exc_info()))
                self._db_update(False, msg)
        except:
            LOG.error(helper.exc_info())


    def _process_tasks(self, tasks):
        self._worker_pool.map(self._send, tasks)
        self._worker_pool.join()


    def run(self):
        while True:
            try:
                tasks = self._produce_tasks()
                if not tasks:
                    time.sleep(5)
                    continue
                self._process_tasks(tasks)
            except KeyboardInterrupt:
                raise KeyboardInterrupt
            except db_exc.SQLAlchemyError:
                LOG.error(helper.exc_info())
                time.sleep(5)
            except:
                LOG.exception('Exception')
                time.sleep(5)


    def start(self, daemon=False):
        if daemon:
            super(Messaging, self).start()
        else:
            self.run()


def configure(config, args=None):
    global CONFIG
    helper.update_config(config['connections']['mysql'], CONFIG['connections']['mysql'])
    if 'system' in config and 'instances_connection_timeout' in config['system']:
        timeout = config['system']['instances_connection_timeout']
        CONFIG['instances_connection_timeout'] = timeout
    if 'instances_connection_policy' in config:
        CONFIG['instances_connection_policy'] = config['instances_connection_policy']
    if 'msg_sender' in config:
        helper.update_config(config['msg_sender'], CONFIG)
    helper.update_config(config_to=CONFIG, args=args)
    helper.validate_config(CONFIG)
    helper.configure_log(
            log_level=CONFIG['verbosity'],
            log_file=CONFIG['log_file'],
            log_size=1024*1000
            )


def main():
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--start', action='store_true', default=False, help='start daemon')
    group.add_argument('--stop', action='store_true', default=False, help='stop daemon')
    parser.add_argument('--no-daemon', action='store_true', default=None,
            help="Run in no daemon mode")
    parser.add_argument('-p', '--pid-file', default=None, help="Pid file")
    parser.add_argument('-l', '--log-file', default=None, help="Log file")
    parser.add_argument('-c', '--config-file', default='./config.yml', help='config file')
    parser.add_argument('-t', '--instances-connection-timeout', type=int, default=None,
            help='instances connection timeout')
    parser.add_argument('-v', '--verbosity', action='count', default=None,
            help='increase output verbosity [0:4]. Default is 1 - ERROR')
    parser.add_argument('--version', action='version', version='Version %s' % __version__)
    args = parser.parse_args()
    try:
        config = yaml.safe_load(open(args.config_file))['scalr']
        configure(config, args)
    except:
        if args.verbosity > 3:
            raise
        else:
            sys.stderr.write('%s\n' % helper.exc_info())
        sys.exit(1)
    try:
        socket.setdefaulttimeout(CONFIG['instances_connection_timeout'])
        daemon = Messaging()
        if args.start:
            LOG.info('Start')
            if not helper.check_pid(CONFIG['pid_file']):
                LOG.info('Another copy of process already running. Exit')
                return
            daemon.start(daemon=not args.no_daemon)
        elif args.stop:
            LOG.info('Stop')
            daemon.stop()
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
