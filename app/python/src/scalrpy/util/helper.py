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

import os
import sys
import pwd
import grp
import time
import psutil
import gevent
import logging
import smtplib
import traceback
import threading
import email.charset
import multiprocessing
import logging.handlers
import subprocess as subps

from email.mime.text import MIMEText

from scalrpy import __version__


email.charset.add_charset('utf-8', email.charset.QP, email.charset.QP)


def patch_gevent():

    def handle_error(self, context, type, value, tb):
        if not issubclass(type, self.NOT_ERROR):
            #self.print_exception(context, type, value, tb)
            pass
        if context is None or issubclass(type, self.SYSTEM_ERROR):
            self.handle_system_error(type, value)

    from gevent import hub

    hub.Hub.handle_error = handle_error


class StdOutStreamHandler(logging.StreamHandler):

    def __init__(self):
        #super(StdOutStreamHandler, self).__init__(stream=sys.stdout)
        logging.StreamHandler.__init__(self, sys.stdout)
        self.setLevel(logging.DEBUG)

    def emit(self, record):
        if record.levelno > logging.INFO:
            return
        logging.StreamHandler.emit(self, record)


class StdErrStreamHandler(logging.StreamHandler):

    def __init__(self):
        #super(StdErrStreamHandler, self).__init__(stream=sys.stderr)
        logging.StreamHandler.__init__(self, sys.stderr)
        self.setLevel(logging.WARNING)

    def emit(self, record):
        if record.levelno < logging.WARNING:
            return
        logging.StreamHandler.emit(self, record)


def configure_log(log_level=1, log_file=None, log_size=1024 * 10):
    level_map = {
        0: logging.CRITICAL,
        1: logging.ERROR,
        2: logging.WARNING,
        3: logging.INFO,
        4: logging.DEBUG,
    }

    if log_level not in level_map:
        sys.stderr.write('Wrong logging level. Set DEBUG\n')
        log_level = 4

    log = logging.getLogger('ScalrPy')
    log.setLevel(level_map[log_level])
    frmtr = logging.Formatter(
        '[%(asctime)s]' +\
        '[%s]' % __version__ +\
        '[%(module)s]' +\
        '[%(process)d]' +\
        '[%(thread)d] ' +\
        '%(levelname)s %(message)s', datefmt='%d/%b/%Y %H:%M:%S'
    )

    stdout_hndlr = StdOutStreamHandler()
    stdout_hndlr.setFormatter(frmtr)
    stderr_hndlr = StdErrStreamHandler()
    stderr_hndlr.setFormatter(frmtr)
    log.addHandler(stdout_hndlr)
    log.addHandler(stderr_hndlr)

    if log_file:
        if not os.path.exists(os.path.dirname(log_file)):
            os.makedirs(os.path.dirname(log_file), 0755)
        file_hndlr = logging.handlers.RotatingFileHandler(
            log_file,
            mode='a',
            maxBytes=log_size
        )
        file_hndlr.setLevel(level_map[log_level])
        file_hndlr.setFormatter(frmtr)
        log.addHandler(file_hndlr)


def check_pid(pid_file):
    if os.path.exists(pid_file):
        pid = open(pid_file).read().strip()
        if pid and os.path.exists('/proc/' + pid):
            return True
    return False


def kill(pid):
    ps = psutil.Process(pid)
    ps.kill()


def kill_child(pid):
    parent = psutil.Process(pid)
    for child in parent.get_children(recursive=True):
        child.kill()


def exc_info(line_no=True):
    exc_type, exc_obj, exc_tb = sys.exc_info()
    file_name, line_num, func_name, text = traceback.extract_tb(exc_tb)[-1]
    return '%s %s, file: %s, line: %s' % (exc_type, exc_obj, os.path.basename(file_name), line_num)


def validate_config(config, key=None):
    if type(config) == dict:
        for k in config:
            validate_config(config[k], key='%s:%s' % (key, k) if key else k)
    else:
        value = config
        assert config is not None, "Wrong config value '%s:%s'" % (key, value)


def update_config(config_from=None, config_to=None, args=None):
    if not config_from:
        config_from = dict()
    if config_to is None:
        config_to = dict()
    for k, v in config_from.iteritems():
        if k not in config_to:
            continue
        if type(v) == dict:
            update_config(config_from[k], config_to[k])
        elif v is not None:
            config_to[k] = v

    if not hasattr(args, '__dict__'):
        return

    for k, v in vars(args).iteritems():
        if v is not None:
            config_to.update({k: v})


class Pool(object):

    def __init__(self, factory, validator, size):
        self._used = list()
        self._free = list()
        self._size = size
        self._factory = factory
        self._validator = validator
        self._get_lock = threading.Lock()
        self._put_lock = threading.Lock()

    def get(self, timeout=None):
        self._get_lock.acquire()
        try:
            if timeout:
                time_until = time.time() + timeout
            while True:
                if self._free:
                    o = self._free.pop()
                    o = o if self._validator(o) else self._factory()
                    self._used.append(o)
                    return o
                elif len(self._used) < self._size:
                    o = self._factory()
                    self._used.append(o)
                    return o
                else:
                    if timeout and time.time() >= time_until:
                        msg = "Pool get timeout, used: {used}, free: {free}".format(
                                used=len(self._used), free=len(self._free))
                        raise Exception(msg)
                    time.sleep(0.33)
                    continue
        finally:
            self._get_lock.release()

    def put(self, o):
        self._put_lock.acquire()
        try:
            if o not in self._used:
                return
            self._used.remove(o)
            if self._validator(o):
                self._free.append(o)
        finally:
            self._put_lock.release()

    def remove(self, o):
        if o in self._used:
            self._used.remove(o)
        if o in self._free:
            self._free.remove(o)


