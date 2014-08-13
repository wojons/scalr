# vim: tabstop=4 shiftwidth=4 softtabstop=4
#
# Copyright 2013, 2014 Scalr Inc.
#
# Licensed under the Apache License, Version 2.0 (the "License"); you may
# not use this file except in compliance with the License. You may obtain
# a copy of the License at
#
# http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.

import os
import sys
import time
import yaml
import socket
import logging
import cherrypy
import argparse
import multiprocessing
import multiprocessing.pool

from scalrpy.util import rrd
from scalrpy.util import snmp
from scalrpy.util import cron
from scalrpy.util import helper
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool
from scalrpy.util import szr_api

from scalrpy import __version__


CONFIG = {
    'connections': {
        'plotter': {
            'scheme': 'http',
            'bind_address': '0.0.0.0',
            'port': 8080,
            'pool_size': 100,
            'ssl_certificate': False,
            'ssl_private_key': False,
            'ssl_certificate_chain': False,
        },
        'mysql': {
            'user': None,
            'pass': None,
            'host': None,
            'port': 3306,
            'name': None,
            'pool_size': 10,
        },
    },
    'user': False,
    'group': False,
    'poller': False,
    'plotter': False,
    'pool_size': 100,
    'metrics': ['cpu', 'la', 'mem', 'net', 'io', 'snum'],
    'instances_connection_timeout': 10,
    'with_snmp': False,
    'no_daemon': False,
    'rrd': {
        'dir': None,
        'rrdcached_sock_path': '/var/run/rrdcached.sock',
    },
    'img': {
        'dir': None,
        'scheme': 'http',
        'host': None,
        'port': False,
        'path': '',
    },
    'interval': 120,
    'pid_file': '/var/run/scalr.load-statistics.pid',
    'log_file': '/var/log/scalr.load-statistics.log',
    'verbosity': 1,
}
SCALR_CONFIG = None
LOG = logging.getLogger('ScalrPy')


def _get_metrics_api(server):
    assert_msg = "Server:'%s' doesn't have a scalarizr key" % server['server_id']
    assert server['scalarizr.key'], assert_msg

    instances_connection_policy = SCALR_CONFIG.get(server['platform'], {}).get(
            'instances_connection_policy', SCALR_CONFIG['instances_connection_policy'])
    ip, port, headers = helper.get_szr_api_conn_info(server, instances_connection_policy)
    key = cryptotool.decrypt_key(server['scalarizr.key'])
    api_type = server['os_type']
    metrics = server['metrics']
    timeout = CONFIG['instances_connection_timeout']
    return szr_api.get_metrics(
            ip, port, key, api_type, metrics, headers=headers, timeout=timeout)


def _get_metrics_snmp(server):
    host = server['remote_ip']
    port = server['scalarizr.snmp_port']
    community = server['hash']
    metrics = server['metrics']
    return snmp.get_metrics(host, port, community, metrics)


def _is_snmp_enable(server):
    is_vpc = 'ec2.vpc.id' in server and not server['remote_ip']
    return CONFIG['with_snmp'] and server['os_type'] == 'linux' and not is_vpc


def _process_server(server):
    data = dict()
    try:
        try:
            data = _get_metrics_api(server)
        except:
            msg = 'Server: %s API failed: %s' % (server, helper.exc_info())
            LOG.warning(msg)
            if _is_snmp_enable(server):
                try:
                    data = _get_metrics_snmp(server)
                except:
                    msg = 'Server %s SNMP failed: %s' % (server['server_id'], helper.exc_info())
                    LOG.warning(msg)
    except:
        LOG.error(helper.exc_info())
    result = {
        'farm_id': server['farm_id'],
        'farm_roleid': server['farm_roleid'],
        'index': server['index'],
        'data': data,
    }
    return result


def get_rf_keys(result):
    r_key = os.path.join(
            CONFIG['rrd']['dir'],
            helper.x1x2(result['farm_id']),
            '%s' % result['farm_id'],
            'FR_%s' % result['farm_roleid']
            )
    f_key = os.path.join(
            CONFIG['rrd']['dir'],
            helper.x1x2(result['farm_id']),
            '%s' % result['farm_id'],
            'FARM'
            )
    return r_key, f_key


