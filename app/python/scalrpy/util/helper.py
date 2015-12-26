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

import os
import sys
import pwd
import grp
import time
import types
import psutil
import gevent
import ctypes
import logging
import smtplib
import datetime
import functools
import traceback
import threading
import subprocess
import gevent.pool
import email.charset
import multiprocessing
import logging.handlers
import greenlet as greenlet_mod
import uuid
import pymysql.err

from textwrap import dedent
from email.mime.text import MIMEText
from ctypes.util import find_library

from scalrpy import LOG
from scalrpy import exceptions


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


log_level_map = {
    'CRITICAL': logging.CRITICAL,
    'ERROR': logging.ERROR,
    'WARNING': logging.WARNING,
    'INFO': logging.INFO,
    'DEBUG': logging.DEBUG,
}


def configure_log(log_level=logging.INFO, log_file=None, log_size=1024 * 10):
    LOG.setLevel(log_level)
    if log_file:
        if not os.path.exists(os.path.dirname(log_file)):
            os.makedirs(os.path.dirname(log_file), mode=0o755)
        prompt = dedent("[%(asctime)15s][%(module)20s][%(process)6d] %(levelname)10s %(message)s")
        file_frmtr = logging.Formatter(prompt, datefmt='%d/%b/%Y %H:%M:%S')
        file_hndlr = logging.handlers.RotatingFileHandler(log_file, maxBytes=log_size, backupCount=1)
        file_hndlr.setFormatter(file_frmtr)
        file_hndlr.setLevel(log_level)
        LOG.addHandler(file_hndlr)


def check_pid(pid_file):
    if os.path.exists(pid_file):
        with open(pid_file) as f:
            pid = f.read().strip()
        if pid and os.path.exists('/proc/' + pid):
            return True
    return False


def kill(pid):
    msg = 'Kill process: %s' % pid
    LOG.debug(msg)
    ps = psutil.Process(pid)
    ps.kill()


def kill_children(pid):
    parent = psutil.Process(pid)
    for child in parent.children(recursive=True):
        msg = 'Kill child process: %s' % child.pid
        LOG.debug(msg)
        child.kill()


def exc_info(where=False):
    exc_type, exc_obj, exc_tb = sys.exc_info()
    file_name, line_num, func_name, text = traceback.extract_tb(exc_tb)[-1]
    if where:
        msg = '%s %s\n    file: %s, line: %s' % (
            exc_type, exc_obj, os.path.normpath(file_name), line_num)
    else:
        msg = '%s %s' % (exc_type, exc_obj)
    return msg


def validate_config(config, key=None):
    """
    >>> validate_config({'1': '1', '2': None})
    Traceback (most recent call last):
        ...
    AssertionError: Wrong config value '2:None'
    """
    if type(config) == dict:
        for k in config:
            validate_config(config[k], key='%s:%s' % (key, k) if key else k)
    else:
        value = config
        assert config is not None, "Wrong config value '%s: %s'" % (key, value)


def update_config(config_from=None, config_to=None, args=None):
    """
    >>> config_from = {'1': '1', '2': '2', '3': '3'}
    >>> config_to = {'1': None, '2': None}
    >>> update_config(config_from, config_to)
    >>> assert config_to == {'1': '1', '2': '2'}
    >>> config_to = {'1': None, '2': None}
    >>> class Args(object):pass
    >>> args = Args()
    >>> args.__dict__ = {'1': '1'}
    >>> update_config(config_to=config_to, args=args)
    >>> assert config_to == {'1': '1', '2': None}
    """
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
        self._lock = threading.Lock()

    def get(self, timeout=None):
        if timeout:
            time_until = time.time() + timeout
        while True:
            with self._lock:
                if self._free:
                    o = self._free.pop()
                    o = o if self._validator(o) else self._factory()
                    self._used.append(o)
                    return o
                elif len(self._used) < self._size:
                    o = self._factory()
                    self._used.append(o)
                    return o
            if timeout and time.time() >= time_until:
                msg = "Pool get timeout, used: {used}, free: {free}".format(
                    used=len(self._used), free=len(self._free))
                raise exceptions.TimeoutError(msg)
            time.sleep(0.1)

    def put(self, o):
        with self._lock:
            if o not in self._used:
                return
            self._used.remove(o)
            if self._validator(o):
                self._free.append(o)

    def remove(self, o):
        with self._lock:
            if o in self._used:
                self._used.remove(o)
            if o in self._free:
                self._free.remove(o)


