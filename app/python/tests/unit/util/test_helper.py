from scalrpy.util import helper


def test_get_proxy_settings():
    scalr_config = {
        'connections': {
            'proxy': {
                'host': 'host',
                'port': 10,
                'type': 0,
                'user': 'user',
                'pass': 'pass',
            },
        }
    }
    cases = [
        {
            'section': ('ec2', 'aws'),
            'use_proxy': (False, None, 'no'),
            'use_on': ('both', 'scalr', 'instances'),
            'result': {}
        },
        {
            'section': ('ec2', 'aws'),
            'use_proxy': (True, 'yes'),
            'use_on': ('instances',),
            'result': {}
        },
        {
            'section': ('nonexistent',),
            'use_proxy': (True, 'yes'),
            'use_on': ('both', 'scalr'),
            'result': {}
        },
        {
            'section': ('ec2', 'aws'),
            'use_proxy': (True, 'yes'),
            'use_on': ('both', 'scalr'),
            'result': {
                'host': scalr_config['connections']['proxy']['host'],
                'port': scalr_config['connections']['proxy']['port'],
                'type': scalr_config['connections']['proxy']['type'],
                'user': scalr_config['connections']['proxy']['user'],
                'pass': scalr_config['connections']['proxy']['pass'],
                'scheme': 'http',
                'url': 'http://user:pass@host:10'
            }
        },
    ]
    for case in cases:
        for section in case['section']:
            if section == 'ec2':
                section = 'aws'
            config = scalr_config.copy()
            for use_proxy in case['use_proxy']:
                if section != 'nonexistent':
                    config[section] = {}
                    config[section]['use_proxy'] = use_proxy
                for use_on in case['use_on']:
                    config['connections']['proxy']['use_on'] = use_on
                    result = helper.get_proxy_settings(config, section)
                    if result:
                        case['result']['use_on'] = use_on
                    assert case['result'] == result, result


def test_get_proxy_settings_default():
    scalr_config = {
        'connections': {
            'proxy': {
                'host': 'host',
                'type': 0,
            },
        }
    }
    cases = [
        {
            'section': ('ec2', 'aws'),
            'use_proxy': (True, 'yes'),
            'use_on': ('both', 'scalr'),
            'result': {
                'host': scalr_config['connections']['proxy']['host'],
                'user': None,
                'pass': None,
                'port': 3128,
                'type': scalr_config['connections']['proxy']['type'],
                'scheme': 'http',
                'url': 'http://host:3128'
            }
        },
    ]
    for case in cases:
        for section in case['section']:
            if section == 'ec2':
                section = 'aws'
            config = scalr_config.copy()
            for use_proxy in case['use_proxy']:
                if section != 'nonexistent':
                    config[section] = {}
                    config[section]['use_proxy'] = use_proxy
                for use_on in case['use_on']:
                    config['connections']['proxy']['use_on'] = use_on
                    result = helper.get_proxy_settings(config, section)
                    if result:
                        case['result']['use_on'] = use_on
                    assert case['result'] == result, result


def test_get_proxy_settings_webhooks():
    scalr_config = {
        'connections': {
            'proxy': {
                'host': 'host',
                'port': 10,
                'type': 0,
                'user': 'user',
                'pass': 'pass',
            },
        },
        'system': {
            'webhooks': {'use_proxy': None}
        }
    }
    cases = [
        {
            'section': ('system.webhooks',),
            'use_proxy': (True, 'yes'),
            'use_on': ('both', 'scalr'),
            'result': {
                'host': scalr_config['connections']['proxy']['host'],
                'port': scalr_config['connections']['proxy']['port'],
                'type': scalr_config['connections']['proxy']['type'],
                'user': scalr_config['connections']['proxy']['user'],
                'pass': scalr_config['connections']['proxy']['pass'],
                'scheme': 'http',
                'url': 'http://user:pass@host:10'
            }
        },
    ]
    for case in cases:
        for section in case['section']:
            config = scalr_config.copy()
            for use_proxy in case['use_proxy']:
                config['system']['webhooks']['use_proxy'] = use_proxy
                for use_on in case['use_on']:
                    config['connections']['proxy']['use_on'] = use_on
                    result = helper.get_proxy_settings(config, section)
                    if result:
                        case['result']['use_on'] = use_on
                    assert case['result'] == result, result
