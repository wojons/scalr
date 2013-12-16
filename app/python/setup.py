import os
import sys

requires = [
        'pytz',
        'pyaml',
        'gevent',
        'psutil',
        'sqlsoup',
        'pymysql',
        'cherrypy',
        'requests',
        'sqlalchemy',
        ]

if sys.version_info < (2, 7):
    requires.append(['argparse'])


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
