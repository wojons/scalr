import os
import sys
import platform
import subprocess

requires = [
    'pytz',
    'boto',
    'pyyaml',
    'gevent',
    'psutil',
    'sqlsoup',
    'pymysql>=0.6.2',
    'httplib2',
    'cherrypy==3.2.6',
    'requests',
    #'pyopenssl', # uncomment this line if you need https
    'pycrypto',
    'sqlalchemy',
    'apache-libcloud==0.15.1',
    'google-api-python-client==1.3',
]

if sys.version_info < (2, 7):
    requires.append(['argparse'])

os_name, os_version = platform.dist()[0].lower(), platform.dist()[1]

print os_name, os_version

cmd = None
if os_name == 'ubuntu':
    if os_version >= '12':
        cmd = '/bin/bash %s/scripts/debian.sh' 
    else:
        cmd = '/bin/bash %s/scripts/ubuntu10.sh'
elif os_name in ['centos', 'redhat']:
    if os_version.startswith('5.'):
        cmd = '/bin/bash %s/scripts/centos5.sh'
    elif os_version.startswith('6.'):
        cmd = '/bin/bash %s/scripts/centos6.sh'
elif os_name == 'debian':
    cmd = '/bin/bash %s/scripts/debian.sh'

if not cmd:
    print 'Unsupported os'
    raise sys.exit(1)

cwd = os.path.dirname(os.path.abspath(__file__))
cmd = cmd % cwd

p = subprocess.Popen(cmd.split())
p.communicate()
if p.returncode != 0:
    sys.stderr.write('Error\n')
    raise sys.exit(1)

from setuptools import setup, find_packages

setup(
    name = 'ScalrPy',
    version = open(
        '%s/src/scalrpy/version' % os.path.dirname(os.path.abspath(__file__))
    ).read().strip(),
    author = "Scalr Inc.",
    author_email = "info@scalr.net",
    url = "https://scalr.net",
    license = 'ASL 2.0',
    description = ('Set of python scripts for Scalr'),
    package_dir = {'':'src'},
    packages = find_packages('src'),
    include_package_data = True,
    install_requires = requires,
)
