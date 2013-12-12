import sys
import time
import yaml
import socket
import smtplib
import logging
import argparse
import email.charset

from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import basedaemon

from email.mime.text import MIMEText

from scalrpy import __version__


email.charset.add_charset('utf-8', email.charset.QP, email.charset.QP)

socket.setdefaulttimeout(30)

CONFIG = {
    'connections':{
        'mysql':{
            'user':None,
            'pass':None,
            'host':None,
            'port':3306,
            'name':None,
            'pool_size':1,
            },
        },
    'email_from':None,
    'email_to':None,
    'no_daemon':False,
    'warning_threshold':250,
    'critical_threshold':500,
    'cratio':120,
    'interval':60,
    'log_file':'/var/log/scalr.msg-sender-alert.log',
    'pid_file':'/var/run/scalr.msg-sender-alert.pid',
    'verbosity':1,
    }

LOG = logging.getLogger('ScalrPy')


class MessagingAlert(basedaemon.BaseDaemon):

    def __init__(self):
        super(MessagingAlert, self).__init__(pid_file=CONFIG['pid_file'])


    def _get_messages_count(self):
        connection = dbmanager.make_connection(CONFIG['connections']['mysql'])
        cursor = connection.cursor()
        try:
            query = """SELECT count(*) """ +\
                    """FROM messages """ +\
                    """WHERE messages.type='out' AND """ +\
                    """messages.status=0 AND """ +\
                    """messages.message_version=2 AND """ +\
                    """messages.message_format='xml' AND """ +\
                    """UNIX_TIMESTAMP(messages.dtlasthandleattempt)+ """ +\
                    """messages.handle_attempts*%s<UNIX_TIMESTAMP()"""
            query = query % CONFIG['cratio']
            cursor.execute(query)
            return cursor.fetchone()['count(*)']
        finally:
            cursor.close()
            connection.close()


    def run(self):
        while True:
            try:
                count = self._get_messages_count()
                LOG.info(count)
                if count < CONFIG['warning_threshold']:
                    time.sleep(CONFIG['interval'])
                    continue
                message = 'Messaging alert. Messages do not processed: %s' % count
                mail = MIMEText(message.encode('utf-8'), _charset='utf-8')
                mail['From'] = CONFIG['email_from']
                mail['To'] = CONFIG['email_to']
                if count > CONFIG['critical_threshold']:
                    mail['Subject'] = 'Messaging critical alert'
                else:
                    mail['Subject'] = 'Messaging warning alert'
                LOG.debug('Send mail\n%s' % mail.as_string())
                try:
                    server = smtplib.SMTP('localhost')
                    server.sendmail(mail['From'], mail['To'], mail.as_string())
                except:
                    LOG.error('Send mail fail: %s' % helper.exc_info())
                time.sleep(CONFIG['interval'])
            except KeyboardInterrupt:
                raise KeyboardInterrupt
            except:
                LOG.error(helper.exc_info())
                time.sleep(10)


    def start(self, daemon=False):
        if daemon:
            super(MessagingAlert, self).start()
        else:
            self.run()


def configure(config, args=None):
    global CONFIG
    helper.update_config(config['connections']['mysql'], CONFIG['connections']['mysql'])
    if 'msg_sender_alert' in config:
        helper.update_config(config['msg_sender_alert'], CONFIG)
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
    group.add_argument('--start', action='store_true', default=False,
            help='start program')
    group.add_argument('--stop', action='store_true', default=False,
            help='stop program')
    parser.add_argument('--no-daemon', action='store_true',
            help='run in no daemon mode')
    parser.add_argument('--email-from', default=None,
            help='email address from')
    parser.add_argument('--email-to', default=None,
            help='email address to')
    parser.add_argument('--warning-threshold', type=int, default=None,
            help='warning threshold')
    parser.add_argument('--critical-threshold', type=int, default=None,
            help='critical threshold')
    parser.add_argument('-i', '--interval', type=int, default=None,
            help="execution interval in seconds")
    parser.add_argument('-p', '--pid-file', default=None,
            help='pid file')
    parser.add_argument('-l', '--log-file', default=None,
            help='log file')
    parser.add_argument('-c', '--config-file', default='./config.yml',
            help='config file')
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
        daemon = MessagingAlert()
        if args.start:
            LOG.info('Start')
            if not helper.check_pid(CONFIG['pid_file']):
                LOG.info('Another copy of process already running. Exit')
                sys.exit(0)
            daemon.start(daemon= not args.no_daemon)
        elif args.stop:
            LOG.info('Stop')
            daemon.stop()
        else:
            print 'Usage %s -h' % sys.argv[0]
    except KeyboardInterrupt:
        LOG.critical('KeyboardInterrupt')
        sys.exit(0)
    except SystemExit:
        pass
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
