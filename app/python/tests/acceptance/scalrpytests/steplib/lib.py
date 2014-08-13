import os
import sys
import time
import pymysql
import pymysql.cursors
import subprocess as subps

from scalrpy.util import dbmanager


def wait_sec(sec):
    time.sleep(sec)


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

    cmd = 'mysql -h {host} -u{user} -p{passwd} {name} <{sql_file}'
    pwd = os.path.dirname(__file__)
    sql_file = os.path.join(pwd, 'scalr.sql')

    p = subps.Popen(
        cmd.format(host=host, user=user, passwd=passwd, name=name, sql_file=sql_file),
        shell=True,
        stdout=subps.PIPE,
        stderr=subps.PIPE
    )
    stdout, stderr = p.communicate()
    if stderr:
        print stderr
        print stderr
        print stderr
        print stderr
        print stderr
        print stderr
    return not stderr.strip()


def start_daemon(name, config):
    cmd = 'python -m scalrpy.%s --start -vvvv -c %s' % (name, config)
    subps.Popen(cmd.split())

    time.sleep(0.5)

    ps = subps.Popen('ps -ef'.split(), stdout=subps.PIPE)
    output = ps.stdout.read()
    ps.stdout.close()
    ps.wait()

    return 'scalrpy.%s --start -vvvv -c %s' % (name, config) in output


def stop_daemon(name, config):
    cmd = 'python -m scalrpy.%s --stop -vvvv -c %s' % (name, config)
    subps.Popen(cmd.split())

    time.sleep(5)

    ps = subps.Popen('ps -ef'.split(), stdout=subps.PIPE)
    output = ps.stdout.read()
    ps.stdout.close()
    ps.wait()

    return 'scalrpy.%s --stop -vvvv -c %s' % (name, config) not in output


def start_service(name):
    cmd = 'service %s start' % name
    subps.call(cmd.split())

    return True


def stop_service(name):
    cmd = 'service %s stop' % name
    subps.call(cmd.split())

    return True
