import os
import sys

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.abspath(os.path.join(cwd, '../../..'))
sys.path.insert(0, scalrpy_dir)

import uuid
import time
import pymysql
import pymysql.cursors
import subprocess


class World(object):
    pass


world = World()


def drop_db(config):
    conn = pymysql.connect(
            user=config['user'],
            passwd=config['pass'],
            host=config['host'],
            cursorclass=pymysql.cursors.DictCursor)
    conn.autocommit(True)
    cur = conn.cursor()
    try:
        cur.execute('drop database `%s`' % config['name'])
        return True
    except pymysql.err.InternalError as e:
        if e.args[0] in (1008, 1049):
            return True
        else:
            raise e
    finally:
        cur.close()
        conn.close()


def create_db(config):
    user = config['user']
    passwd = config['pass']
    host = config['host']
    name = config['name']

    conn = pymysql.connect(
            user=user,
            passwd=passwd,
            host=host,
            cursorclass=pymysql.cursors.DictCursor)
    conn.autocommit(True)
    cur = conn.cursor()
    try:
        cur.execute('create database `%s`' % name)
    except (pymysql.err.ProgrammingError, pymysql.err.InternalError) as e:
        if e.args[0] not in (1007, 1049):
            raise e
    finally:
        cur.close()
        conn.close()

    if passwd:
        cmd = "mysql -h {host} -u{user} -p{passwd} {name} < {sql_file}"
    else:
        cmd = "mysql -h {host} -u{user} {name} < {sql_file}"
    sql_file = os.path.join(scalrpy_dir, 'tests/fixtures/%s.sql' % name)

    p = subprocess.Popen(
        cmd.format(host=host, user=user, passwd=passwd, name=name, sql_file=sql_file),
        shell=True,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE
    )
    stdout, stderr = p.communicate()
    if stderr.strip():
        raise Exception(stderr)
    return True


class Script(object):

    app_cls = None
    name = None

    def __init__(self, opts=None):
        self.opts = opts or ''
        self.app = self.app_cls(argv=self.opts.split() + ['stop'])
        self.app.load_config()
        self.app.configure()

    def start(self):
        name = self.name
        opts = self.opts or ''

        cmd = "/usr/bin/python {scalrpy_dir}/scalrpy/{name}.py {opts} start 2>&1 >/dev/null".format(
                scalrpy_dir=scalrpy_dir, name=name, opts=opts)

        subprocess.Popen(cmd, shell=True)

        time.sleep(1)

        ps = subprocess.Popen('ps -ef'.split(), stdout=subprocess.PIPE)
        output = ps.stdout.read()
        ps.stdout.close()
        ps.wait()
        string = "{scalrpy_dir}/scalrpy/{name}.py {opts} start".format(
                scalrpy_dir=scalrpy_dir, name=name, opts=opts)
        return string in output

    def stop(self):
        name = self.name
        opts = self.opts or ''

        cmd = "/usr/bin/python {scalrpy_dir}/scalrpy/{name}.py {opts} stop 2>&1 >/dev/null".format(
                scalrpy_dir=scalrpy_dir, name=name, opts=opts)

        subprocess.Popen(cmd, shell=True)

        time.sleep(2)

        ps = subprocess.Popen('ps -ef'.split(), stdout=subprocess.PIPE)
        output = ps.stdout.read()
        ps.stdout.close()
        ps.wait()
        string = "{scalrpy_dir}/scalrpy/{name}.py".format(
                scalrpy_dir=scalrpy_dir, name=name)
        return string not in output

    def prepare(self):
        return


ScriptCls = None


def start_system_service(name):
    #cmd = '/etc/init.d/%s start 1>/dev/null' % name
    cmd = "service '%s' start 1>/dev/null" % name
    ps = subprocess.Popen(cmd, shell=True)
    ps.communicate()

    ps = subprocess.Popen('ps -ef'.split(), stdout=subprocess.PIPE)
    output = ps.stdout.read()
    ps.stdout.close()
    ps.wait()
    time.sleep(1)
    return '%s' % name in output


def stop_system_service(name):
    #cmd = '/etc/init.d/%s stop 1>/dev/null' % name
    cmd = "service '%s' stop 1>/dev/null" % name
    ps = subprocess.Popen(cmd, shell=True)
    ps.communicate()

    ps = subprocess.Popen('ps -ef'.split(), stdout=subprocess.PIPE)
    output = ps.stdout.read()
    ps.stdout.close()
    ps.wait()
    time.sleep(1)
    return '%s' % name not in output


def generate_server_id():
    return str(uuid.uuid4())


def generate_id():
    return int(time.time() * 1000000) & 0xFFFFFF


def generate_history_id():
    return str(uuid.uuid4())


def generate_webhook_id():
    return str(uuid.uuid4())


def generate_endpoint_id():
    return str(uuid.uuid4())


def generate_event_id():
    return str(uuid.uuid4())


def generate_cloud_credentials_id():
    return uuid.uuid4().hex[-12:]
