from gevent import monkey
monkey.patch_all(subprocess=True)

import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.normpath(os.path.join(cwd, '../../..'))
sys.path.insert(0, scalrpy_dir)
scalrpytests_dir = os.path.join(cwd, '../..')
sys.path.insert(0, scalrpytests_dir)

import time

from gevent import pywsgi

from scalrpy.util import cryptotool
from scalrpy.analytics_processing import AnalyticsProcessing

from scalrpytests.steplib import lib
from scalrpytests.steplib.steps import *

from lettuce import step, before, after


dtime = time.strftime("%Y-%m-%d %H:00:00", time.gmtime(time.time() - 3600))


class AnalyticsProcessingScript(lib.Script):

    app_cls = AnalyticsProcessing
    name = 'analytics_processing'


lib.ScriptCls = AnalyticsProcessingScript 

