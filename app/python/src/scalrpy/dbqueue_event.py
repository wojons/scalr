from gevent import monkey
monkey.patch_all()

import sys
import yaml
import time
import socket
import logging
import smtplib
import argparse
import requests
import gevent.pool
import email.charset
import requests.exceptions

from email.mime.text import MIMEText

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import basedaemon

from scalrpy import __version__


email.charset.add_charset('utf-8', email.charset.QP, email.charset.QP)

CONFIG = {
    'connections':{
        'mysql':{
            'user':None,
            'pass':None,
            'host':None,
            'port':3306,
            'name':None,
            'pool_size':4,
            },
        },
    'email':{
        'address':None,
        },
    'pool_size':100,
    'no_daemon':False,
    'instances_connection_timeout':10,
    'log_file':'/var/log/scalr.dbqueue-event.log',
    'pid_file':'/var/run/scalr.dbqueue-event.pid',
    'verbosity':1,
    }

LOG = logging.getLogger('ScalrPy')


class DBQueueEvent(basedaemon.BaseDaemon):

    def __init__(self):
        super(DBQueueEvent, self).__init__(pid_file=CONFIG['pid_file'])
        self._db = dbmanager.ScalrDB(CONFIG['connections']['mysql'])
        self._pool = gevent.pool.Pool(CONFIG['pool_size'])
        self.observers = {
            'MailEventObserver':self.mail_event_observer,
            'RESTEventObserver':self.rest_event_observer,
            }


    def _get_db_events(self):
        query = """SELECT `id`, `type`, `farmid`, `message` """ +\
                """FROM `events` """ +\
                """WHERE `ishandled`=0 """ +\
                """ORDER BY `id` """ +\
                """LIMIT 500"""
        return self._db.execute_query(query)


    def _get_db_observers(self, farms_id):
        if not farms_id:
            return tuple()
        query = """SELECT `id`, `event_observer_name`, `farmid` """ +\
                """FROM `farm_event_observers`""" +\
                """WHERE `farmid` IN ( %s )"""
        query = query % str(farms_id).replace('L', '')[1:-1]
        return self._db.execute_query(query)


    def _get_db_observers_config(self, observers_id):
        if not observers_id:
            return tuple()
        query = """SELECT `observerid`, `key`, `value` """ +\
                """FROM `farm_event_observers_config` """ +\
                """WHERE `observerid` IN ( %s )"""
        query = query % str(observers_id).replace('L', '')[1:-1]
        return self._db.execute_query(query)


    def _get_observers(self, events):
        observers = dict()
        farms_id = [_['farmid'] for _ in events if _['farmid'] != None]
        if farms_id:
            for observer in self._get_db_observers(farms_id):
                observers.setdefault(observer['farmid'], []).append(observer)
        return observers


    def _get_config(self, observers):
        config = dict()
        observers_id = [_['id'] for s in observers.values() for _ in s if _['id'] != None]
        if observers_id:
            for cnf in self._get_db_observers_config(observers_id):
                config.setdefault(cnf['observerid'], {})
                config[cnf['observerid']].update({cnf['key']: cnf['value']})
        return config


    def get_events_data(self):
        data = list()
        events = self._get_db_events()
        observers = self._get_observers(events)
        config = self._get_config(observers)
        for event in events:
            element = {
                'event': {
                    'id': event['id'],
                    'type': event['type'],
                    'farmid': event['farmid'],
                    'message': event['message'],
                    },
                'observers': [],
                }
            if event['farmid'] in observers:
                for observer in observers[event['farmid']]:
                    if observer['id'] in config:
                        element['observers'].append({
                            'event_observer_name': observer['event_observer_name'],
                            'config': config[observer['id']],
                            })
            data.append(element)
        return data


    class NothingToDoError(Exception):
        pass


    def do_iteration(self):
        events_data = self.get_events_data()
        if not events_data:
            raise DBQueueEvent.NothingToDoError()
        for event_data in events_data:
            event, observers = event_data['event'], event_data['observers']
            for observer in observers:
                is_enabled = observer['config']['IsEnabled']
                if 'IsEnabled' in observer['config'] and is_enabled == '1':
                    self.observers[observer['event_observer_name']](
                        event,
                        observer['config'],
                        pool = self._pool
                        )
        self.db_update([_['event']['id'] for _ in events_data])
        self._pool.join()


    @helper.apply_async
    def mail_event_observer(self, event, config):
        try:
            key = 'On%sNotify' % event['type']
            if key not in config or config[key] != '1':
                return
            def get_farm_name(farm_id):
                query = """SELECT `name` FROM `farms` WHERE `id`=%s""" % farm_id
                try:
                    result = self._db.execute_query(query)
                except:
                    LOG.error(helper.exc_info())
                    return None
                return result[0]['name'] if result else None
            farm_name = get_farm_name(event['farmid'])
            if farm_name:
                subj = '%s event notification (FarmID: %s FarmName: %s)' \
                        % (event['type'], event['farmid'], farm_name)
            else:
                subj = '%s event notification (FarmID: %s)' % (event['type'], event['farmid'])
            mail = MIMEText(event['message'].encode('utf-8'), _charset='utf-8')
            mail['From'] = CONFIG['email']['address']
            mail['To'] = config['EventMailTo']
            mail['Subject'] = subj
            LOG.debug("Event:%s. Send mail:'%s'" % (event['id'], mail['Subject']))
            server = smtplib.SMTP('localhost')
            server.sendmail(mail['From'], mail['To'], mail.as_string())
        except:
            LOG.error(helper.exc_info())


    @helper.apply_async
    def rest_event_observer(self, event, config):
        try:
            key = 'On%sNotifyURL' % event['type']
            if key not in config or not config[key]:
                return
            payload = {'event': event['type'], 'message': event['message']}
            r = requests.post(config[key], params=payload, timeout=10)
            LOG.debug("Event:%s. Send request:'url:%s' status:'%s'" \
                    % (event['id'], config[key], r.status_code))
        except requests.exceptions.RequestException:
            LOG.warning(helper.exc_info())
        except:
            LOG.error(helper.exc_info())


    def db_update(self, events_id):
        while True:
            try:
                query = """UPDATE `events` SET `ishandled`=1 WHERE `id` IN ( %s )""" \
                        % str(events_id).replace('L', '')[1:-1]
                self._db.execute_query(query)
                break
            except KeyboardInterrupt:
                raise KeyboardInterrupt
            except:
                LOG.error(helper.exc_info())
                time.sleep(10)


    def run(self):
        while True:
            try:
                self.do_iteration()
            except DBQueueEvent.NothingToDoError:
                time.sleep(5)
            except KeyboardInterrupt:
                raise KeyboardInterrupt
            except:
                LOG.error(helper.exc_info())
                time.sleep(10)


    def start(self, daemon=False):
        if daemon:
            super(DBQueueEvent, self).start()
        else:
            self.run()


