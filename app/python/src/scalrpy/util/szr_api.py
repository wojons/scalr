import re

from scalrpy.util import rpc


def get_cpu_stat(hsp, api_type='linux', timeout=5):
    cpu = hsp.sysinfo.cpu_stat(timeout=timeout)
    for k, v in cpu.iteritems():
        cpu[k] = float(v)
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
        raise Exception("Unsupported API type '%s' for MEM info" % api_type)
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
            if re.match(r'^.* Ethernet Adapter _0$', key):
                ret = {
                    'in': float(net[key]['receive']['bytes']),
                    'out': float(net[key]['transmit']['bytes']),
                    }
                break
        else:
            msg = "Can't find '* Ethernet Adapter _0' pattern in api response"
            raise Exception(msg)
    else:
        raise Exception("Unsupported API type '%s' for NET stat" % api_type)
    return ret


def get_io_stat(hsp, api_type='linux', timeout=5):
    assert_msg = "Unsupported API type '%s' for IO stat" % api_type
    assert api_type == 'linux', assert_msg
    io = hsp.sysinfo.disk_stats(timeout=timeout)
    ret = dict((
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
        data.update({metric:getters[metric](hsp, api_type, timeout=timeout)})
    return data