def _average(results, ra=None, fa=None, rs=None, fs=None):
    ra = ra or dict()
    fa = fa or dict()
    rs = rs or dict()
    fs = fs or dict()
    for result in results:
        try:
            r_key, f_key = get_rf_keys(result)
            if 'snum' in CONFIG['metrics']:
                rs.setdefault(r_key, {'snum': {'s_running': 0}})
                fs.setdefault(f_key, {'snum': {'s_running': 0}})
                rs[r_key]['snum']['s_running'] += 1
                fs[f_key]['snum']['s_running'] += 1
            if not result['data']:
                continue
            for metrics_group_name, metrics_data in result['data'].iteritems():
                if not metrics_data or metrics_group_name == 'io':
                    continue
                for metric_name, value in metrics_data.iteritems():
                    try:
                        ra.setdefault(r_key, {})
                        ra[r_key].setdefault(metrics_group_name, {})
                        ra[r_key][metrics_group_name].setdefault(metric_name, Average())

                        fa.setdefault(f_key, {})
                        fa[f_key].setdefault(metrics_group_name, {})
                        fa[f_key][metrics_group_name].setdefault(metric_name, Average())

                        ra[r_key][metrics_group_name][metric_name] += value
                        fa[f_key][metrics_group_name][metric_name] += value
                    except:
                        LOG.error(helper.exc_info())
        except:
            LOG.error(helper.exc_info())
    return ra, fa, rs, fs


class Average(object):

    def __init__(self):
        self._count = 0
        self._value = None

    def __iadd__(self, other):
        if other is None:
            return self
        if self._value is None:
            self._value = float(other)
        else:
            self._value = (self._value * self._count + other) / (self._count + 1)
        self._count += 1
        return self

    def __str__(self):
        return str(self._value)

    def __repr__(self):
        return str(self._value)

    def __int__(self):
        return int(self._value)

    def __float__(self):
        return float(self._value)


class Poller(object):

    def __init__(self):
        self._db = dbmanager.ScalrDB(CONFIG['connections']['mysql'], pool_size=1)

    def _get_servers(self):
        query = (
                "SELECT f.id farm_id, f.hash, s.server_id, s.farm_roleid, s.index, "
                "s.remote_ip, s.local_ip, s.env_id, s.os_type, s.platform "
                "FROM servers s "
                "JOIN farms f ON s.farm_id=f.id "
                "JOIN clients c ON s.client_id=c.id "
                "JOIN client_environments ce ON s.env_id=ce.id "
                "WHERE c.status='Active' "
                "AND ce.status='Active' "
                "AND s.status='Running' "
                "ORDER BY s.server_id")
        return self._db.execute_with_limit(query, 1000, retries=1)

    def get_servers(self):
        for servers in self._get_servers():

            prop = ['scalarizr.api_port', 'scalarizr.snmp_port', 'scalarizr.key']
            self._db.load_server_properties(servers, prop)

            for server in servers:
                if 'scalarizr.api_port' not in server:
                    server['scalarizr.api_port'] = 8010
                if 'scalarizr.snmp_port' not in server:
                    server['scalarizr.snmp_port'] = 161
                if 'scalarizr.key' not in server:
                    server['scalarizr.key'] = None

            self._db.load_vpc_settings(servers)

            out = []
            for server in servers:
                try:
                    server_id = server['server_id']
                    if server['os_type'] == 'linux':
                        exclude = ['snum']
                    elif server['os_type'] == 'windows':
                        exclude = ['la', 'io', 'snum']
                    else:
                        msg = "Wrong os type for server:'%s'" % server['server_id']
                        raise Exception(msg)
                    metrics = [m for m in CONFIG['metrics'] if m not in exclude]
                    server['metrics'] = metrics
                    out.append(server)
                except:
                    LOG.error(helper.exc_info())
                    continue
            yield out

    def __call__(self):
        srv_pool = multiprocessing.pool.ThreadPool(CONFIG['pool_size'])
        rrd_pool = multiprocessing.pool.ThreadPool(20)
        try:
            ra, fa, rs, fs = dict(), dict(), dict(), dict()
            for servers in self.get_servers():
                results = srv_pool.map(_process_server, servers)
                for result in results:
                    if result['data']:
                        file_dir = os.path.join(
                            CONFIG['rrd']['dir'],
                            helper.x1x2(result['farm_id']),
                            '%s' % result['farm_id'],
                            'INSTANCE_%s_%s' % (result['farm_roleid'], result['index'])
                        )
                        rrd_pool.apply_async(rrd.write, (file_dir, result['data'],))
                ra, fa, rs, fs = _average(results, ra=ra, fa=fa, rs=rs, fs=fs)
            for k, v in ra.iteritems():
                rrd_pool.apply_async(rrd.write, args=(k, v,))
            for k, v in fa.iteritems():
                rrd_pool.apply_async(rrd.write, args=(k, v,))
            if 'snum' in CONFIG['metrics']:
                for k, v in rs.iteritems():
                    rrd_pool.apply_async(rrd.write, args=(k, v,))
                for k, v in fs.iteritems():
                    rrd_pool.apply_async(rrd.write, args=(k, v,))
        except:
            LOG.error(helper.exc_info())
        finally:
            srv_pool.close()
            rrd_pool.close()
            srv_pool.join()
            rrd_pool.join()

