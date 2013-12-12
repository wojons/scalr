import mock
import unittest

from scalrpy.util import snmp


@mock.patch('scalrpy.util.snmp.netsnmp')
def test_get_metrics(netsnmp):
    host = 'localhost'
    port = 161
    community = 'YaOtBabushkiUhelYaOtDeduhkiUhel'

    with mock.patch('scalrpy.util.snmp.netsnmp.Session') as Session:
        instance = Session.return_value
        instance.get.return_value = (
            '0', '0', '0', '0', '0.0', '0.0', '0.0','0',
            '0', '0', '0', '0', '0', '0', '0', '0', '0')
        data = snmp.get_metrics(host, port, community, ['cpu', 'la', 'mem', 'net'])
        assert data == {
                'cpu':{
                        'user':0.0,
                        'nice':0.0,
                        'system':0.0,
                        'idle':0.0},
                'la':{
                        'la1':0.0,
                        'la5':0.0,
                        'la15':0.0},
                'mem':{
                        'swap':0.0,
                        'swapavail':0.0,
                        'total':0.0,
                        'avail':0.0,
                        'free':0.0,
                        'shared':0.0,
                        'buffer':0.0,
                        'cached':0.0},
                'net':{
                        'in':0.0,
                        'out':0.0}}


'''
def patch_snmp():
    snmp.get_metrics = mock.Mock(return_value = {
            'cpu':{
                    'user':1,
                    'nice':1,
                    'system':1,
                    'idle':1},
            'la':{
                    'la1':1.0,
                    'la5':1.0,
                    'la15':1.0},
            'mem':{
                    'swap':1,
                    'swapavail':1,
                    'total':1,
                    'avail':1,
                    'free':1,
                    'shared':1,
                    'buffer':1,
                    'cached':1},
            'net':{
                    'in':1,
                    'out':1}})
'''


if __name__ == "__main__":
	unittest.main()