def x1x2(farm_id):
    """
    >>> x1x2(1), x1x2(2), x1x2(3), x1x2(4), x1x2(5)
    ('x1x6', 'x2x7', 'x3x8', 'x4x9', 'x5x0')
    """
    i = int(str(farm_id)[-1]) - 1
    x1 = str(i - 5 * (i / 5) + 1)[-1]
    x2 = str(i - 5 * (i / 5) + 6)[-1]
    return 'x%sx%s' % (x1, x2)


def apply_async(f):
    def wrapper(*args, **kwds):
        pool = kwds.pop('pool')
        return pool.apply_async(f, args=args, kwds=kwds)
    return wrapper


def process(daemon=False, name=None):
    def wrapper1(f):
        def wrapper2(*args, **kwds):
            p = multiprocessing.Process(name=name, target=f, args=args, kwargs=kwds)
            p.daemon = daemon
            p.start()
            return p
        return wrapper2
    return wrapper1


def thread(daemon=False, name=None):
    def wrapper1(f):
        def wrapper2(*args, **kwds):
            t = threading.Thread(name=name, target=f, args=args, kwargs=kwds)
            t.daemon = daemon
            t.start()
            return t
        return wrapper2
    return wrapper1


def greenlet(f):
    @functools.wraps(f)
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
    msg = "Creating pid file: %s" % pid_file
    LOG.debug(msg)
    if not os.path.exists(os.path.dirname(pid_file)):
        os.makedirs(os.path.dirname(pid_file), mode=0o755)
    file(pid_file, 'w+').write('%s\n' % pid)


def delete_file(file_path):
    msg = "Deleting file: %s" % file_path
    LOG.debug(msg)
    if os.path.exists(file_path):
        try:
            os.remove(file_path)
        except:
            handle_error()


def daemonize(stdin='/dev/null', stdout='/dev/null', stderr='/dev/null'):
    LOG.debug("Daemonize")

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


def get_uid(user):
    if type(user) is int:
        uid = user
    else:
        try:
            uid = pwd.getpwnam(user).pw_uid
        except KeyError:
            raise Exception("User '{0}' not found".format(user))
    return uid


def get_gid(group):
    if type(group) is int:
        gid = group
    else:
        try:
            gid = grp.getgrnam(group).gr_gid
        except KeyError:
            raise Exception("Group '{0}' not found".format(group))
    return gid


def set_uid(user):
    uid = get_uid(user)
    os.setuid(uid)
    LOG.debug("Set uid: {0}".format(uid))


def set_gid(group):
    gid = get_gid(group)
    os.setgid(gid)
    LOG.debug("Set gid: {0}".format(gid))


def chown(path, user=None, group=None):
    uid = get_uid(user) if user else -1
    gid = get_gid(group) if group else -1
    os.chown(path, uid, gid)


def chunks(data, chunk_size):
    """
    >>> data = range(10)
    >>> out = [chunk for chunk in chunks(data, 3)]
    >>> assert out == [[0, 1, 2], [3, 4, 5], [6, 7, 8], [9]]
    """
    for i in xrange(0, len(data), chunk_size):
        yield data[i:i + chunk_size]


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


def colorize(color, text):
    return color + text + Color.ENDC


def set_proc_name(name):
    libc = ctypes.CDLL(find_library('c'))
    libc.prctl(15, ctypes.c_char_p(name), 0, 0, 0)