def configure(config, args=None):
    global CONFIG
    helper.update_config(
        config['connections']['mysql'], CONFIG['connections']['mysql'])
    if 'email' in config:
        helper.update_config(config['email'], CONFIG['email'])
    if 'system' in config and 'instances_connection_timeout' in config['system']:
        timeout = config['system']['instances_connection_timeout']
        CONFIG['instances_connection_timeout'] = timeout
    if 'dbqueue_event' in config:
        helper.update_config(config['dbqueue_event'], CONFIG)
    helper.update_config(config_to=CONFIG, args=args)
    helper.validate_config(CONFIG)
    helper.configure_log(
            log_level=CONFIG['verbosity'],
            log_file=CONFIG['log_file'],
            log_size=1024 * 1000
            )


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
    parser.add_argument('-t', '--instances-connection-timeout', type=int, default=None,
            help='instances connection timeout')
    parser.add_argument('-v', '--verbosity', action='count', default=None,
            help='increase output verbosity')
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
        daemon = DBQueueEvent()
        if args.start:
            LOG.info('Start')
            if helper.check_pid(CONFIG['pid_file']):
                LOG.critical('Another copy of process already running. Exit')
                return
            daemon.start(daemon=not args.no_daemon)
        elif args.stop:
            LOG.info('Stop')
            daemon.stop()
        else:
            print 'Usage %s -h' % sys.argv[0]
    except SystemExit:
        pass
    except KeyboardInterrupt:
        LOG.critical('KeyboardInterrupt')
        return
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
