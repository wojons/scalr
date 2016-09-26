import os
import uuid
import time
import random
from datetime import datetime

from scalrpy.util import dbmanager
from scalrpy.util import analytics

from scalrpytests.steplib import lib

from lettuce import *
from lettuce import step


cwd = os.path.dirname(os.path.abspath(__file__))
etc_dir = os.path.abspath(os.path.join(cwd, '../../etc'))


def strip(string):
    return string.strip('\'"').strip('"\'')


@step(u"White Rabbit waits (\d+) seconds")
def wait(step, seconds):
    time.sleep(int(seconds))


@step(u"White Rabbit has config '(.*)'")
def has_config(step, config):
    script = lib.ScriptCls('-c %s' % config)
    lib.world.config = script.app.config.copy()


@step(u"White Rabbit drops scalr_test database")
def drop_db_scalr(step):
    assert lib.drop_db(lib.world.config['connections']['mysql'])


@step(u"White Rabbit creates scalr_test database")
def create_db_scalr(step):
    assert lib.create_db(lib.world.config['connections']['mysql'])


@step(u"White Rabbit drops analytics_test database")
def drop_db_analytics(step):
    assert lib.drop_db(lib.world.config['connections']['analytics'])


@step(u"White Rabbit creates analytics_test database")
def create_db_analytics(step):
    assert lib.create_db(lib.world.config['connections']['analytics'])


@step(u"White Rabbit starts system service '(.*)'")
def start_system_service(step, name):
    assert lib.start_system_service(name)


@step(u"White Rabbit stops system service '(.*)'")
def stop_system_service(step, name):
    assert lib.stop_system_service(name)


@step(u"White Rabbit starts script with options '(.*)'")
def start_script(step, opts):
    script = lib.ScriptCls(opts)
    script.prepare()
    script.start()
    lib.world.script = script


@step(u"White Rabbit stops script with options '(.*)'")
def stop_script(step, opts):
    script = lib.ScriptCls(opts)
    script.stop()
    lib.world.script = None


