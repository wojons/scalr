from gevent import monkey
monkey.patch_all()

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.normpath(os.path.join(cwd, '../../..'))
sys.path.insert(0, scalrpy_dir)
scalrpytests_dir = os.path.join(cwd, '../..')
sys.path.insert(0, scalrpytests_dir)

import time
import random

from gevent import pywsgi

import scalrpytests
from scalrpy.util import cryptotool
from scalrpytests import LOG, configure_log

configure_log(os.path.join(os.path.dirname(__file__), 'test_msg_sender.log'))


def answer(environ, start_response):
    try:
        data = environ['wsgi.input'].readline()
        key = cryptotool.decrypt_key(scalrpytests.scalarizr_key)
        msg = cryptotool.decrypt_scalarizr(scalrpytests.crypto_algo, data, key)
        if msg == '400':
            start_response('400 NOT OK', [('Content-Type', 'text/html')])
        else:
            time.sleep(random.randint(3, 20) / 10.0)
            start_response('201 OK', [('Content-Type', 'text/html')])
        yield '<b>Hello world!</b>\n'
    except:
        LOG.exception('Answer exception')


if __name__ == '__main__':
    port = int(sys.argv[1])
    pywsgi.WSGIServer(('127.0.0.1', port), answer).serve_forever()
