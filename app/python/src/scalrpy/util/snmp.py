import netsnmp
import logging


LOG = logging.getLogger('ScalrPy')

OIDS = {
    'cpu': {
        'user': '.1.3.6.1.4.1.2021.11.50.0',
        'nice': '.1.3.6.1.4.1.2021.11.51.0',
        'system': '.1.3.6.1.4.1.2021.11.52.0',
        'idle': '.1.3.6.1.4.1.2021.11.53.0',
    },
    'la': {
        'la1': '.1.3.6.1.4.1.2021.10.1.3.1',
        'la5': '.1.3.6.1.4.1.2021.10.1.3.2',
        'la15': '.1.3.6.1.4.1.2021.10.1.3.3',
    },
    'mem': {
        'swap': '.1.3.6.1.4.1.2021.4.3.0',
        'swapavail': '.1.3.6.1.4.1.2021.4.4.0',
        'total': '.1.3.6.1.4.1.2021.4.5.0',
        'avail': '.1.3.6.1.4.1.2021.4.6.0',
        'free': '.1.3.6.1.4.1.2021.4.11.0',
        'shared': '.1.3.6.1.4.1.2021.4.13.0',
        'buffer': '.1.3.6.1.4.1.2021.4.14.0',
        'cached': '.1.3.6.1.4.1.2021.4.15.0',
    },
    'net': {
        'in': '.1.3.6.1.2.1.2.2.1.10.2',
        'out': '.1.3.6.1.2.1.2.2.1.16.2',
    },
}


def get_metrics(host, port, community, metrics):
    assert host, 'host'
    assert port, 'port'
    assert community, 'community'
    assert metrics, 'metrics'

    oids = []
    for k, v in OIDS.iteritems():
        if k in metrics:
            for kk, vv in v.iteritems():
                oids.append(vv)
    if not oids:
        return dict()
    session = netsnmp.Session(
            DestHost='%s:%s' % (host, port),
            Version=1,
            Community=community,
            Timeout=2000000)
    Vars = netsnmp.VarList(*oids)
    snmp_data = dict((o, v) for o, v in zip(oids, session.get(Vars)))
    data = dict()
    for metric_name in metrics:
        if metric_name not in OIDS:
            continue
        for metric in OIDS[metric_name].keys():
            try:
                value = float(snmp_data[OIDS[metric_name][metric]])
            except:
                value = None
            data.setdefault(metric_name, {}).setdefault(metric, value)
    return data
