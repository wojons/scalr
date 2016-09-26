import os
import logging
from textwrap import dedent

LOG = logging.getLogger('ScalrPyTests')

scalarizr_key = '8mYTcBxiE70DtXCBRjn7AMuTQNzBJJcTa5uFok24X40ePafq1gUyyg=='
crypto_algo = dict(name="des_ede3_cbc", key_size=24, iv_size=8)


def configure_log(log_file):
    if not os.path.exists(os.path.dirname(log_file)):
        os.makedirs(os.path.dirname(log_file), mode=0o755)
    prompt = dedent("[%(asctime)15s][%(module)20s][%(process)d] %(levelname)10s %(message)s")
    file_frmtr = logging.Formatter(prompt, datefmt='%d/%b/%Y %H:%M:%S')
    file_hndlr = logging.handlers.RotatingFileHandler(
        log_file,
        mode='w',
        maxBytes=1024 * 10)
    file_hndlr.setFormatter(file_frmtr)
    file_hndlr.setLevel(logging.DEBUG)
    LOG.addHandler(file_hndlr)
    LOG.setLevel(logging.DEBUG)
