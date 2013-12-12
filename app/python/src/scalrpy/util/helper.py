import os
import sys
import time
import psutil
import logging
import threading
import logging.handlers
import subprocess as subps

from scalrpy import __version__


def configure_log(log_level=1, log_file=None, log_size=1024*10):
    level = {
            0:logging.CRITICAL,
            1:logging.ERROR,
            2:logging.WARNING,
            3:logging.INFO,
            4:logging.DEBUG,
            }

    if log_level not in level.keys():
        sys.stderr.write('Wrong logging level. Set DEBUG\n')
        log_level = 4

    log = logging.getLogger('ScalrPy')
    log.setLevel(level[log_level])
    frmtr = logging.Formatter(
            '[%(asctime)s]' + \
            '[%s]' % __version__ + \
            '[%(module)s]' + \
            '[%(process)d]' + \
            '[%(thread)d] ' + \
            '%(levelname)s %(message)s', datefmt='%d/%b/%Y %H:%M:%S'
            )

    hndlr = logging.StreamHandler(sys.stderr)
    hndlr.setLevel(level[log_level])
    hndlr.setFormatter(frmtr)
    log.addHandler(hndlr)

    if log_file:
        hndlr = logging.handlers.RotatingFileHandler(
                log_file,
                mode='a',
                maxBytes=log_size
                )
        hndlr.setLevel(level[log_level])
        hndlr.setFormatter(frmtr)
        log.addHandler(hndlr)


def check_pid(pid_file):
    if os.path.exists(pid_file):
        pid = open(pid_file).read().strip()
        if pid and os.path.exists('/proc/' + pid):
            return False
    with open(pid_file, 'w+') as fp:
        fp.write(str(os.getpid()))
    return True


def kill_ps(pid, child=False):
    parent = psutil.Process(pid)
    if child:
        for child in parent.get_children(recursive=True):
            child.kill()
    parent.kill()


def exc_info(line_no=True):
    exc_type, exc_obj, exc_tb = sys.exc_info()
    if line_no:
        return '%s %s line: %s' % (str(exc_type), str(exc_obj), str(exc_tb.tb_lineno))
    else:
        return '%s %s' % (str(exc_type), str(exc_obj))


def validate_config(config, key=None):
    if type(config) == dict:
        for k in config:
            validate_config(config[k], key='%s:%s' % (key, k) if key else k)
    else:
        value = config
        assert config != None , 'Wrong config value %s:%s' % (key, value) 


def update_config(config_from=None, config_to=None, args=None):
    if not config_from:
        config_from = dict()
    if config_to == None:
        config_to = dict()
    for k, v in config_from.iteritems():
        if k not in config_to:
            config_to[k] = v
        if type(v) == dict:
            update_config(config_from[k], config_to[k])
        elif v != None:
            config_to[k] = v

    if not hasattr(args, '__dict__'):
        return

    for k, v in vars(args).iteritems():
        if v is not None:
            config_to.update({k:v})


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
                        raise Exception('Pool.get timeout')
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


def call(cmd, **kwds):
    if 'stdout' not in kwds:
        kwds.update({'stdout':subps.PIPE})
    if 'stderr' not in kwds:
        kwds.update({'stderr':subps.PIPE})
    p = subps.Popen(cmd.split(), **kwds)
    stdout, stderr = p.communicate()
    return stdout, stderr


def apply_async(f):
    def new_f(*args, **kwds):
        pool = kwds.pop('pool')
        return pool.apply_async(f, args=args, kwds=kwds)
    return new_f


def thread(f):
    def new_f(*args, **kwds):
        t = threading.Thread(target=f, args=args, kwargs=kwds)
        t.start()
        return t
    return new_f


def create_pid_file(pid_file):
    pid = str(os.getpid())
    file(pid_file,'w+').write('%s\n' % pid)


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