def x1x2(farm_id):
    i = int(str(farm_id)[-1]) - 1
    x1 = str(i - 5 * (i / 5) + 1)[-1]
    x2 = str(i - 5 * (i / 5) + 6)[-1]
    return 'x%sx%s' % (x1, x2)


def call(cmd, input=None, **kwds):
    if 'stdout' not in kwds:
        kwds.update({'stdout': subps.PIPE})
    if 'stderr' not in kwds:
        kwds.update({'stderr': subps.PIPE})
    if 'stdin' not in kwds:
        kwds.update({'stdin': subps.PIPE})
    if 'shell' in kwds and kwds['shell']:
        p = subps.Popen(cmd, **kwds)
    else:
        p = subps.Popen(cmd.split(), **kwds)
    stdout, stderr = p.communicate(input=input)
    return stdout, stderr


def apply_async(f):
    def wrapper(*args, **kwds):
        pool = kwds.pop('pool')
        return pool.apply_async(f, args=args, kwds=kwds)
    return wrapper


def process(daemon=False):
    def wrapper1(f):
        def wrapper2(*args, **kwds):
            p = multiprocessing.Process(target=f, args=args, kwargs=kwds)
            p.daemon = daemon
            p.start()
            return p
        return wrapper2
    return wrapper1


def thread(daemon=False):
    def wrapper1(f):
        def wrapper2(*args, **kwds):
            t = threading.Thread(target=f, args=args, kwargs=kwds)
            t.daemon = daemon
            t.start()
            return t
        return wrapper2
    return wrapper1


def greenlet(f):
    def wrapper(*args, **kwds):
        g = gevent.spawn(f, *args, **kwds)
        gevent.sleep(0)
        return g
    return wrapper


def retry_f(f, args=None, kwds=None, retries=1, retry_timeout=10, excs=None):
    args = args or ()
    kwds = kwds or {}
    excs = excs or ()
    while True:
        try:
            return f(*args, **kwds)
        except:
            retries -= 1
            if sys.exc_info()[0] not in excs or retries < 0:
                raise
            time.sleep(retry_timeout)


def retry(retries, retry_timeout, *excs):
    def wrapper1(f):
        def wrapper2(*args, **kwds):
            return retry_f(
                f, args=args, kwds=kwds,
                retries=retries, retry_timeout=retry_timeout, excs=excs
            )
        return wrapper2
    return wrapper1


def create_pid_file(pid_file):
    pid = str(os.getpid())
    if not os.path.exists(os.path.dirname(pid_file)):
        os.makedirs(os.path.dirname(pid_file), 0755)
    file(pid_file, 'w+').write('%s\n' % pid)


def delete_file(file_path):
    if os.path.exists(file_path):
        os.remove(file_path)


def daemonize(stdin='/dev/null', stdout='/dev/null', stderr='/dev/null'):
    # first fork
    pid = os.fork()
    if pid > 0:
        sys.exit(0)

    os.chdir('/')
    os.setsid()
    os.umask(0)

    # second fork
    pid = os.fork()
    if pid > 0:
        sys.exit(0)

    # redirect standard file descriptors
    sys.stdout.flush()
    sys.stderr.flush()
    si = file(stdin, 'r')
    so = file(stdout, "a+")
    se = file(stderr, "a+", 0)
    os.dup2(si.fileno(), sys.stdin.fileno())
    os.dup2(so.fileno(), sys.stdout.fileno())
    os.dup2(se.fileno(), sys.stderr.fileno())


def set_uid(user):
    if type(user) is int:
        uid = user
    else:
        uid = pwd.getpwnam(user).pw_uid
    os.setuid(uid)


def set_gid(group):
    if type(group) is int:
        gid = group
    else:
        gid = grp.getgrnam(group).gr_gid
    os.setgid(gid)


def chown(path, user, group):
    if type(user) is int:
        uid = user
    else:
        uid = pwd.getpwnam(user).pw_uid
    if type(group) is int:
        gid = group
    else:
        gid = grp.getgrnam(group).gr_gid
    os.chown(path, uid, gid)


def chunks(data, chunk_size):
    for i in xrange(0, len(data), chunk_size):
        yield data[i:i+chunk_size]


def send_email(email_from, email_to, subject, message):
    mail = MIMEText(message.encode('utf-8'), _charset='utf-8')
    mail['From'] = email_from
    mail['To'] = email_to
    mail['Subject'] = subject
    server = smtplib.SMTP('localhost')
    server.sendmail(mail['From'], mail['To'], mail.as_string())


def _get_szr_conn_info(server, port, instances_connection_policy):
    ip = {
        'public': server['remote_ip'],
        'local': server['local_ip'],
        'auto': server['remote_ip'] if server['remote_ip'] else server['local_ip'],
    }[instances_connection_policy]
    headers = {}
    if server['platform'] == 'ec2' and 'ec2.vpc.id' in server and 'router.vpc.ip' in server:
        if server['remote_ip']:
            ip = server['remote_ip']
        else:
            headers.update({
                'X-Receiver-Host': server['local_ip'],
                'X-Receiver-Port': port,
            })
            ip = server['router.vpc.ip']
            port = 80
    return ip, port, headers


def get_szr_ctrl_conn_info(server, instances_connection_policy='public'):
    return _get_szr_conn_info(server, server['scalarizr.ctrl_port'], instances_connection_policy)


def get_szr_api_conn_info(server, instances_connection_policy='public'):
    return _get_szr_conn_info(server, server['scalarizr.api_port'], instances_connection_policy)


def get_szr_updc_conn_info(server, instances_connection_policy='public'):
    return _get_szr_conn_info(server, server['scalarizr.updc_port'], instances_connection_policy)