class Plotter(object):

    class PlotterError(Exception):
        pass

    class BadRequestError(PlotterError):
        pass

    class FarmTerminatedError(PlotterError):
        pass

    class IOError(PlotterError):
        pass

    def __init__(self):
        self._db = dbmanager.ScalrDB(CONFIG['connections']['mysql'])
        self.plotters = {
            'cpu': rrd.plot_cpu,
            'la': rrd.plot_la,
            'mem': rrd.plot_mem,
            'net': rrd.plot_net,
            'snum': rrd.plot_snum,
        }

        import os
        import threading

        os_local = threading.local()
        for k, v in os.__dict__.iteritems():
            os_local.__setattr__(k, v)
        os = os_local

    @helper.thread()
    def _serve_forever(self):
        cherrypy.quickstart(self)

    def __call__(self):
        try:
            cherrypy.config.update({
                'engine.autoreload_on': False,
                'server.socket_host': CONFIG['connections']['plotter']['bind_address'],
                'server.socket_port': CONFIG['connections']['plotter']['port'],
                'server.thread_pool': CONFIG['connections']['plotter']['pool_size'],
		        'error_page.404': Plotter.error_page_404,
            })
            if CONFIG['connections']['plotter']['scheme'] == 'https':
                ssl_certificate = CONFIG['connections']['plotter']['ssl_certificate']
                if not os.path.isfile(ssl_certificate):
                    msg = 'ssl certficate {0} not found'.format(ssl_certificate)
                    raise Exception(msg)
                ssl_private_key = CONFIG['connections']['plotter']['ssl_private_key']
                if not os.path.isfile(ssl_private_key):
                    msg = 'ssl private key {0} not found'.format(ssl_private_key)
                    raise Exception(msg)
                ssl_certificate_chain = CONFIG['connections']['plotter']['ssl_certificate_chain']
                if ssl_certificate_chain and not os.path.isfile(ssl_certificate_chain):
                    msg = 'ssl private key {0} not found'.format(ssl_certificate_chain)
                    raise Exception(msg)
                cherrypy.config.update({
	                'server.ssl_module': 'pyopenssl',
	                'server.ssl_certificate': ssl_certificate,
	                'server.ssl_private_key': ssl_private_key,
                    'server.ssl_certificate_chain': ssl_certificate_chain,
                })
            t = self._serve_forever()
            time.sleep(1)
            change_permissions()
            t.join()
        except:
            LOG.critical(helper.exc_info())

    def _get_farm(self, farm_id):
        if not farm_id:
            return tuple()
        query = "SELECT `status`,`hash` FROM farms WHERE id=%s" % farm_id
        result = [_ for _ in self._db.execute(query)]
        return result[0] if result else tuple()

    def _get_tz(self, farm_id):
        query = (
                "SELECT `value` "
                "FROM farm_settings "
                "WHERE name='timezone' AND farmid={farm_id}"
        ).format(farm_id=farm_id)
        result = [_ for _ in self._db.execute(query)]
        if not result or result[0]['value'] == '0':
            return None
        else:
            return result[0]['value']

    def _check_request(self, kwds):
        try:
            assert 'hash' in kwds, "Missing required parameter 'hash'"
            assert 'farmId' in kwds, "Missing required parameter 'farmId'"
            int(kwds['farmId'])
            assert 'metrics' in kwds, "Missing required parameter 'metrics'"
            assert 'period' in kwds, "Missing required parameter 'period'"
            if 'index' in kwds:
                assert 'farmRoleId' in kwds, "Missing required parameter 'farmRoleId'"
                int(kwds['farmRoleId'])
                int(kwds['index'])
            elif 'farmRoleId' in kwds:
                int(kwds['farmRoleId'])
            assert kwds['period'] in ['daily', 'weekly', 'monthly', 'yearly'], \
                    "Unsupported period '%s'" % kwds['period']
        except (AssertionError, ValueError):
            LOG.warning(helper.exc_info())
            raise Plotter.BadRequestError('Bad request')

    def _get_relative_dir(self, kwds):
        if 'index' in kwds:
            tmp = 'INSTANCE_%s_%s' % (kwds['farmRoleId'], kwds['index'])
            return os.path.join('%s' % kwds['farmId'], tmp)
        elif 'farmRoleId' in kwds:
            return os.path.join('%s' % kwds['farmId'], 'FR_%s' % kwds['farmRoleId'])
        else:
            return os.path.join('%s' % kwds['farmId'], 'FARM')

    def _get_rrd_dir(self, kwds, relative_dir):
        base_rrd_dir = os.path.join(CONFIG['rrd']['dir'], helper.x1x2(kwds['farmId']))
        rrd_dir = os.path.join(base_rrd_dir, relative_dir)
        return rrd_dir

    def _get_rrd_files(self, kwds, rrd_dir, metric):
        if metric == 'io':
            m = 'IO'
        elif metric == 'snum':
            m = 'SERVERS'
        else:
            m = '%sSNMP' % metric.upper()
        try:
            rrd_files = [os.path.join(rrd_dir, m, f) for f in
                    os.walk(os.path.join(rrd_dir, m)).next()[2]]
        except StopIteration:
            rrd_files = []
        for rrd_file in rrd_files:
            if not os.path.exists(rrd_file):
                raise IOError('No such file or directory: %s' % rrd_file)
        return rrd_files

    def _get_image_dir(self, relative_dir):
        img_dir = os.path.join(CONFIG['img']['dir'], relative_dir)
        if not os.path.exists(img_dir):
            try:
                os.makedirs(img_dir, 0755)
            except OSError as e:
                if e.errno != 17:  # File exists
                    raise
        return img_dir

    def _get_url_dir(self, relative_dir):
        if CONFIG['img']['port']:
            url_dir = '{scheme}://{host}:{port}/{path}'.format(
                scheme=CONFIG['img']['scheme'],
                host=CONFIG['img']['host'],
                port=CONFIG['img']['port'],
                path=os.path.join(CONFIG['img']['path'], relative_dir)
            )
        else:
            url_dir = '{scheme}://{host}/{path}'.format(
                scheme=CONFIG['img']['scheme'],
                host=CONFIG['img']['host'],
                path=os.path.join(CONFIG['img']['path'], relative_dir)
            )
        return url_dir

    def _plot_io(self, kwds, rrd_files, img_dir, url_dir, tz):
        url = dict()
        options = rrd.GRAPH_OPT[kwds['period']]
        for rrd_file in rrd_files:
            dev = os.path.basename(rrd_file)[:-4]
            url[dev] = dict()
            img_file = os.path.join(
                img_dir,
                'io_bits_%s_%s.png' % (dev, kwds['period'])
            )
            url[dev]['bits_per_sec'] = os.path.join(
                url_dir,
                'io_bits_%s_%s.png' % (dev, kwds['period'])
            )
            rrd.plot_io_bits(str(img_file), str(rrd_file), options, tz=tz)
            img_file = os.path.join(
                img_dir,
                'io_ops_%s_%s.png' % (dev, kwds['period'])
            )
            url[dev]['operations_per_sec'] = os.path.join(
                url_dir,
                'io_ops_%s_%s.png' % (dev, kwds['period'])
            )
            rrd.plot_io_ops(str(img_file), str(rrd_file), options, tz=tz)
        return url

    def _plot(self, kwds, rrd_files, img_dir, url_dir, tz, metric):
        if metric == 'io':
            url = self._plot_io(kwds, rrd_files, img_dir, url_dir, tz)
        else:
            rrd_file = rrd_files[0]
            img_file = os.path.join(img_dir, '%s_%s.png' % (metric, kwds['period']))
            if not os.path.exists(img_file) or os.path.getmtime(img_file) + 60 < time.time():
                options = rrd.GRAPH_OPT[kwds['period']]
                self.plotters[metric](str(img_file), str(rrd_file), options)
            url = os.path.join(url_dir, '%s_%s.png' % (metric, kwds['period']))
        return url

    @staticmethod
    def error_page_404(*args, **kwds):
        return "We're terribly sorry, the page you're looking for doesn't seem to exist!"

    @cherrypy.expose
    @cherrypy.tools.json_in()
    @cherrypy.tools.json_out()
    def load_statistics(self, **kwds):
        result = {'success': True}
        try:
            self._check_request(kwds)

            farm = self._get_farm(kwds['farmId'])
            if not farm or farm['status'] != 1:
                msg = 'Statistics are not available for terminated farms'
                raise Plotter.FarmTerminatedError(msg)
            if farm['hash'] != kwds['hash']:
                msg = 'Farm hash mismatch'
                raise Plotter.PlotterError(msg)

            tz = self._get_tz(kwds['farmId'])
            if tz:
                os.environ['TZ'] = tz

            metrics = kwds['metrics'].strip().split(',')
            for metric in metrics:
                try:
                    metric = metric.strip()
                    if metric not in ['cpu', 'la', 'mem', 'net', 'io', 'snum']:
                        msg = "Unsupported metric '%s'" % metric
                        raise Plotter.PlotterError(msg)
                    relative_dir = self._get_relative_dir(kwds)
                    rrd_dir = self._get_rrd_dir(kwds, relative_dir)
                    img_dir = self._get_image_dir(relative_dir)
                    url_dir = self._get_url_dir(relative_dir)
                    rrd_files = self._get_rrd_files(kwds, rrd_dir, metric)
                    if not rrd_files:
                        LOG.warning("Coudn't find rrd file(s) for request:%s, metric:%s" % (kwds, metric))
                        raise Plotter.IOError('Statistics are not available')
                    url = self._plot(kwds, rrd_files, img_dir, url_dir, tz, metric)
                    result.setdefault('metric', dict())
                    result['metric'][metric] = {'success': True, 'img': url}
                except Plotter.PlotterError as e:
                    result.setdefault('metric', dict())
                    result['metric'][metric] = {'success': False, 'msg': str(e)}
        except Plotter.PlotterError as e:
            result['success'] = False
            result['msg'] = str(e)
        except:
            result['success'] = False
            result['msg'] = 'Internal error. Unable to load statistics'
            LOG.error(helper.exc_info())
        cherrypy.response.headers['Access-Control-Allow-Origin'] = '*'
        cherrypy.response.headers['Access-Control-Max-Age'] = 300
        if 'Access-Control-Request-Headers' in cherrypy.request.headers:
            cherrypy.response.headers['Access-Control-Allow-Headers'] = \
                    cherrypy.request.headers['Access-Control-Request-Headers']
        return result