@step(u"Database has messages records")
def fill_messages(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.messages = {}
    for record in step.hashes:
        record['messageid'] = record.get('messageid', "'%s'" % str(uuid.uuid4()))
        record['status'] = record.get('status', 0)
        record['handle_attempts'] = record.get('handle_attempts', 0)
        record['dtlasthandleattempt'] = record.get(
            'dthandleattempt', "'%s'" % datetime.now().strftime('%Y-%m-%d %H:%M:%S'))
        record['message'] = record.get('message', "'Some message'")
        record['server_id'] = record.get('server_id', "'%s'" % lib.generate_server_id())
        record['type'] = record.get('type', "'out'")
        record['message_version'] = record.get('message_version', 2)
        record['message_name'] = record.get('message_name', "''")
        record['message_format'] = record.get('message_format', "'json'")
        record['event_id'] = record.get('event_id', "'%s'" % lib.generate_id())
        query = (
            "INSERT messages "
            "(messageid, status, handle_attempts, dtlasthandleattempt, message, "
            "server_id, type, message_version, message_name, message_format, event_id) "
            "VALUES ({messageid}, {status}, {handle_attempts}, {dtlasthandleattempt}, "
            "{message}, {server_id}, {type}, {message_version}, {message_name}, "
            "{message_format}, {event_id})"
        ).format(**record)
        db.execute(query)
        message = db.execute(
            'SELECT * FROM messages where messageid={0}'.format(record['messageid'].strip()))[0]
        lib.world.messages[message['messageid']] = message


@step(u"Database has servers records")
def fill_servers(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.servers = {}
    for record in step.hashes:
        record['server_id'] = record.get('server_id', "'%s'" % lib.generate_server_id())
        record['farm_id'] = record.get('farm_id', lib.generate_id())
        record['farm_roleid'] = record.get('farm_roleid', lib.generate_id())
        record['client_id'] = record.get('client_id', lib.generate_id())
        record['env_id'] = record.get('env_id', lib.generate_id())
        record['platform'] = record.get(
            'platform', random.choice(["'ec2'", "'gce'", "'idcf'", "'openstack'"]))
        record['cloud_location'] = record.get('cloud_location', "'us-east-1'")
        record['status'] = record.get('server_status') or record.get('status', "'Running'")
        record['remote_ip'] = record.get('remote_ip', 'NULL')
        record['local_ip'] = record.get('local_ip', 'NULL')
        record['index'] = record.get('index', 1)
        record['instance_type_name'] = record.get('instance_type_name', 'NULL')
        record['type'] = record.get('type', 'NULL')
        record['os_type'] = record.get('os_type', random.choice(["'linux'", "'windows'"]))
        query = (
            "INSERT INTO servers "
            "(server_id, farm_id, farm_roleid, client_id, env_id, platform, cloud_location, "
            "status, remote_ip, local_ip, `index`, instance_type_name, type, os_type) "
            "VALUES ({server_id}, {farm_id}, {farm_roleid}, {client_id}, {env_id}, "
            "{platform}, {cloud_location}, {status}, {remote_ip}, {local_ip}, {index}, "
            "{instance_type_name}, {type}, {os_type})"
        ).format(**record)
        db.execute(query)
        server = db.execute("SELECT * FROM servers where server_id='%s'" %
                            strip(record['server_id']))[0]
        for k, v in server.items():
            if isinstance(v, basestring):
                server[k] = "'%s'" % v
        lib.world.servers[server['server_id']] = server


@step(u"Database has servers_history records")
def fill_servers_history(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        record['server_id'] = record.get('server_id', "'%s'" % lib.generate_server_id())
        server = lib.world.servers.get(record['server_id'], {})
        record['cloud_server_id'] = record['cloud_server_id']
        record['farm_id'] = record.get('farm_id') or server.get('farm_id') or lib.generate_id()
        record['farm_roleid'] = record.get('farm_roleid') or server.get('farm_roleid') or lib.generate_id()
        record['client_id'] = record.get('client_id') or server.get('client_id') or lib.generate_id()
        record['env_id'] = record.get('env_id') or server.get('env_id') or lib.generate_id()
        record['platform'] = record.get('platform') or server.get('platform') or random.choice(
                ["'ec2'", "'gce'", "'idcf'", "'openstack'"])
        record['cloud_location'] = record.get('cloud_location') or server.get('cloud_location') or "'us-east-1'"
        record['instance_type_name'] = record.get('instance_type_name') or server.get('instance_type_name') or 'NULL'
        record['type'] = record.get('type') or server.get('type') or 'NULL'
        record['server_index'] = record.get('server_index') or server.get('index') or 1
        record['role_id'] = record.get('role_id') or lib.generate_id()
        record['project_id'] = uuid.UUID(strip(record.get('project_id', '00000000-0000-0000-0000-000000000001'))).hex.upper()
        record['cc_id'] = uuid.UUID(strip(record.get('cc_id', '00000000-0000-0000-0000-000000000001'))).hex.upper()
        query = (
            "INSERT INTO servers_history "
            "(server_id, cloud_server_id, farm_id, farm_roleid, role_id, client_id, env_id, "
            "platform, cloud_location, server_index, instance_type_name, type, project_id, cc_id) "
            "VALUES ({server_id}, {cloud_server_id}, {farm_id}, {farm_roleid}, {role_id}, "
            "{client_id}, {env_id}, {platform}, {cloud_location}, {server_index}, "
            "{instance_type_name}, {type}, UNHEX('{project_id}'), UNHEX('{cc_id}'))"
        ).format(**record)
        db.execute(query)


@step(u"Database has webhook_history records")
def fill_webhook_history(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.webhook_history = {}
    for record in step.hashes:
        record['history_id'] = uuid.UUID(strip(record.get('history_id', "'%s'" %
                                         lib.generate_history_id()))).hex.upper()
        record['webhook_id'] = uuid.UUID(strip(record.get('webhook_id', "'%s'" %
                                         lib.generate_webhook_id()))).hex.upper()
        record['endpoint_id'] = uuid.UUID(strip(record.get('endpoint_id', "'%s'" %
                                          lib.generate_endpoint_id()))).hex.upper()
        record['event_id'] = record.get('event_id', "%s" % lib.generate_id())
        record['status'] = record.get('status', 0)
        record['payload'] = record.get('payload', "'This is text'")
        query = (
            "INSERT INTO webhook_history "
            "(history_id, webhook_id, endpoint_id, event_id, status, payload) "
            "VALUES (UNHEX('{history_id}'), UNHEX('{webhook_id}'), UNHEX('{endpoint_id}'), "
            "{event_id}, {status}, {payload})"
        ).format(**record)
        db.execute(query)

        query = "SELECT * FROM webhook_history where history_id=UNHEX('%s')" \
                % strip(record['webhook_id']).replace('-', '')
        webhook = db.execute(query)[0]
        lib.world.webhook_history[strip(record['webhook_id'])] = webhook


@step(u"Database has webhook_endpoints records")
def fill_webhook_endpoints(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.webhook_endpoints = {}
    for record in step.hashes:
        record['endpoint_id'] = record.get('endpoint_id', "'%s'" %
                                           lib.generate_endpoint_id()).replace('-', '')
        record['url'] = record.get('url', 'http://localhost')
        query = (
            "INSERT INTO webhook_endpoints "
            "(endpoint_id, url) "
            "VALUES (UNHEX('{endpoint_id}'), {url})"
        ).format(**record)
        db.execute(query)

        query = "SELECT * FROM webhook_endpoints where endpoint_id=UNHEX('%s')" \
                % strip(record['endpoint_id']).replace('-', '')
        endpoint = db.execute(query)[0]
        lib.world.webhook_endpoints[endpoint['endpoint_id']] = endpoint


@step(u"Database has server_properties records")
def fill_server_properties(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.server_properties = {}
    for record in step.hashes:
        query = (
            "INSERT INTO server_properties "
            "(server_id, name, value) "
            "VALUES ({server_id}, {name}, {value})"
        ).format(**record)
        db.execute(query)
        lib.world.server_properties.setdefault(strip(record['server_id']), {}).update(
            {strip(record['name']): strip(record['value'])})


@step(u"Database has clients records")
def fill_clients(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.clients = {}
    for record in step.hashes:
        record['id'] = record.get('id', lib.generate_id())
        record['status'] = record.get('status', "'Active'")
        query = (
            "INSERT INTO clients "
            "(id, status) "
            "VALUES ({id}, {status})"
        ).format(**record)
        db.execute(query)
        client = db.execute('SELECT * FROM clients where id=%s' % record['id'])[0]
        lib.world.clients[client['id']] = client


@step(u"Database has client_environments records")
def fill_client_environments(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.client_environments = {}
    for record in step.hashes:
        record['id'] = record.get('id', lib.generate_id())
        record['client_id'] = record.get('client_id', lib.generate_id())
        record['status'] = record.get('status', 'Active')
        lib.world.client_environments[record['id']] = record
        query = (
            "INSERT INTO client_environments "
            "(id, client_id, status) "
            "VALUES ({id}, {client_id}, {status})"
        ).format(**record)
        db.execute(query)


@step(u"Database has client_environment_properties records")
def fill_client_environment_properties(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        record['group'] = record.get('group', "''")
        record['cloud'] = record.get('cloud', 'NULL')
        query = (
            "INSERT INTO client_environment_properties "
            "(env_id, name, value, `group`, cloud) "
            "VALUES ({env_id}, {name}, {value}, {group}, {cloud})"
        ).format(**record)
        db.execute(query)


@step(u"Database has cloud_credentials records")
def fill_cloud_credentials(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        record['id'] = record.get('id', lib.generate_id())
        record['account_id'] = record.get('account_id', lib.generate_id())
        record['env_id'] = record.get('env_id', lib.generate_id())
        record['cloud'] = record.get('cloud', random.choice(["'ec2'", "'gce'", "'idcf'", "'openstack'"]))
        query = (
            "INSERT INTO cloud_credentials "
            "(id, account_id, env_id, cloud) "
            "VALUES ({id}, {account_id}, {env_id}, {cloud})"
        ).format(**record)
        db.execute(query)


@step(u"Database has cloud_credentials_properties records")
def fill_cloud_credentials_properties(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        query = (
            "INSERT INTO cloud_credentials_properties "
            "(cloud_credentials_id, name, value) "
            "VALUES ({cloud_credentials_id}, {name}, {value})"
        ).format(**record)
        db.execute(query)


@step(u"Database has environment_cloud_credentials records")
def fill_environment_cloud_credentials(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.client_environments = {}
    for record in step.hashes:
        record['env_id'] = record.get('env_id', lib.generate_id())
        record['cloud'] = record.get('cloud', random.choice(["'ec2'", "'gce'", "'idcf'", "'openstack'"]))
        record['cloud_credentials_id'] = record.get('cloud_credentials_id', lib.generate_cloud_credentials_id())
        query = (
            "INSERT INTO environment_cloud_credentials "
            "(env_id, cloud, cloud_credentials_id) "
            "VALUES ({env_id}, {cloud}, {cloud_credentials_id})"
        ).format(**record)
        db.execute(query)


@step(u"Database has farms records")
def fill_farms(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    lib.world.farms = {}
    for record in step.hashes:
        record['id'] = record.get('id', lib.generate_id())
        record['clientid'] = record.get('clientid', lib.generate_id())
        record['env_id'] = record.get('env_id', lib.generate_id())
        record['hash'] = record.get('hash', "'914d929db09834'")
        record['status'] = record.get('status', "1")
        lib.world.farms[record['id']] = record
        query = (
            "INSERT INTO farms "
            "(id, clientid, env_id, hash, status) "
            "VALUES ({id}, {clientid}, {env_id}, {hash}, {status})"
        ).format(**record)
        db.execute(query)


@step(u"Database has farm_settings records")
def fill_farm_settings(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        query = (
            "INSERT INTO farm_settings "
            "(farmid, name, value) "
            "VALUES ({farmid}, {name}, {value})"
        ).format(**record)
        db.execute(query)


@step(u"Database has farm_role_settings records")
def fill_farm_role_settings(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        query = (
            "INSERT INTO farm_role_settings "
            "(farm_roleid, name, value) "
            "VALUES ({farm_roleid}, {name}, {value})"
        ).format(**record)
        db.execute(query)


@step(u"Database has farm_roles records")
def fill_farm_roles(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        query = (
            "INSERT INTO farm_roles "
            "(id, role_id) "
            "VALUES ({id}, {role_id})"
        ).format(**record)
        db.execute(query)


@step(u"Database has roles records")
def fill_roles(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        query = (
            "INSERT INTO roles "
            "(id, os_id) "
            "VALUES ({id}, {os_id})"
        ).format(**record)
        db.execute(query)


dtime = time.strftime("%Y-%m-%d %H:00:00", time.gmtime(time.time() - 3600))


@step(u"Database has poller_sessions records")
def fill_poller_sessions(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        record['sid'] = uuid.UUID(record['sid']).hex
        record['dtime'] = record.get('dtime', "'%s'" % dtime)
        record['url'] = record.get('url', "''")
        record['cloud_account'] = record.get('cloud_account', "''")
        query = (
            "INSERT INTO poller_sessions "
            "(sid, account_id, env_id, dtime, platform, url, cloud_location, cloud_account) "
            "VALUES (UNHEX('{sid}'), {account_id}, {env_id}, {dtime}, {platform}, "
            "{url}, {cloud_location}, {cloud_account})"
        ).format(**record)
        db.execute(query)


@step(u"Analytics database has managed records")
def fill_managed(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        query = (
            "SELECT platform "
            "FROM poller_sessions "
            "WHERE sid=UNHEX('{sid}') "
            "LIMIT 1"
        ).format(**record)
        record['sid'] = uuid.UUID(record['sid']).hex
        record['server_id'] = record['server_id'].replace('-', '')
        query = (
            "INSERT INTO managed "
            "(sid, server_id, instance_type, os) "
            "VALUES (UNHEX('{sid}'), UNHEX({server_id}), {instance_type}, {os})"
        ).format(**record)
        db.execute(query)


@step(u"Analytics database has notmanaged records")
def fill_notmanaged(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        record['sid'] = uuid.UUID(record['sid']).hex
        query = (
            "INSERT INTO notmanaged "
            "(sid, instance_id, instance_type, os) "
            "VALUES (UNHEX('{sid}'), {instance_id}, {instance_type}, {os})"
        ).format(**record)
        db.execute(query)


@step(u"Analytics database has price_history records")
def fill_price_history(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        record['price_id'] = uuid.UUID(record['price_id']).hex
        record['applied'] = record.get('applied', "'%s'" % dtime.split()[0])
        record['url'] = record.get('url', "''")
        query = (
            "INSERT INTO price_history "
            "(price_id, platform, url, cloud_location, account_id, applied) "
            "VALUES (UNHEX('{price_id}'), {platform}, {url}, {cloud_location}, "
            "{account_id}, {applied})"
        ).format(**record)
        db.execute(query)


@step(u"Analytics database has prices records")
def fill_prices(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        record['price_id'] = uuid.UUID(record['price_id']).hex
        query = (
            "INSERT INTO prices "
            "(price_id, instance_type, os, cost) "
            "VALUES (UNHEX('{price_id}'), {instance_type}, {os}, {cost})"
        ).format(**record)
        db.execute(query)


@step(u"Analytics database has quarterly_budget records")
def fill_quarterly_budget(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        record['subject_id'] = uuid.UUID(record['subject_id']).hex
        record['year'] = record.get('year', "'%s'" % dtime.split()[0].split('-')[0])
        try:
            record['cumulativespend'] = float(record['cumulativespend'])
        except ValueError:
            record['cumulativespend'] = 'NULL'
        query = (
            "INSERT INTO quarterly_budget "
            "(year, subject_type, subject_id, quarter, budget, cumulativespend, spentondate) "
            "VALUES ({year}, {subject_type}, UNHEX('{subject_id}'), {quarter}, {budget}, "
            "{cumulativespend}, {spentondate})"
        ).format(**record)
        db.execute(query)


@step(u"Analytics database has settings records")
def fill_settings(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        query = (
            "INSERT INTO settings (id,value) "
            "VALUES ({id}, {value})"
        ).format(**record)
        db.execute(query)


@step(u"Database has events records")
def fill_events(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        record['id'] = record.get('id', lib.generate_id())
        record['event_id'] = record.get('event_id', "'%s'" % lib.generate_id())
        query = (
            "INSERT INTO events "
            "(id, event_id) "
            "VALUES ({id}, {event_id})"
        ).format(**record)
        db.execute(query)


@step(u"White Rabbit checks messages table")
def check_messages(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    query = "SELECT count(*) AS count FROM messages"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes), result[0]['count']
    for record in step.hashes:
        query = (
            "SELECT * "
            "FROM messages "
            "WHERE messageid={messageid}"
        ).format(**record)
        res = db.execute(query)
        assert res
        assert int(record['status']) == int(res[0]['status']), res[0]['status']
        assert int(record['handle_attempts']) == int(
            res[0]['handle_attempts']), res[0]['handle_attempts']


@step(u"White Rabbit checks webhook_history table")
def check_webhook_history(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    query = "SELECT count(*) AS count FROM webhook_history"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes), result[0]['count']
    for record in step.hashes:
        record['history_id'] = uuid.UUID(strip(record['history_id'])).hex
        query = (
            "SELECT * "
            "FROM webhook_history "
            "WHERE history_id=UNHEX('{history_id}')"
        ).format(**record)
        res = db.execute(query)
        assert res
        assert int(record['status']) == int(res[0]['status']), res[0]['status']
        a = str(record['response_code']).strip()
        b = 'NULL' if res[0]['response_code'] is None else str(res[0]['response_code']).strip()
        assert a == b, '%s %s' % (a, b)
        assert strip(record['error_msg']) == res[0]['error_msg'], res[0]['error_msg']
        assert int(record['handle_attempts']) == int(res[0]['handle_attempts'])


@step(u"White Rabbit checks usage_h table")
def check_usage_h(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = "SELECT count(*) AS count FROM usage_h"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes)
    for record in step.hashes:
        record['dtime'] = record.get('dtime', '%s' % dtime)
        record['cc_id'] = uuid.UUID(strip(record['cc_id'])).hex
        record['project_id'] = uuid.UUID(strip(record['project_id'])).hex
        record['url'] = record.get('url', '')
        for k, v in record.items():
            record[k] = strip(v)
        query = (
            "SELECT HEX(ui.id) id FROM usage_items ui JOIN usage_types ut ON ut.id=ui.usage_type "
            "WHERE ut.cost_distr_type={ut_cost_distr_type} AND ut.name='{ut_name}' AND ui.name='{ui_name}'"
        ).format(ut_cost_distr_type=record['usage_types.cost_distr_type'],
                 ut_name=record['usage_types.name'],
                 ui_name=record['usage_items.name'])
        result = db.execute(query)
        record['usage_item'] = result[0]['id'].lower()
        usage_id = analytics.Usage_h(record)['usage_id']
        query = (
            "SELECT * "
            "FROM usage_h "
            "WHERE usage_id=UNHEX('{usage_id}')"
        ).format(usage_id=usage_id)
        res = db.execute(query)
        assert res, usage_id
        assert float(record['num']) == float(res[0]['num'])
        assert float(record['cost']) == float(res[0]['cost']), '%s != %s' % (record['cost'], float(res[0]['cost']))
        for k, v in record.items():
            if k in ['usage_types.name', 'usage_types.cost_distr_type', 'usage_items.name',
                     'project_id', 'cc_id', 'usage_item']:
                continue
            assert str(v) == str(res[0][k]), '%s, %s != %s' % (k, str(v), str(res[0][k]))


@step(u"White Rabbit checks nm_usage_h table")
def check_nm_usage_h(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = "SELECT count(*) AS count FROM nm_usage_h"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes)
    for record in step.hashes:
        record['dtime'] = record.get('dtime', '%s' % dtime)
        record['cc_id'] = uuid.UUID(strip(record['cc_id'])).hex
        record['url'] = record.get('url', '')
        for k, v in record.iteritems():
            record[k] = strip(v)
        usage_id = analytics.NM_usage_h(record)['usage_id']
        query = (
            "SELECT * "
            "FROM nm_usage_h "
            "WHERE usage_id=UNHEX('{usage_id}')"
        ).format(usage_id=usage_id)
        res = db.execute(query)
        assert res
        assert int(record['num']) == int(res[0]['num'])
        assert float(record['cost']) == float(res[0]['cost']), float(res[0]['cost'])


@step(u"White Rabbit checks usage_d table")
def check_usage_d(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = "SELECT count(*) AS count FROM usage_d"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes)
    for record in step.hashes:
        record['date'] = record.get('date', "'%s'" % dtime.split()[0])
        record['cc_id'] = uuid.UUID(strip(record['cc_id'])).hex
        record['project_id'] = uuid.UUID(strip(record['project_id'])).hex
        query = (
            "SELECT * "
            "FROM usage_d "
            "WHERE date={date} "
            "AND platform={platform} "
            "AND cc_id=UNHEX('{cc_id}') "
            "AND project_id=UNHEX('{project_id}') "
            "AND farm_id={farm_id} "
            "AND cost={cost}"
        ).format(**record)
        assert db.execute(query)


@step(u"White Rabbit checks nm_usage_d table")
def check_nm_usage_d(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = "SELECT count(*) AS count FROM nm_usage_d"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes)
    for record in step.hashes:
        record['date'] = record.get('date', "'%s'" % dtime.split()[0])
        record['cc_id'] = uuid.UUID(strip(record['cc_id'])).hex
        query = (
            "SELECT * "
            "FROM nm_usage_d "
            "WHERE date={date} "
            "AND platform={platform} "
            "AND cc_id=UNHEX('{cc_id}') "
            "AND env_id={env_id} "
            "AND cost={cost}"
        ).format(**record)
        assert db.execute(query)


@step(u"White Rabbit checks farm_usage_d table")
def check_farm_usage_d(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = "SELECT count(*) AS count FROM farm_usage_d"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes)
    query = (
        "SELECT *, HEX(usage_item) usage_item "
        "FROM farm_usage_d")
    results = db.execute(query)
    for record in step.hashes:
        record['date'] = record.get('date', "'%s'" % dtime.split()[0])
        record['cc_id'] = uuid.UUID(strip(record['cc_id'])).bytes
        record['project_id'] = uuid.UUID(strip(record['project_id'])).bytes
        query = (
            "SELECT HEX(ui.id) id FROM usage_items ui "
            "WHERE ui.name={name}"
        ).format(name=record['usage_items.name'])
        result = db.execute(query)
        record['usage_item'] = result[0]['id'].upper()
        for k, v in record.iteritems():
            record[k] = strip(v)
        for result in results:
            try:
                result['usage_item'] = result.get('.usage_item', result['usage_item'])
                for k, v in record.iteritems():
                    if k in ('usage_items.name',):
                        continue
                    assert record[k] == str(result[k])
                break
            except AssertionError:
                continue
        else:
            assert False


@step(u"White Rabbit checks quarterly_budget table")
def check_quarterly_budget(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = "SELECT count(*) AS count FROM quarterly_budget"
    result = db.execute(query)
    assert int(result[0]['count']) == len(step.hashes)
    for record in step.hashes:
        record['year'] = record.get('year', "'%s'" % dtime.split()[0].split('-')[0])
        record['subject_id'] = uuid.UUID(strip(record['subject_id'])).hex
        if record['spentondate'] == '':
            record['spentondate'] = "'%s'" % dtime
        query = (
            "SELECT * "
            "FROM quarterly_budget "
            "WHERE year={year} "
            "AND subject_type={subject_type} "
            "AND subject_id=UNHEX('{subject_id}') "
            "AND quarter={quarter} "
            "AND cumulativespend={cumulativespend} "
            "AND DATE(spentondate)=DATE({spentondate})"
        ).format(**record)
        assert db.execute(query), query


@step(u"White Rabbit checks poller_sessions table")
def check_poller_sessions(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = (
        "SELECT * "
        "FROM poller_sessions"
    )
    results = db.execute(query)
    assert len(results) == len(step.hashes), len(results)
    for record in step.hashes:
        for k, v in record.iteritems():
            record[k] = strip(v)
        for result in results:
            try:
                for k, v in record.iteritems():
                    assert record[k] == str(result[k])
                break
            except AssertionError:
                continue
        else:
            assert False


@step(u"White Rabbit checks managed table")
def check_managed(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = (
        "SELECT * "
        "FROM managed"
    )
    results = db.execute(query)
    assert len(results) == len(step.hashes), len(results)
    for record in step.hashes:
        record['server_id'] = uuid.UUID(strip(record['server_id'])).bytes
        for k, v in record.iteritems():
            record[k] = strip(v)
        for result in results:
            try:
                for k, v in record.iteritems():
                    assert record[k] == str(result[k])
                break
            except AssertionError:
                continue
        else:
            assert False


@step(u"White Rabbit checks notmanaged table")
def check_notmanaged(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    query = (
        "SELECT * "
        "FROM notmanaged"
    )
    results = db.execute(query)
    assert len(results) == len(step.hashes), len(results)
    for record in step.hashes:
        for k, v in record.iteritems():
            record[k] = strip(v)
        for result in results:
            try:
                for k, v in record.iteritems():
                    assert record[k] == str(result[k])
                break
            except AssertionError:
                continue
        else:
            assert False


@step(u"Analytics database has new prices")
def update_prices(step):
    db = dbmanager.DB(lib.world.config['connections']['analytics'])
    for record in step.hashes:
        record['price_id'] = uuid.UUID(strip(record['price_id'])).hex
        query = (
            "UPDATE prices "
            "SET cost={cost} "
            "WHERE price_id=UNHEX('{price_id}') "
            "AND instance_type={instance_type} "
            "AND os={os}"
        ).format(**record)
        db.execute(query)


@step(u"White Rabbit checks events table")
def check_events(step):
    db = dbmanager.DB(lib.world.config['connections']['mysql'])
    for record in step.hashes:
        query = (
            "SELECT * "
            "FROM events "
            "WHERE event_id={event_id}"
        ).format(**record)
        res = db.execute(query)
        assert res
        assert int(record['wh_completed']) == int(res[0]['wh_completed']), res[0]['wh_completed']
        assert int(record['wh_failed']) == int(res[0]['wh_failed']), res[0]['wh_failed']
