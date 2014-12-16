import os
import sys
import subprocess


msg = (
    "WARNING!\n\n"
    "The Scalr Python components have been recently updated,\n"
    "and running `python setup.py install` is no longer required.\n\n"
    "If you are installing Scalr for the first time, you shouldn't be running this script.\n"
    "Please double check you are using the correct installation instructions for this version of Scalr.\n\n"
    "If you are upgrading an existing Scalr installation, you need to manually upgrade.\n"
    "Please review the instructions on this page: https://scalr-wiki.atlassian.net/wiki/x/FYD4\n\n"
    "Once you're done, proceed.\n"
)
print msg


value = None
while value not in ['y', 'yes', 'n', 'no']:
    value = raw_input("Are you ready to continue? y/n ")

if value in ['n', 'no']:
    print 'Abort'
    sys.exit(1)


python_cmd = [sys.executable, '-m', 'scalrpy.__init__']
try:
    return_code = subprocess.check_call(
            'cd /tmp && %s -m scalrpy.__init__' % sys.executable,
            stderr=subprocess.PIPE,
            shell=True)
    print 'ERROR!'
    print 'Found old ScalrPy installation.'
    print 'Please stop all ScalrPy scripts, uninstall ScalrPy with right pip executable and retry.'
    sys.exit(1)
except subprocess.CalledProcessError:
    pass