class LoadStatistics(cron.Cron):

    def __init__(self):
        super(LoadStatistics, self).__init__(CONFIG['pid_file'])

    def _run(self):
        plotter_ps = None
        if CONFIG['plotter']:
            plotter = Plotter()
            plotter_ps = multiprocessing.Process(target=plotter, args=())
            plotter_ps.start()
            time.sleep(2)
            if not plotter_ps.is_alive():
                LOG.critical('Failed to start CherryPy web server')
                sys.exit(1)

        change_permissions()

        if CONFIG['poller']:
            poller = Poller()
            while True:
                try:
                    start_time = time.time()
                    LOG.info('Start iteration')
                    poller_ps = multiprocessing.Process(target=poller, args=())
                    poller_ps.start()
                    poller_ps.join(CONFIG['interval'] * 2)
                    if poller_ps.is_alive():
                        LOG.error('Iteration timeout. Terminating...')
                        try:
                            poller_ps.terminate()
                        except:
                            msg = 'Unable to terminate, reason: %s'
                            LOG.error(msg % helper.exc_info())
                    LOG.info('Working time: %.2f' % (time.time() - start_time))
                    sleep_time = start_time + CONFIG['interval'] - time.time() - 0.1
                    if sleep_time > 0:
                        time.sleep(sleep_time)
                except KeyboardInterrupt:
                    raise
                except:
                    msg = 'Iteration failed, reason: %s'
                    LOG.error(msg % helper.exc_info())
        if plotter_ps:
            plotter_ps.join()


