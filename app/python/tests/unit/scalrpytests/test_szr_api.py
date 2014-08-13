import mock
import unittest

from scalrpy.util import szr_api


def test_get_metrics():
    host = 'localhost'
    port = '8010'
    key = 'YaOtBabuhkiYhelYaOtDeduhkiYhel'
    
    with mock.patch('scalrpy.util.rpc.HttpServiceProxy') as HttpServiceProxy:
        hsp = HttpServiceProxy.return_value
        hsp.sysinfo.cpu_stat.return_value = {'user':0, 'nice':0, 'system':0, 'idle':0}
        hsp.sysinfo.mem_info.return_value = {
                'total_swap':0,
                'avail_swap':0,
                'total_real':0,
                'total_free':0,
                'shared':0,
                'buffer':0,
                'cached':0}
        hsp.sysinfo.net_stats.return_value = {
                'eth0':{'receive':{'bytes':0}, 'transmit':{'bytes':0}}}
        hsp.sysinfo.load_average.return_value = [0.0, 0.0, 0.0]
        hsp.sysinfo.disk_stats.return_value = {
            'xvda1':{
                    'write':{
                            'num':0,
                            'bytes':0,
                            'sectors':0},
                    'read':{
                            'num':0,
                            'bytes':0,
                            'sectors':0}},
            'loop0':{
                    'write':{
                            'num':0,
                            'bytes':0,
                            'sectors':0},
                    'read':{
                            'num':0,
                            'bytes':0,
                            'sectors':0}}}
        data = szr_api.get_metrics(host, port, key, 'linux', ['cpu', 'la', 'mem', 'net'])
        assert data == {
                'cpu':{
                        'user':0.0,
                        'nice':0.0,
                        'system':0.0,
                        'idle':0.0,
                        },
                'la':{
                        'la1':0.0,
                        'la5':0.0,
                        'la15':0.0,
                        },
                'mem':{
                        'swap':0.0,
                        'swapavail':0.0,
                        'total':0.0,
                        'avail':None,
                        'free':0.0,
                        'shared':0.0,
                        'buffer':0.0,
                        'cached':0.0,
                        },
                'net':{
                        'in':0.0,
                        'out':0.0,
                        },
                }
        hsp.sysinfo.net_stats.return_value = {
                'xxx Ethernet Adapter _0':{'receive':{'bytes':0}, 'transmit':{'bytes':0}}}
        data = szr_api.get_metrics(host, port, key, 'windows', ['cpu', 'mem', 'net'])
        assert data == {
                'cpu':{
                        'user':0.0,
                        'nice':0.0,
                        'system':0.0,
                        'idle':0.0,
                        },
                'mem':{
                        'swap':0.0,
                        'swapavail':0.0,
                        'total':0.0,
                        'avail':None,
                        'free':0.0,
                        },
                'net':{
                        'in':0.0,
                        'out':0.0,
                        },
                }


if __name__ == "__main__":
	unittest.main()
