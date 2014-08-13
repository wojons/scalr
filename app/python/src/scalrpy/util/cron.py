import atexit
import logging

from scalrpy.util import helper


LOG = logging.getLogger('ScalrPy')


class Cron(object):

    def __init__(self, pid_file):
        self.pid_file = pid_file

    def _run(self):
        """
        Override this method in derived class
        """

    def start(self):
        LOG.debug('Starting...')
        try:
            helper.create_pid_file(self.pid_file)
        except:
            msg = "Can't create pid file %s. Start failed" % self.pid_file
            LOG.error(msg)
            return
        atexit.register(helper.delete_file, self.pid_file)
        LOG.info('Started')
        self._run()
        LOG.info('Stopped')

    def stop(self):
        LOG.debug('Stopping...')
        try:
            with file(self.pid_file, 'r') as pf:
                pid = int(pf.read().strip())
        except IOError:
            msg = "Can't open pid file %s. Stop failed" % self.pid_file
            LOG.error(msg)
            return
        try:
            helper.kill_child(pid)
            helper.kill(pid)
        except:
            LOG.error(helper.exc_info())
        LOG.info('Stopped')