class Color(object):
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'


class StdOutStreamHandler(logging.StreamHandler):

    def __init__(self):
        super(StdOutStreamHandler, self).__init__(stream=sys.stdout)
        self.setLevel(logging.DEBUG)

    def emit(self, record):
        if record.levelno > logging.INFO:
            return
        logging.StreamHandler.emit(self, record)


class StdErrStreamHandler(logging.StreamHandler):

    def __init__(self):
        super(StdErrStreamHandler, self).__init__(stream=sys.stderr)
        self.setLevel(logging.WARNING)

    def emit(self, record):
        if record.levelno < logging.WARNING:
            return
        logging.StreamHandler.emit(self, record)


def call(cmd, input=None, **kwds):
    """
    >>> call("echo 'hello'")
    ("'hello'\\n", '')
    >>> call("echo 'hello'", shell=True)
    ('hello\\n', '')
    """
    if 'stdout' not in kwds:
        kwds.update({'stdout': subprocess.PIPE})
    if 'stderr' not in kwds:
        kwds.update({'stderr': subprocess.PIPE})
    if 'stdin' not in kwds:
        kwds.update({'stdin': subprocess.PIPE})

    msg = "Call '%s'" % cmd
    LOG.debug(msg)
    if 'shell' in kwds and kwds['shell']:
        p = subprocess.Popen(cmd, **kwds)
    else:
        p = subprocess.Popen(cmd.split(), **kwds)
    stdout, stderr = p.communicate(input=input)
    return stdout, stderr, p.returncode


class GPool(gevent.pool.Group):

    def __init__(self, *args, **kwds):
        try:
            self.pool_size = kwds.pop('pool_size')
        except:
            self.pool_size = None
        super(GPool, self).__init__(*args, **kwds)

    def wait(self):
        while self.pool_size and len(self) >= self.pool_size:
            gevent.sleep(0.05)


import ssl

from requests.adapters import HTTPAdapter
from requests.packages.urllib3.poolmanager import PoolManager


class HttpsAdapter(HTTPAdapter):

    """"Transport adapter" that allows to use TLS v1"""

    def init_poolmanager(self, connections, maxsize, block=False):
        self.poolmanager = PoolManager(num_pools=connections,
                                       maxsize=maxsize,
                                       block=block,
                                       ssl_version=ssl.PROTOCOL_TLSv1)


def next_month(dtime):
    one_day = datetime.timedelta(days=1)
    new_dtime = dtime + one_day
    while dtime.month == new_dtime.month:
        new_dtime += one_day
    new_dtime = new_dtime.replace(day=1, hour=0, minute=0, second=0, microsecond=0)
    return new_dtime


def previous_month(dtime):
    delta = datetime.timedelta(days=dtime.day + 1)
    new_dtime = dtime - delta
    new_dtime = new_dtime.replace(day=1, hour=0, minute=0, second=0, microsecond=0)
    return new_dtime


def pkg_type_by_name(name):
    if name.lower() in ('windows'):
        return 'win'
    if name.lower() in ('debian', 'ubuntu'):
        return 'deb'
    if name.lower() in ('fedora', 'oracle', 'oel', 'centos', 'redhat'):
        return 'rpm'
    return None


def get_free_space(path):
    s = os.statvfs(path)
    return (s.f_bavail * s.f_frsize)


def get_scalr_meta(tags):
    keys_map = {'v1': ['env_id', 'farm_id', 'farm_role_id', 'server_id']}
    types_map = {'env_id': int, 'farm_id': int, 'farm_role_id': int, 'server_id': str}
    data = tags.split(':')
    version = data[0]
    assert version in keys_map, 'Unsupported scalr-meta version {0}'.format(version)
    meta = dict(zip(keys_map[version], data[1:]))
    for k, v in meta.items():
        if v:
            meta[k] = types_map[k](v)
    if meta['server_id']:
        try:
            uuid.UUID(meta['server_id'])
        except:
            handle_error(message='Invalid scalr-meta: {}'.format(tags))
    return meta


