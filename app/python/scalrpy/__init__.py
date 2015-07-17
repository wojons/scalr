__version__ = '1.2'

import logging

LOG = logging.getLogger('ScalrPy')

from textwrap import dedent

from scalrpy.util import helper


LOG.setLevel(logging.INFO)

prompt = dedent(
    "[%(asctime)15s][%(module)20s][%(process)d] \033[92m%(levelname)10s\033[0m %(message)s")
stdout_frmtr = logging.Formatter(prompt, datefmt='%d/%b/%Y %H:%M:%S')
stdout_hndlr = helper.StdOutStreamHandler()
stdout_hndlr.setFormatter(stdout_frmtr)
LOG.addHandler(stdout_hndlr)

prompt = dedent(
    "[%(asctime)15s][%(module)20s][%(process)d] \033[91m%(levelname)10s\033[0m %(message)s")
stderr_frmtr = logging.Formatter(prompt, datefmt='%d/%b/%Y %H:%M:%S')
stderr_hndlr = helper.StdErrStreamHandler()
stderr_hndlr.setFormatter(stderr_frmtr)
LOG.addHandler(stderr_hndlr)