def configure(args=None):
    global CONFIG, SCALR_CONFIG
    helper.update_config(
            SCALR_CONFIG.get('connections', {}).get('mysql', {}), CONFIG['connections']['mysql'])
    helper.update_config(SCALR_CONFIG.get('load_statistics', {}), CONFIG)
    inst_conn_timeout = SCALR_CONFIG.get('system', {}).get('instances_connection_timeout', None)
    if inst_conn_timeout:
        CONFIG['instances_connection_timeout'] = inst_conn_timeout
    helper.update_config(config_to=CONFIG, args=args)
    helper.validate_config(CONFIG)
    helper.configure_log(
        log_level=CONFIG['verbosity'],
        log_file=CONFIG['log_file'],
        log_size=1024 * 1000)
    socket.setdefaulttimeout(CONFIG['instances_connection_timeout'])
    if CONFIG['connections']['plotter']['scheme'] == 'https':
        try:
            import OpenSSL
        except ImportError:
            msg = "Configure failed, https is not supported, reason: PyOpenSSL is not installed"
            raise Exception(msg)
    if not os.path.exists(os.path.dirname(CONFIG['rrd']['rrdcached_sock_path'])):
        os.makedirs(os.path.dirname(CONFIG['rrd']['rrdcached_sock_path']), 0755)
    rrd.RRDCACHED_SOCK_FILE = CONFIG['rrd']['rrdcached_sock_path']


