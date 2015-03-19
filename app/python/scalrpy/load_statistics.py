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

cwd = os.path.dirname(os.path.abspath(__file__))
scalrpy_dir = os.path.join(cwd, '..')
sys.path.insert(0, scalrpy_dir)

import time
import socket
import thread
import cherrypy
import multiprocessing.pool

from scalrpy.util import rrd
from scalrpy.util import helper
from scalrpy.util import szr_api
from scalrpy.util import dbmanager
from scalrpy.util import cryptotool
from scalrpy.util import exceptions
from scalrpy.util import application

from scalrpy import LOG


app = None


class Plotter(object):

    class PlotterError(Exception):
        pass

    class BadRequestError(PlotterError):
        pass

    class FarmTerminatedError(PlotterError):
        pass

    class IOError(PlotterError):
        pass

    def __init__(self, config):
        self.config = config
        self._db = dbmanager.ScalrDB(config['connections']['mysql'])
        self._plotters = {
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
        LOG.debug('Starting plotter')
        try:
            cherrypy.quickstart(self, '/', {'/': {}})
        except:
            LOG.error(helper.exc_info())
            thread.interrupt_main()

    def run(self):
        try:
            self.configure()

            t = self._serve_forever()
            while not t.is_alive():
                time.sleep(0.5)

            # wait before change permissions to allow cherrypy read certificates
            time.sleep(2)

            # change permissions
            if self.config['group']:
                helper.set_gid(self.config['group'])
            if self.config['user']:
                helper.set_uid(self.config['user'])

            LOG.info('Plotter started')
            t.join()
        except:
            LOG.exception(helper.exc_info())

    @helper.thread(daemon=True)
    def run_in_thread(self):
        self.run()

    @helper.process(daemon=True)
    def run_in_process(self):
        helper.set_proc_name('plotter')
        self.run()

    def configure(self):
        cnf = self.config['connections']['plotter']
        cherrypy.config.update({
            'engine.autoreload_on': False,
            'server.socket_host': cnf['bind_address'] or cnf['bind_host'],
            'server.socket_port': cnf['bind_port'] or cnf['port'],
            'server.thread_pool': cnf['pool_size'],
            'error_page.404': Plotter.error_page_404,
            'log.error_file': self.config['log_file'],
        })
        scheme = cnf['bind_scheme'] or cnf['scheme']
        if scheme == 'https':
            ssl_certificate = cnf['ssl_certificate']
            if not os.path.isfile(ssl_certificate):
                msg = 'ssl certficate {0} not found'.format(ssl_certificate)
                raise Plotter.IOError(msg)
            ssl_private_key = cnf['ssl_private_key']
            if not os.path.isfile(ssl_private_key):
                msg = 'ssl private key {0} not found'.format(ssl_private_key)
                raise Plotter.IOError(msg)
            ssl_certificate_chain = cnf['ssl_certificate_chain']
            if ssl_certificate_chain and not os.path.isfile(ssl_certificate_chain):
                msg = 'ssl certificate chain file {0} not found'.format(ssl_certificate_chain)
                raise Plotter.IOError(msg)
            cherrypy.config.update({
                'server.ssl_module': 'pyopenssl',
                'server.ssl_certificate': ssl_certificate,
                'server.ssl_private_key': ssl_private_key,
                'server.ssl_certificate_chain': ssl_certificate_chain,
            })

    def _get_farm(self, farm_id):
        if not farm_id:
            return tuple()
        query = "SELECT status, hash FROM farms WHERE id={0}".format(farm_id)
        result = [_ for _ in self._db.execute(query)]
        return result[0] if result else tuple()

    def _get_tz(self, farm_id):
        query = (
            "SELECT value "
            "FROM farm_settings "
            "WHERE name='timezone' AND farmid={0}"
        ).format(farm_id)
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

    def _get_rrd_dir(self, kwds):
        base_rrd_dir = os.path.join(self.config['rrd']['dir'], helper.x1x2(kwds['farmId']))
        relative_dir = self._get_relative_dir(kwds)
        rrd_dir = os.path.join(base_rrd_dir, relative_dir)
        return rrd_dir

    def _get_rrd_files(self, kwds, metric):
        rrd_dir = self._get_rrd_dir(kwds)
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

    def _get_image_dir(self, kwds):
        relative_dir = self._get_relative_dir(kwds)
        img_dir = os.path.join(self.config['img']['dir'], relative_dir)
        if not os.path.exists(img_dir):
            try:
                os.makedirs(img_dir, 0755)
            except OSError as e:
                if e.errno != 17:  # File exists
                    raise
        return img_dir

    def _get_url_dir(self, kwds):
        relative_dir = self._get_relative_dir(kwds)
        if self.config['img']['port']:
            url_dir = '{scheme}://{host}:{port}/{path}'.format(
                scheme=self.config['img']['scheme'],
                host=self.config['img']['host'],
                port=self.config['img']['port'],
                path=os.path.join(self.config['img']['path'], relative_dir)
            )
        else:
            url_dir = '{scheme}://{host}/{path}'.format(
                scheme=self.config['img']['scheme'],
                host=self.config['img']['host'],
                path=os.path.join(self.config['img']['path'], relative_dir)
            )
        return url_dir

    def _plot(self, kwds, tz, metric):
        img_dir = self._get_image_dir(kwds)
        url_dir = self._get_url_dir(kwds)
        rrd_files = self._get_rrd_files(kwds, metric)
        if not rrd_files:
            msg = "Coudn't find rrd file(s) for request: {0}, metric: {1}"
            msg = msg.format(kwds, metric)
            LOG.warning(msg)
            raise Plotter.IOError('Statistics are not available')
        if metric == 'io':
            url = dict()
            options = rrd.GRAPH_OPT[kwds['period']]
            for rrd_file in rrd_files:
                dev = os.path.basename(rrd_file)[:-4]
                url[dev] = dict()
                img_file = os.path.join(
                    img_dir,
                    'io_bits_%s_%s.png' % (dev, kwds['period']))
                url[dev]['bits_per_sec'] = os.path.join(
                    url_dir,
                    'io_bits_%s_%s.png' % (dev, kwds['period']))
                rrd.plot_io_bits(str(img_file), str(rrd_file), options, tz=tz)
                img_file = os.path.join(
                    img_dir,
                    'io_ops_%s_%s.png' % (dev, kwds['period']))
                url[dev]['operations_per_sec'] = os.path.join(
                    url_dir,
                    'io_ops_%s_%s.png' % (dev, kwds['period']))
                rrd.plot_io_ops(str(img_file), str(rrd_file), options, tz=tz)
        else:
            rrd_file = rrd_files[0]
            img_file = os.path.join(img_dir, '%s_%s.png' % (metric, kwds['period']))
            if not os.path.exists(img_file) or os.path.getmtime(img_file) + 60 < time.time():
                options = rrd.GRAPH_OPT[kwds['period']]
                self._plotters[metric](str(img_file), str(rrd_file), options)
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
                    url = self._plot(kwds, tz, metric)
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


class Average(object):

    def __init__(self):
        self._count = 0
        self._value = None

    def __iadd__(self, other):
        """
        >>> x = Average()
        >>> x += 1
        >>> assert float(x) == 1.0
        >>> x += 2
        >>> assert float(x) == 1.5
        >>> x += 3
        >>> assert float(x) == 2
        """
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

    def __init__(self, config, scalr_config):
        self.config = config
        self.scalr_config = scalr_config
        self._db = dbmanager.ScalrDB(self.config['connections']['mysql'], pool_size=1)

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

    def _get_rf_keys(self, result):
        r_key = os.path.join(
            self.config['rrd']['dir'],
            helper.x1x2(result['farm_id']),
            '%s' % result['farm_id'],
            'FR_%s' % result['farm_roleid']
        )
        f_key = os.path.join(
            self.config['rrd']['dir'],
            helper.x1x2(result['farm_id']),
            '%s' % result['farm_id'],
            'FARM'
        )
        return r_key, f_key

    def _average(self, results, ra=None, fa=None, rs=None, fs=None):
        ra = ra or dict()
        fa = fa or dict()
        rs = rs or dict()
        fs = fs or dict()
        for result in results:
            try:
                r_key, f_key = self._get_rf_keys(result)
                if 'snum' in self.config['metrics']:
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

    def _get_metrics_api(self, server):
        assert_msg = "Server: '%s' doesn't have a scalarizr key" % server['server_id']
        assert server['scalarizr.key'], assert_msg

        headers = {'X-Server-Id': server['server_id']}

        instances_connection_policy = self.scalr_config.get(server['platform'], {}).get(
            'instances_connection_policy', self.scalr_config['instances_connection_policy'])
        ip, port, proxy_headers = helper.get_szr_api_conn_info(server, instances_connection_policy)
        headers.update(proxy_headers)
        key = cryptotool.decrypt_key(server['scalarizr.key'])
        api_type = server['os_type']
        metrics = server['metrics']
        timeout = self.config['instances_connection_timeout']
        return szr_api.get_metrics(
            ip, port, key, api_type, metrics, headers=headers, timeout=timeout)

    def _process_server(self, server):
        data = dict()
        try:
            data = self._get_metrics_api(server)
        except:
            msg = "Server: '%s' API failed: %s" % (server['server_id'], helper.exc_info())
            LOG.warning(msg)
        result = {
            'farm_id': server['farm_id'],
            'farm_roleid': server['farm_roleid'],
            'index': server['index'],
            'data': data,
        }
        return result

    def get_servers(self):
        for servers in self._get_servers():

            prop = ['scalarizr.api_port', 'scalarizr.key']
            self._db.load_server_properties(servers, prop)

            for server in servers:
                if 'scalarizr.api_port' not in server:
                    server['scalarizr.api_port'] = 8010
                if 'scalarizr.key' not in server:
                    server['scalarizr.key'] = None

            self._db.load_vpc_settings(servers)

            out = []
            for server in servers:
                try:
                    if server['os_type'] == 'linux':
                        exclude = ['snum']
                    elif server['os_type'] == 'windows':
                        exclude = ['la', 'io', 'snum']
                    else:
                        msg = "Wrong os type for server: '%s'" % server['server_id']
                        raise Exception(msg)
                    metrics = [m for m in self.config['metrics'] if m not in exclude]
                    server['metrics'] = metrics
                    out.append(server)
                except:
                    LOG.error(helper.exc_info())
                    continue
            yield out

    def run(self):
        srv_pool = multiprocessing.pool.ThreadPool(self.config['pool_size'])
        rrd_pool = multiprocessing.pool.ThreadPool(10)
        try:
            rrd_sock = self.config['rrd']['rrdcached_sock_path']
            ra, fa, rs, fs = dict(), dict(), dict(), dict()
            for servers in self.get_servers():
                results = srv_pool.map(self._process_server, servers)
                for result in results:
                    if result['data']:
                        file_dir = os.path.join(
                            self.config['rrd']['dir'],
                            helper.x1x2(result['farm_id']),
                            '%s' % result['farm_id'],
                            'INSTANCE_%s_%s' % (result['farm_roleid'], result['index']))
                        rrd_pool.apply_async(
                            rrd.write,
                            args=(file_dir, result['data'],),
                            kwds={'sock_path': rrd_sock})
                ra, fa, rs, fs = self._average(results, ra=ra, fa=fa, rs=rs, fs=fs)
            for k, v in ra.iteritems():
                rrd_pool.apply_async(rrd.write, args=(k, v,), kwds={'sock_path': rrd_sock})
            for k, v in fa.iteritems():
                rrd_pool.apply_async(rrd.write, args=(k, v,), kwds={'sock_path': rrd_sock})
            if 'snum' in self.config['metrics']:
                for k, v in rs.iteritems():
                    rrd_pool.apply_async(rrd.write, args=(k, v,), kwds={'sock_path': rrd_sock})
                for k, v in fs.iteritems():
                    rrd_pool.apply_async(rrd.write, args=(k, v,), kwds={'sock_path': rrd_sock})
        except:
            LOG.error(helper.exc_info())
        finally:
            srv_pool.close()
            rrd_pool.close()
            srv_pool.join()
            rrd_pool.join()

    @helper.thread(daemon=True)
    def run_in_thread(self):
        self.run()

    @helper.process(daemon=True)
    def run_in_process(self):
        helper.set_proc_name('poller')
        self.run()


class LoadStatistics(application.ScalrApplication):

    def __init__(self, argv=None):
        self.description = "Scalr load statistics application"
        options = (
            """  --poller                          start poller process\n"""
            """  --plotter                         start plotter process\n""")
        self.add_options(options)

        super(LoadStatistics, self).__init__(argv=argv)

        self.config.update({
            'pool_size': 100,
            'metrics': ['cpu', 'la', 'mem', 'net', 'io', 'snum'],
            'interval': 120,
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
        })

        self.config['connections'].update({
            'plotter': {
                'bind_scheme': False,
                'scheme': 'http',
                'bind_address': False,  # deprecated, for compatibility only
                'bind_host': '0.0.0.0',
                'bind_port': False,
                'port': 8080,
                'pool_size': 100,
                'ssl_certificate': False,
                'ssl_private_key': False,
                'ssl_certificate_chain': False,
            },
        })

    def validate_config(self):
        application.ScalrApplication.validate_config(self)

        assert_msg = 'Unsupported metric: %s'
        for metric in self.config['metrics']:
            assert metric in ['cpu', 'la', 'mem', 'net', 'io', 'snum'], assert_msg % metric

        plt_cnf = self.config['connections']['plotter']
        plt_scheme = plt_cnf['bind_scheme'] or plt_cnf['scheme']
        assert plt_scheme

        if plt_scheme == 'https':
            def _assert(option):
                assert plt_cnf[option] and isinstance(plt_cnf[option], basestring), (
                    "Wrong config option connections:plotter:{0}, "
                    "must be defined and has <type 'str'> not {1}").format(
                    option, plt_cnf[option])

            for opt in ['ssl_certificate', 'ssl_private_key']:
                _assert(opt)

            if plt_cnf['ssl_certificate_chain']:
                _assert('ssl_certificate_chain')

    def configure(self):
        helper.update_config(
            self.scalr_config.get('load_statistics', {}), self.config)
        helper.validate_config(self.config)
        socket.setdefaulttimeout(self.config['instances_connection_timeout'])

    def __call__(self):
        poller_ps, plotter_ps = None, None

        if self.args['--plotter']:
            plotter = Plotter(self.config)
            plotter_ps = plotter.run_in_process()
            time.sleep(5)
            if not plotter_ps.is_alive():
                LOG.critical('Failed to start CherryPy web server')
                sys.exit(1)

        self.change_permissions()

        if self.args['--poller']:

            poller = Poller(self.config, self.scalr_config)
            while True:
                start_time = time.time()
                try:
                    LOG.info('Start poller iteration')

                    rrdcached_sock_file = self.config['rrd']['rrdcached_sock_path']
                    if not os.path.exists(rrdcached_sock_file):
                        raise Exception('rrdcached process is not running')

                    poller_ps = poller.run_in_process()
                    poller_ps.join(self.config['interval'] * 2)
                    if poller_ps.is_alive():
                        LOG.error('Poller iteration timeout. Terminating')
                        try:
                            poller_ps.terminate()
                        except:
                            msg = 'Unable to terminate, reason: {error}'.format(
                                error=helper.exc_info())
                            raise Exception(msg)
                    LOG.info('Poller iteration time: %.2f' % (time.time() - start_time))
                except KeyboardInterrupt:
                    raise
                except:
                    msg = 'Poller iteration failed, reason: {error}'.format(
                        error=helper.exc_info())
                    LOG.error(msg)
                finally:
                    sleep_time = start_time + self.config['interval'] - time.time() - 0.1
                    if sleep_time > 0:
                        time.sleep(sleep_time)

        if plotter_ps:
            plotter_ps.join()


def main():
    global app
    app = LoadStatistics()
    try:
        app.load_config()
        app.configure()
        app.run()
    except exceptions.AlreadyRunningError:
        LOG.info(helper.exc_info(where=False))
    except (SystemExit, KeyboardInterrupt):
        pass
    except:
        LOG.exception('Oops')


if __name__ == '__main__':
    main()