class PeriodicalTask(object):

    def __init__(self, task, period=None, timeout=None, before=None, after=None, on_error=None):
        self.task = task
        if isinstance(self.task, types.FunctionType):
            self.task_name = self.task.__name__
        else:
            self.task_name = self.task.__class__
        self.period = period
        self.timeout = timeout
        self.before = before
        self.after = after
        self.on_error = on_error
        self._stop = False
        self.start_time = None
        self.end_time = None

    def stop(self):
        self._stop = True

    @greenlet
    def __call__(self):
        self._stop = False
        while not self._stop:
            g = None
            try:
                self.start_time = time.time()
                LOG.debug('Start periodical task ({})'.format(self.task_name))
                self.task.task_info = {
                        'period': self.period,
                        'timeout': self.timeout,
                        'start_time': self.start_time,
                }
                if callable(self.before):
                    msg = 'Periodical task ({}) call before ({})'
                    msg = msg.format(self.task_name, self.before.__name__)
                    LOG.debug(msg)
                    self.before(self)
                g = gevent.spawn(self.task)
                g.get(timeout=self.timeout)
            except:
                if g and not g.ready():
                    g.kill()
                try:
                    if callable(self.on_error):
                        self.on_error(self)
                except:
                    msg = 'Periodical task ({}) on error ({}) failed'
                    msg = msg.format(self.task_name, self.on_error.__name__)
                    handle_error(message=msg)
                msg = 'Periodical task ({}) error: {}'
                msg = msg.format(self.task_name, exc_info(where=False))
                handle_error(message=msg)
            finally:
                try:
                    if callable(self.after):
                        try:
                            msg = 'Periodical task ({}) call after ({})'
                            msg = msg.format(self.task_name, self.after.__name__)
                            LOG.debug(msg)
                            self.after(self)
                        except:
                            msg = 'After ({0}) failed'.format(self.after.__name__)
                            handle_error(message=msg)
                    self.end_time = time.time()
                    task_time = self.end_time - self.start_time
                    msg = 'End task ({0}): {1:.1f} seconds'
                    msg = msg.format(self.task_name, task_time)
                    LOG.info(msg)
                    if self.period:
                        next_time = self.start_time + self.period
                        while time.time() < next_time and not self._stop:
                            time.sleep(0.5)
                except:
                    msg = 'Task ({}) finally failed'.format(self.task_name)
                    handle_error(message=msg)


def log_exception(f):
    def wrapper(*args, **kwds):
        try:
            return f(*args, **kwds)
        except:
            LOG.exception('Exception')
            raise
    return wrapper


def timeit(f):
    def wrapper(*args, **kwds):
        start_time = time.time()
        try:
            return f(*args, **kwds)
        finally:
            end_time = time.time()
            msg = 'TIMEIT %s.%s: %s' % (f.__module__, f.__name__, end_time - start_time)
            LOG.debug(msg)
    return wrapper


def handle_error(message=None, level='exception'):
    c, e, t = sys.exc_info()
    if message:
        message = message.rstrip().rstrip('.') + '. Reason: {}'.format(exc_info())
    else:
        message = exc_info()
    if isinstance(e, (
                      KeyboardInterrupt,
                      GeneratorExit,
                      greenlet_mod.GreenletExit,
                      gevent.Timeout,
                     )
                  ):
        LOG.debug(message)
        raise
    if isinstance(e, SystemExit) and sys.exc_info()[1].args[0] == 0:
        raise
    logging_map = {
        'debug': LOG.debug,
        'info': LOG.info,
        'warning': LOG.warning,
        'error': LOG.error,
        'critical': LOG.critical,
        'exception': LOG.exception,
    }
    if isinstance(e, pymysql.err.Error):
        logging_map[min(level, 'error')](message)
    else:
        logging_map[level](message)