def change_permissions():
    if CONFIG['group']:
        helper.chown(CONFIG['log_file'], os.getuid(), CONFIG['group'])
        helper.chown(CONFIG['pid_file'], os.getuid(), CONFIG['group'])
        helper.set_gid(CONFIG['group'])
    if CONFIG['user']:
        helper.chown(CONFIG['log_file'], CONFIG['user'], os.getgid())
        helper.chown(CONFIG['pid_file'], CONFIG['user'], os.getgid())
        helper.set_uid(CONFIG['user'])


def main():
    parser = argparse.ArgumentParser()
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--start', action='store_true', default=False,
            help='start program')
    group.add_argument('--stop', action='store_true', default=False,
            help='stop program')
    parser.add_argument('--poller', action='store_true', default=None,
            help='poller mode')
    parser.add_argument('--plotter', action='store_true', default=None,
            help='plotter mode')
    parser.add_argument('--no-daemon', action='store_true', default=None,
            help="run in no daemon mode")
    parser.add_argument('--with-snmp', action='store_true', default=None,
            help="use snmp")
    parser.add_argument('-i', '--interval', type=int, default=None,
            help="execution interval in seconds. Default is 120")
    parser.add_argument('-p', '--pid-file', default=None,
            help="pid file")
    parser.add_argument('-l', '--log-file', default=None,
            help="log file")
    parser.add_argument('-m', '--metrics', default=None,
            choices=['cpu', 'la', 'mem', 'net', 'io', 'snum'], action='append',
            help="metrics type for processing")
    parser.add_argument('-c', '--config-file', default='./config.yml',
            help='config file')
    parser.add_argument('-v', '--verbosity', default=None, action='count',
            help='increase output verbosity [0:4]. Default is 1 - Error')
    parser.add_argument('--version', action='version', version='Version %s' % __version__)
    args = parser.parse_args()
    try:
        global SCALR_CONFIG
        SCALR_CONFIG = yaml.safe_load(open(args.config_file))['scalr']
        configure(args)
    except:
        if args.verbosity > 3:
            raise
        else:
            sys.stderr.write('%s\n' % helper.exc_info())
        sys.exit(1)
    try:
        socket.setdefaulttimeout(CONFIG['instances_connection_timeout'])
        app = LoadStatistics()
        if args.start:
            if helper.check_pid(CONFIG['pid_file']):
                msg = "Application with pid file '%s' already running. Exit" % CONFIG['pid_file']
                LOG.info(msg)
                sys.exit(0)
            if not args.no_daemon:
                helper.daemonize()
            app.start()
        elif args.stop:
            app.stop()
        else:
            print 'Usage %s -h' % sys.argv[0]
    except KeyboardInterrupt:
        sys.stdout.write('Keyboard interrupt\n')
        helper.kill_child(multiprocessing.current_process().pid)
        sys.exit(0)
    except SystemExit:
        pass
    except:
        LOG.exception('Something happened and I think I died')
        sys.exit(1)


if __name__ == '__main__':
    main()
