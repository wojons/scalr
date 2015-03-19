import re
import socket
import urllib2

from scalrpy.util import rpc
from scalrpy.util import helper


from scalrpy import LOG


def get_cpu_stat(hsp, api_type='linux', timeout=5):
    cpu = hsp.sysinfo.cpu_stat(timeout=timeout)
    for k, v in cpu.iteritems():
        try:
            cpu[k] = float(v)
        except ValueError:
            cpu[k] = None
    return cpu


def get_la_stat(hsp, api_type='linux', timeout=5):
    assert_msg = "Unsupported API type '%s' for LA stat" % api_type
    assert api_type == 'linux', assert_msg
    la = hsp.sysinfo.load_average(timeout=timeout)
    return {
        'la1': float(la[0]),
        'la5': float(la[1]),
        'la15': float(la[2]),
    }


class APIError(Exception):
    pass


def get_mem_info(hsp, api_type='linux', timeout=5):
    mem = hsp.sysinfo.mem_info(timeout=timeout)
    if api_type == 'linux':
        ret = {
            'swap': float(mem['total_swap']),
            'swapavail': float(mem['avail_swap']),
            'total': float(mem['total_real']),
            'avail': None,  # FIXME
            'free': float(mem['total_free']),
            'shared': float(mem['shared']),
            'buffer': float(mem['buffer']),
            'cached': float(mem['cached']),
        }
    elif api_type == 'windows':
        ret = {
            'swap': float(mem['total_swap']),
            'swapavail': float(mem['avail_swap']),
            'total': float(mem['total_real']),
            'avail': None,  # FIXME
            'free': float(mem['total_free']),
        }
    else:
        raise APIError("Unsupported API type '%s' for MEM info" % api_type)
    return ret


def get_net_stat(hsp, api_type='linux', timeout=5):
    net = hsp.sysinfo.net_stats(timeout=timeout)
    if api_type == 'linux':
        ret = {
            'in': float(net['eth0']['receive']['bytes']),
            'out': float(net['eth0']['transmit']['bytes']),
        }
    elif api_type == 'windows':
        for key in net:
            if re.match(r'^.*Ethernet Adapter.*$', key) \
                    or re.match(r'^.*AWS PV Network Device.*$', key):
                ret = {
                    'in': float(net[key]['receive']['bytes']),
                    'out': float(net[key]['transmit']['bytes']),
                }
                break
        else:
            msg = (
                "Can't find ['^.* Ethernet Adapter.*$', '^.*AWS PV Network Device.*$'] "
                "pattern in api response for endpoint: {0}, available: {1}, use {2}"
            ).format(hsp.endpoint, net.keys(), net.keys()[0])
            LOG.warning(msg)
            first_key = net.keys()[0]
            ret = {
                'in': float(net[first_key]['receive']['bytes']),
                'out': float(net[first_key]['transmit']['bytes']),
            }
    else:
        raise APIError("Unsupported API type '%s' for NET stat" % api_type)
    return ret


def get_io_stat(hsp, api_type='linux', timeout=5):
    assert_msg = "Unsupported API type '%s' for IO stat" % api_type
    assert api_type == 'linux', assert_msg
    io = hsp.sysinfo.disk_stats(timeout=timeout)
    ret = dict(
        (
            str(dev),
            {
                'read': float(io[dev]['read']['num']),
                'write': float(io[dev]['write']['num']),
                'rbyte': float(io[dev]['read']['bytes']),
                'wbyte': float(io[dev]['write']['bytes']),
            }
        ) for dev in io if
        re.match(r'^sd[a-z]{1}[0-9]{1,2}$', dev) or
        re.match(r'^hd[a-z]{1}[0-9]{1,2}$', dev) or
        re.match(r'^xvd[a-z]{1}[0-9]{1,2}$', dev)
    )
    return ret


def get_metrics(host, port, key, api_type, metrics, headers=None, timeout=5):
    assert host, 'host'
    assert port, 'port'
    assert key, 'key'
    assert api_type, 'api_type'
    assert metrics, 'metrics'

    data = dict()
    endpoint = 'http://%s:%s' % (host, port)
    security = rpc.Security(key)
    hsp = rpc.HttpServiceProxy(endpoint, security=security, headers=headers)
    getters = {
        'cpu': get_cpu_stat,
        'la': get_la_stat,
        'mem': get_mem_info,
        'net': get_net_stat,
        'io': get_io_stat,
    }
    for metric in metrics:
        try:
            data.update({metric: getters[metric](hsp, api_type, timeout=timeout)})
        except (urllib2.URLError, urllib2.HTTPError, socket.timeout):
            msg = "Endpoint: {endpoint}, headers: {headers}, metric: '{metric}', reason: {err}"
            msg = msg.format(
                endpoint=endpoint, headers=headers, metric=metric, err=helper.exc_info())
            raise Exception(msg)
        except:
            msg = "Endpoint: {endpoint}, headers: {headers}, metric '{metric}' failed, reason: {er}"
            msg = msg.format(
                endpoint=endpoint, headers=headers, metric=metric, err=helper.exc_info())
            LOG.warning(msg)
            continue
    return data
