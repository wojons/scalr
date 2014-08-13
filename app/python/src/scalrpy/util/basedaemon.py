import os
import sys
import atexit
import logging

from scalrpy.util import helper


LOG = logging.getLogger('ScalrPy')


class BaseDaemon(object):

    def __init__(self,
            pid_file=None,
            stdin='/dev/null',
            stdout='/dev/null',
            stderr='/dev/null'):

        self.pid_file = pid_file
        self.stdin = stdin
        self.stdout = stdout
        self.stderr = stderr

    def start(self):
        self._daemonize()
        LOG.info('Start')
        self.run()

    def stop(self):
        LOG.info('Stop')
        if not self.pid_file:
            raise Exception("You must specify pid file")

        try:
            pf = file(self.pid_file, 'r')
            pid = int(pf.read().strip())
            pf.close()
        except IOError:
            LOG.error("Pid file %s dosn't exist" % self.pid_file)
            return
        except ValueError:
            LOG.error("Wrong value in pid file %s" % self.pid_file)
            self._delete_pid_file()
            return

        try:
            if helper.check_pid(self.pid_file):
                helper.kill_child(pid)
                helper.kill(pid)
        except:
            LOG.error(helper.exc_info())

    def run(self):
        """
        Override this method in derived class
        """
        pass

    def _create_pid_file(self):
        helper.create_pid_file(self.pid_file or '/var/run/%s.pid' % os.getpid())

    def _delete_pid_file(self):
        LOG.debug('Removing pid file %s' % self.pid_file)
        os.remove(self.pid_file)

    def _daemonize(self):
        LOG.info('Daemonize')

        # first fork
        try:
            pid = os.fork()
            if pid > 0:
                sys.exit(0)
        except OSError as e:
            LOG.error(e)
            raise

        os.chdir('/')
        os.setsid()
        os.umask(0)

        # second fork
        try:
            pid = os.fork()
            if pid > 0:
                sys.exit(0)
        except OSError as e:
            LOG.critical(e)
            raise

        self._create_pid_file()

        atexit.register(self._delete_pid_file)

        # redirect standard file descriptors
        sys.stdout.flush()
        sys.stderr.flush()
        si = file(self.stdin, 'r')
        so = file(self.stdout, "a+")
        se = file(self.stderr, "a+", 0)
        os.dup2(si.fileno(), sys.stdin.fileno())
        os.dup2(so.fileno(), sys.stdout.fileno())
        os.dup2(se.fileno(), sys.stderr.fileno())

