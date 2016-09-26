<?php

use Scalr\DependencyInjection\Container;
use Scalr\Exception\ScalrException;
use Scalr\LogCollector\ApiLoggerConfiguration;
use Scalr\LogCollector\AuditLoggerRetrieveConfigurationInterface;
use Scalr\System\Config\Yaml;
use Scalr\Model\Entity;
use Scalr\LogCollector\AuditLogger;
use Scalr\LogCollector\UserLogger;
use Scalr\LogCollector\ApiLogger;

/**
 * Dependency injection container configuration
 *
 * @author  Vitaliy Demidov    <vitaliy@scalr.com>
 * @since   01.11.2012
 */

$container = Scalr::getContainer();

/* @var $cont \Scalr\DependencyInjection\Container */
/* @var $analyticsContainer Scalr\DependencyInjection\AnalyticsContainer */

$container->setShared('config', function ($cont) {
    $loader = new \Scalr\System\Config\Loader();
    $cfg = $loader->load();
    return $cfg;
});

//Common dsn getter
$container->set('dsn.getter', function ($cont, array $arguments = null) {
    $my = $cont->config->get($arguments[0]);
    $dsn = sprintf(
        "%s://%s:%s@%s/%s",
        (isset($my['driver']) ? $my['driver'] : 'mysqli'),
        $my['user'], rawurlencode($my['pass']),
        (isset($my['host']) ? $my['host'] : 'localhost') . (isset($my['port']) ? ':' . $my['port'] : ''),
        $my['name']
    );
    return $dsn;
});

$container->setShared('adodb', function ($cont) {
    return new \Scalr\Db\ConnectionPool($cont->{'dsn.getter'}('scalr.connections.mysql'));
});

$container->setShared('dnsdb', function ($cont) {
    return new \Scalr\Db\ConnectionPool($cont->{'dsn.getter'}('scalr.dns.mysql'));
});

$container->setShared('cadb', function ($cont) {
    return new \Scalr\Db\ConnectionPool($cont->{'dsn.getter'}('scalr.analytics.connections.analytics'));
});

$container->session = function ($cont) {
    return Scalr_Session::getInstance();
};

$container->user = function ($cont) {
    return $cont->initialized('request') &&
           $cont->request->getUser() instanceof Scalr_Account_User ?
           $cont->request->getUser() : null;
};

$container->awsAccessKeyId = function ($cont) {
    return $cont->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY];
};

$container->awsSecretAccessKey = function ($cont) {
    return $cont->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_SECRET_KEY];
};

$container->awsAccountNumber = function ($cont) {
    return $cont->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID];
};

$container->awsCertificate = function ($cont) {
    return $cont->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_CERTIFICATE];
};

$container->awsPrivateKey = function ($cont) {
    return $cont->environment->keychain(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY];
};

$container->aws = function ($cont, array $arguments = null) {
    /* @var $env \Scalr_Environment */
    $params = [];

    $traitFetchEnvProperties = function ($env) use (&$params) {
        /* @var $env \Scalr_Environment|Entity\Account\Environment */
        $ccProps = $env->keychain(SERVER_PLATFORMS::EC2)->properties;

        $params['accessKeyId'] = $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY];
        $params['secretAccessKey'] = $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY];
        $params['certificate'] = $ccProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE];
        $params['privateKey'] = $ccProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY];
        $params['environment'] = $env;
    };

    if (!empty($arguments) && is_object($arguments[0])) {
        //Makes it possible to get aws instance by dbserver object
        if ($arguments[0] instanceof \DBServer) {
            $env = $arguments[0]->GetEnvironmentObject();
            $params['region'] = $arguments[0]->GetProperty(EC2_SERVER_PROPERTIES::REGION);
        } elseif ($arguments[0] instanceof \DBFarmRole) {
            $env = $arguments[0]->GetFarmObject()->GetEnvironmentObject();
            $params['region'] = $arguments[0]->CloudLocation;
        } elseif ($arguments[0] instanceof \DBEBSVolume) {
            $env = $arguments[0]->getEnvironmentObject();
            $params['region'] = $arguments[0]->ec2Region;
        } else {
            throw new InvalidArgumentException(
                'RegionName|DBServer|DBFarmRole|DBEBSVolume are only accepted. Invalid argument ' . get_class($arguments[0])
            );
        }
        $traitFetchEnvProperties($env);
    } elseif (isset($arguments[1]) && ($arguments[1] instanceof \Scalr_Environment || $arguments[1] instanceof \Scalr\Model\Entity\Account\Environment)) {
        $params['region'] = !empty($arguments[0]) ? (string)$arguments[0] : null;
        $env = $arguments[1];
        $traitFetchEnvProperties($env);
    } else {
        $params['region'] = isset($arguments[0]) ? $arguments[0] : null;
        $params['accessKeyId'] = isset($arguments[1]) ? $arguments[1] : null;
        $params['secretAccessKey'] = isset($arguments[2]) ? $arguments[2] : null;
        $params['certificate'] = isset($arguments[3]) ? $arguments[3] : null;
        $params['privateKey'] = isset($arguments[4]) ? $arguments[4] : null;
        $params['environment'] = !isset($arguments[2]) && $cont->initialized('environment') ? $cont->environment : null;
    }

    $serviceid = 'aws.' . hash('sha256', sprintf("%s|%s|%s|%s|%s",
        $params['accessKeyId'], $params['secretAccessKey'], $params['region'],
        (!empty($params['certificate']) ? crc32($params['certificate']) : '-'),
        (!empty($params['privateKey']) ? crc32($params['privateKey']) : '-')
    ), false);

    if (!$cont->initialized($serviceid)) {
        $config = $cont->config;
        $proxySettings = null;

        if ($config('scalr.aws.use_proxy') && in_array($config('scalr.connections.proxy.use_on'), array('both', 'scalr'))) {
            $proxySettings = $config('scalr.connections.proxy');
        }

        $cont->setShared($serviceid, function($cont) use ($params, $proxySettings) {
            if (empty($params['secretAccessKey']) || empty($params['accessKeyId'])) {
                throw new \Scalr\Exception\InvalidCloudCredentialsException();
            }

            $aws = new \Scalr\Service\Aws(
                $params['accessKeyId'], $params['secretAccessKey'], $params['region'],
                $params['certificate'], $params['privateKey']
            );

            if ($proxySettings !== null) {
                $aws->setProxy(
                    $proxySettings['host'], $proxySettings['port'], $proxySettings['user'],
                    $proxySettings['pass'], $proxySettings['type'], $proxySettings['authtype']
                );
            }

            $observer = new \Scalr\Service\Aws\Plugin\EventObserver($aws);
            $aws->setEventObserver($observer);

            if (isset($params['environment']) && $params['environment'] instanceof \Scalr_Environment) {
                $aws->setEnvironment($params['environment']);
            }

            return $aws;
        });
    }

    return $cont->get($serviceid);
};

$container->cloudyn = function ($cont) {
    $params = array();
    $acc = $cont->request->getUser()->getAccount();
    $params['email'] = $acc->getSetting(Scalr_Account::SETTING_CLOUDYN_USER_EMAIL);
    $params['password'] = $acc->getSetting(Scalr_Account::SETTING_CLOUDYN_USER_PASSWD);
    $serviceid = 'cloudyn.' . hash('sha256', $params['email'], false);
    if (!$cont->initialized($serviceid)) {
        $cont->setShared($serviceid, function ($cont) use ($params) {
            return new \Scalr\Service\Cloudyn(
                $params['email'], $params['password'],
                $cont->config->get('scalr.cloudyn.environment')
            );
        });
    }
    return $cont->get($serviceid);
};

$container->openstack = function ($cont, array $arguments = null) {
    /* @var $cont \Scalr\DependencyInjection\Container */
    $params = array();
    if (!isset($arguments[0])) {
        throw new \BadFunctionCallException('Platform value must be provided!');
    } else if (is_object($arguments[0])) {
        if ($arguments[0] instanceof \Scalr\Service\OpenStack\OpenStackConfig) {
            /* @var $config \Scalr\Service\OpenStack\OpenStackConfig */
            $config = $arguments[0];
            $params['username'] = $config->getUsername();
            $params['identityEndpoint'] = $config->getIdentityEndpoint();
            $params['config'] = $config;
            $params['region'] = $config->getRegion();
            $params['identityVersion'] = $config->getIdentityVersion();
            $params['proxySettings'] = $config->getProxySettings();
            $params['requestTimeout'] = $config->getRequestTimeout();
        } else {
            throw new \InvalidArgumentException('Invalid argument type!');
        }
    } else {
        $platform = $arguments[0];
        $params['region'] = isset($arguments[1]) ? (string)$arguments[1] : null;
        if (isset($arguments[2]) && $arguments[2] instanceof \Scalr_Environment) {
            $env = $arguments[2];
        } else {
            $env = $cont->environment;
        }

        $ccProps = $env->keychain($platform)->properties;

        $params['username'] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME];
        $params['identityEndpoint'] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];

        $params['apiKey'] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY];
        if (empty($params['apiKey'])) $params['apiKey'] = null;

        $params['updateTokenCallback'] = function ($token) use ($ccProps, $platform) {
            if (!empty($ccProps) && $token instanceof \Scalr\Service\OpenStack\Client\AuthToken) {
                $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN] = serialize($token);
                $ccProps->save();
            }
        };

        // Some issues with multi-regional openstack deployments and re-using token.
        // Most likely we will need to make it configurable
        $params['authToken'] = null;
        //$params['authToken'] = $env->getPlatformConfigValue($platform . "." . OpenstackPlatformModule::AUTH_TOKEN);
        //$params['authToken'] = empty($params['authToken']) ? null : unserialize($params['authToken']);

        $params['password'] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD];
        $params['tenantName'] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME] ?: null;
        $params['domainName'] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME] ?: null;
        $params['identityVersion'] = $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION];
    }

    //calculates unique identifier of the service
    $serviceid = 'openstack.' . hash('sha256',
        sprintf('%s|%s|%s|%s|%s',
            $params['username'],
            (string)$params['tenantName'],
            (string)$params['domainName'],
            $params['identityEndpoint'],
            $params['region']
        ),
        false
    );

    if (!$cont->initialized($serviceid)) {
        /* @var $config Yaml */
        $config = $cont->config;

        if (isset($platform) &&
            $config->defined("scalr.{$platform}.use_proxy") &&
            $config("scalr.{$platform}.use_proxy") &&
            in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
            $params['proxySettings'] = $config('scalr.connections.proxy');
        }

        if ($config->defined("scalr.{$platform}.api_client.timeout")) {
            $params['requestTimeout'] = $config("scalr.{$platform}.api_client.timeout");
        }

        $cont->setShared($serviceid, function ($cont) use ($params) {
            if (!isset($params['config'])) {
                $params['config'] = new \Scalr\Service\OpenStack\OpenStackConfig(
                    $params['username'], $params['identityEndpoint'], $params['region'], $params['apiKey'],
                    $params['updateTokenCallback'], $params['authToken'], $params['password'], $params['tenantName'],
                    $params['domainName'], $params['identityVersion'],
                    empty($params['proxySettings']) ? null : $params['proxySettings'],
                    empty($params['requestTimeout']) ? null : $params['requestTimeout']
                );
            }

            if ($params['config']->getUsername() == '' || $params['config']->getIdentityEndpoint() == '') {
                throw new \Scalr\Exception\InvalidCloudCredentialsException();
            }

            return new \Scalr\Service\OpenStack\OpenStack($params['config']);
        });
    }

    return $cont->get($serviceid);
};

$container->cloudstack = function($cont, array $arguments = null) {
    /* @var $env \Scalr_Environment */
    $params = [];

    $traitFetchEnvProperties = function ($platform, $env) use (&$params) {
        /* @var $env \Scalr_Environment */
        $ccProps = $env->keychain($platform)->properties;

        $params['apiUrl'] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL];
        $params['apiKey'] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY];
        $params['secretKey'] = $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY];
        $params['platform'] = $platform;
    };

    if (!isset($arguments[0])) {
        throw new \BadFunctionCallException('Platform value must be provided!');
    } else {
        $platform = (string) $arguments[0];
        if (isset($arguments[1]) && $arguments[1] instanceof \Scalr_Environment) {
            $env = $arguments[1];
        } else {
            $env = $cont->environment;
        }
        $traitFetchEnvProperties($platform, $env);
    }

    $serviceid = 'cloudstack.' . hash('sha256', sprintf("%s|%s|%s|%s",
        $params['apiUrl'], $params['apiKey'], $params['secretKey'], $params['platform']
    ), false);

    if (!$cont->initialized($serviceid)) {
        $proxySettings = null;
        /* @var $config Yaml */
        $config = $cont->config;

        if (isset($platform) &&
            $config->defined("scalr.{$platform}.use_proxy") &&
            $config("scalr.{$platform}.use_proxy") &&
            in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
            $proxySettings = $config('scalr.connections.proxy');
        }

        $cont->setShared($serviceid, function($cont) use ($params, $proxySettings) {
            if (empty($params['apiKey']) || empty($params['secretKey'])) {
                throw new \Scalr\Exception\InvalidCloudCredentialsException();
            }

            $cloudstack = new \Scalr\Service\CloudStack\CloudStack(
                $params['apiUrl'], $params['apiKey'], $params['secretKey'], $params['platform']
            );

            if ($proxySettings !== null) {
                $cloudstack->setProxy(
                    $proxySettings['host'], $proxySettings['port'], $proxySettings['user'],
                    $proxySettings['pass'], $proxySettings['type'], $proxySettings['authtype']
                );
            }

            return $cloudstack;
        });
    }

    return $cont->get($serviceid);
};

$container->azure = function($cont, array $arguments = null) {
    $params = [];

    $traitFetchEnvProperties = function ($env) use (&$params) {
        /* @var $env \Scalr_Environment */
        $ccProps = $env->keychain(SERVER_PLATFORMS::AZURE)->properties;

        $params['appClientId'] = $env->config("scalr.azure.app_client_id");
        $params['appSecretKey'] = $env->config("scalr.azure.app_secret_key");
        $params['tenantName'] = $ccProps[Entity\CloudCredentialsProperty::AZURE_TENANT_NAME];
        $params['environment'] = $env;
    };

    if (!isset($arguments[0])) {
        throw new \BadFunctionCallException('Environment value must be provided!');
    } else {
        if ($arguments[0] instanceof \Scalr_Environment) {
            $env = $arguments[0];
        } else {
            $env = $cont->environment;
        }
        $traitFetchEnvProperties($env);
    }

    $serviceId = 'azure.' . hash('sha256', sprintf("%s|%s|%s",
            $params['appClientId'], $params['appSecretKey'], $params['tenantName']
        ), false);

    if (!$cont->initialized($serviceId)) {
        $config = $cont->config;
        $proxySettings = null;

        if ($config('scalr.azure.use_proxy') && in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
            $proxySettings = $config('scalr.connections.proxy');
        }

        $cont->setShared($serviceId, function($cont) use ($params, $proxySettings) {
            if (empty($params['appClientId']) || empty($params['appSecretKey'])) {
                throw new \Scalr\Exception\InvalidCloudCredentialsException();
            }

            $azure = new \Scalr\Service\Azure(
                $params['appClientId'], $params['appSecretKey'], $params['tenantName']
            );

            $azure->setEnvironment($params['environment']);

            if ($proxySettings !== null) {
                $azure->setProxy(
                    $proxySettings['host'], $proxySettings['port'], $proxySettings['user'],
                    $proxySettings['pass'], $proxySettings['type'], $proxySettings['authtype']
                );
            }

            return $azure;
        });
    }

    return $cont->get($serviceId);
};

$container->mailer = function ($cont) {
    $mailer = new \Scalr\SimpleMailer();
    if ($cont->config->get('scalr.email.address')) {
        $mailer->setFrom($cont->config->get('scalr.email.address'), $cont->config->get('scalr.email.name'));
    }
    return $mailer;
};

$container->setShared('ldap.config', function ($cont) {
    $my = $cont->config->get('scalr.connections.ldap');
    return new \Scalr\Net\Ldap\LdapConfig(
        isset($my['host']) ? $my['host'] : 'localhost',
        isset($my['port']) ? $my['port'] : null,
        isset($my['user']) ? $my['user'] : null,
        isset($my['pass']) ? $my['pass'] : null,
        isset($my['base_dn']) ? $my['base_dn'] : null,
        isset($my['filter']['users']) ? $my['filter']['users'] : null,
        isset($my['filter']['groups']) ? $my['filter']['groups'] : null,
        !empty($my['domain']) ? $my['domain'] : null,
        isset($my['base_dn_groups']) ? $my['base_dn_groups'] : null,
        isset($my['group_nesting']) ? $my['group_nesting'] : null,
        isset($my['bind_type']) ? $my['bind_type'] : null,
        isset($my['mail_attribute']) ? $my['mail_attribute'] : null,
        isset($my['fullname_attribute']) ? $my['fullname_attribute'] : null,
        isset($my['username_attribute']) ? $my['username_attribute'] : null,
        isset($my['group_member_attribute']) ? $my['group_member_attribute'] : null,
        isset($my['group_member_attribute_type']) ? $my['group_member_attribute_type'] : null,
        isset($my['groupname_attribute']) ? $my['groupname_attribute'] : null,
        isset($my['group_displayname_attribute']) ? $my['group_displayname_attribute'] : null,
        isset($my['debug']) ? $my['debug'] : false
    );
});

$container->set('ldap', function ($cont, array $arguments = null) {
    $ldapCf = $cont('ldap.config');
    if (count($arguments) != 2) {
        throw new \InvalidArgumentException(
            "You must provide both username and password to LdapClient object!"
        );
    }
    $user = (string) $arguments[0];
    $pass = (string) $arguments[1];
    $uid = null;
    if ($ldapCf->bindType == \Scalr\Net\Ldap\LdapClient::BIND_TYPE_REGULAR) {
        //Adjusts username with default domain if it has not been provided.
        if (($pos = strpos($user, '@')) === false) {
            if (stripos($user, 'DC=') === false)
                $user = $user . '@' . $ldapCf->getDomain();
        } elseif (stripos($ldapCf->getDomain(), substr($user, $pos + 1)) === 0) {
            $user = substr($user, 0, $pos + 1) . $ldapCf->getDomain();
        }
    } elseif ($ldapCf->bindType == \Scalr\Net\Ldap\LdapClient::BIND_TYPE_OPENLDAP) {

        if (preg_match("/^" . preg_quote($ldapCf->usernameAttribute, '/') . "=(?<uid>.+?)(?<!\\\\),/", $user, $matches)) {
            $uid = $matches['uid'];
        } else {
            $uid = $user;
            $user = "{$ldapCf->usernameAttribute}={$user},{$ldapCf->baseDn}";
        }
    }
    return new \Scalr\Net\Ldap\LdapClient($ldapCf, $user, $pass, $uid);
});

$container->setShared('acl', function ($cont) {
    $acl = new \Scalr\Acl\Acl();
    $acl->setDb($cont->adodb);
    return $acl;
});

//Analytics sub container
$container->setShared('analytics', function ($cont) {
    $analyticsContainer = new \Scalr\DependencyInjection\AnalyticsContainer();
    $analyticsContainer->setContainer($cont);
    return $analyticsContainer;
});

//Api sub container
$container->setShared('api', function ($cont) {
    $apiContainer = new \Scalr\DependencyInjection\ApiContainer();
    $apiContainer->setContainer($cont);
    return $apiContainer;
});

$container->analytics->setShared('enabled', function ($analyticsContainer) {
	return (bool)$analyticsContainer->getContainer()->config('scalr.analytics.enabled');
});

$container->analytics->setShared('tags', function ($analyticsContainer) {
    return new \Scalr\Stats\CostAnalytics\Tags($analyticsContainer->getContainer()->cadb);
});

$container->analytics->setShared('prices', function ($analyticsContainer) {
    return new \Scalr\Stats\CostAnalytics\Prices($analyticsContainer->getContainer()->cadb);
});

$container->analytics->setShared('ccs', function ($analyticsContainer) {
    return new \Scalr\Stats\CostAnalytics\CostCentres($analyticsContainer->getContainer()->adodb);
});

$container->analytics->setShared('projects', function ($analyticsContainer) {
    return new \Scalr\Stats\CostAnalytics\Projects($analyticsContainer->getContainer()->adodb);
});

$container->analytics->setShared('events', function ($analyticsContainer) {
    return new \Scalr\Stats\CostAnalytics\Events($analyticsContainer->getContainer()->cadb);
});

$container->analytics->setShared('usage', function ($analyticsContainer) {
    return new \Scalr\Stats\CostAnalytics\Usage($analyticsContainer->getContainer()->cadb);
});

$container->analytics->setShared('notifications', function ($analyticsContainer) {
    return new \Scalr\Stats\CostAnalytics\Notifications($analyticsContainer->getContainer()->cadb);
});

$container->setShared('model.loader', function ($cont) {
    return new \Scalr\Model\Loader\MappingLoader();
});

$container->set('logger', function($cont, array $arguments = null) {
    $params = [];

    if (!empty($arguments[0])) {
        $params[0] = (string)$arguments[0];
    }

    $serviceid = 'logger' . (!empty($params[0]) ? '.' . $params[0] : '');

    if (!$cont->initialized($serviceid)) {
        $cont->setShared($serviceid, function($cont) use ($params) {
            return new \Scalr\Logger(!empty($params[0]) ? $params[0] : null);
        });
    }

    return $cont->get($serviceid);
});

$container->set('warmup', function($cont, array $arguments = null) {
    /* @var $cont \Scalr\DependencyInjection\BaseContainer */
    //Releases cloud credentials
    foreach(['aws', 'openstack', 'cloudstack', 'cloudyn'] as $srv) {
        $cont->release($srv);
    }

    $cont->release('keychain');

    //Releases platform module static cache
    \Scalr\Modules\PlatformFactory::warmup();

    \Scalr_Governance::clearCache();

    return $cont;
});

$container->set('crypto', function ($cont, array $arguments = []) {
        $algo = array_shift($arguments) ?: MCRYPT_RIJNDAEL_128;
        $mode = array_shift($arguments) ?: MCRYPT_MODE_CFB;
        $cryptoKey = array_shift($arguments);
        $keySize = array_shift($arguments) ?: 32;
        $blockSize = array_shift($arguments) ?: 16;

        if ($cryptoKey === null) {
            $cryptoKeyId = APPPATH . "/etc/.cryptokey";
            $cryptoKey = @file_get_contents($cryptoKeyId);

            if (empty($cryptoKey)) {
                throw new ScalrException("Wrong crypto key!");
            }
        } else if ($cryptoKey instanceof SplFileObject) {
            $cryptoKeyId = $cryptoKey->getRealPath();
        } else if(is_resource($cryptoKey) && get_resource_type($cryptoKey) == 'stream') {
            $cryptoKeyId = realpath(stream_get_meta_data($cryptoKey)['uri']);
        } else if((is_string($cryptoKey) || is_numeric($cryptoKey)) && @file_exists($cryptoKey)) {
            $cryptoKeyId = realpath($cryptoKey);
        } else if(is_array($cryptoKey)) {
            $cryptoKeyId = implode('', $cryptoKey);
        } else {
            $cryptoKeyId = $cryptoKey;
        }

        $cryptoId = hash("sha256", "crypto_{$algo}_{$mode}_{$cryptoKeyId}_{$keySize}_{$blockSize}");
        if (!$cont->initialized($cryptoId)) {
            $cont->setShared(
                $cryptoId,
                function ($cont) use ($algo, $mode, $cryptoKey, $keySize, $blockSize) {
                    return new \Scalr\Util\CryptoTool($algo, $mode, $cryptoKey, $keySize, $blockSize);
                }
            );
        }

        return $cont->get($cryptoId);
    }
);

$container->set('srzcrypto', function ($cont, array $arguments = []) {
        $cryptoKey = array_shift($arguments);

        return $cont->crypto(MCRYPT_TRIPLEDES, MCRYPT_MODE_CBC, $cryptoKey, 24, 8);
    }
);

$container->set('http', function ($cont, array $arguments = []) {
    $driver = array_shift($arguments) ?: "curl";
    $persistent = array_shift($arguments) ?: null;
    $options = array_shift($arguments) ?: [];

    $client = new Scalr\System\Http\Client($driver, $persistent);

    $uaInfo = $cont->version;

    $options = array_merge([
        'useragent' => sprintf(
            "%s/%s (%s; %s.%s)",
            isset($options["name"]) ? $options["name"] : "Scalr",
            $uaInfo["version"],
            $uaInfo["edition"],
            $uaInfo["gitRevision"],
            $uaInfo["id"]
        )
    ], $options);
    unset($options["name"]);

    $client->setOptions($options);

    return $client;
});

$container->set('srzhttp', function ($cont, array $arguments = []) {
    $driver = array_shift($arguments) ?: "curl";
    $persistent = array_shift($arguments) ?: null;
    $options = array_merge(array_shift($arguments) ?: [], ['protocol' => \http\Client\Curl\HTTP_VERSION_1_0]);

    return $cont->http($driver, $persistent, $options);
});

$container->setShared("version.info", function ($cont, array $arguments = []) {
    $manifest  = APPPATH . "/../manifest.json";

    $uaInfo = ["short" => ["version" => SCALR_VERSION]];

    $uaInfo["full"] = $uaInfo["short"];

    $gitError = null;

    if (is_readable($manifest)) {
        $info = @json_decode(file_get_contents($manifest), true);

        $uaInfo["full"]["edition"]     = !empty($info["edition"]) && stristr($info["edition"], "ee") ? "Enterprise" : "Community";
        $uaInfo["full"]["gitRevision"] = $info["revision"];
        $uaInfo["full"]["gitFullHash"] = $info["full_revision"];
        $uaInfo["full"]["gitDate"]     = $info["date"];
    }

    if (!array_key_exists("edition", $uaInfo["full"])) {
        @exec("git log -1 --format='%h|%ci|%H' 2>/dev/null", $output, $gitError);

        if (!$gitError) {
            $info = @explode("|", $output[0]);

            $uaInfo["full"]["gitRevision"] = trim($info[0]);
            $uaInfo["full"]["gitFullHash"] = trim($info[2]);
            $uaInfo["full"]["gitDate"]     = trim($info[1]);
        }

        $uaInfo["full"]["edition"] = file_exists(APPPATH . '/../ui') ? "Enterprise" : "Community";
    }

    $uaInfo["full"]["edition"] .= " Edition";
    $uaInfo["full"]["id"]       = SCALR_ID;

    $uaInfo["beta"] = $uaInfo["full"];

    if (isset($gitError) && !$gitError) {
        @exec("git rev-parse --abbrev-ref HEAD 2>/dev/null", $branch);
        $uaInfo["beta"]["branch"] = trim($branch[0]);
    }

    return $uaInfo;
});

$container->set('version', function ($cont, array $arguments = []) {
    $part = array_shift($arguments) ?: 'full';

    return $cont->{'version.info'}[$part];
});

$container->set('keychain', function ($cont, array $arguments = []) {
    /* @var $cont \Scalr\DependencyInjection\Container */
    $cloud = array_shift($arguments);

    if (empty($cloud)) {
        throw new BadFunctionCallException('Cloud value must be provided!');
    }

    $envId = array_shift($arguments) ?: ($cont->environment ? $cont->environment->id : null);

    if (empty($envId)) {
        throw new BadFunctionCallException('Environment value must be provided!');
    }

    $cloudCredentials = null;
    $envCloudCredId = "keychain.env_cloud_creds.{$envId}.{$cloud}";

    /* @var $cloudCredentials Entity\CloudCredentials */
    if (!$cont->initialized($envCloudCredId)) {
        $cont->setShared($envCloudCredId, function ($cont) use ($envId, $cloud, &$cloudCredentials) {
            $cloudCredentials = new Entity\CloudCredentials();
            $envCloudCredentials = new Entity\EnvironmentCloudCredentials();

            /* @var $cloudCredentials Entity\CloudCredentials */
            $cloudCredentials = Entity\CloudCredentials::findOne([
                \Scalr\Model\AbstractEntity::STMT_FROM  => "{$cloudCredentials->table()} JOIN {$envCloudCredentials->table('cecc')} ON {$cloudCredentials->columnId()} = {$envCloudCredentials->columnCloudCredentialsId('cecc')} AND {$cloudCredentials->columnCloud()} = {$envCloudCredentials->columnCloud('cecc')}",
                \Scalr\Model\AbstractEntity::STMT_WHERE => "{$envCloudCredentials->columnEnvId('cecc')} = {$envCloudCredentials->qstr('envId', $envId)} AND {$envCloudCredentials->columnCloud('cecc')} = {$envCloudCredentials->qstr('cloud', $cloud)}"
            ]);

            if (!empty($cloudCredentials)) {
                $cloudCredId = $cloudCredentials->id;

                $cloudCredentials->bindEnvironment($envId);

                return $cloudCredId;
            }

            return null;
        });
    }

    $cloudCredId = $cont->get($envCloudCredId);
    $contCloudCredId = "keychain.cloud_creds.{$cloudCredId}";

    if (!$cont->initialized($contCloudCredId)) {
        $cont->setShared($contCloudCredId, function ($cont) use ($envId, $cloud, $cloudCredId, &$cloudCredentials){
            if (!(isset($cloudCredentials) || empty($cloudCredentials = Entity\CloudCredentials::findPk($cloudCredId)))) {
                $cloudCredentials->bindEnvironment($envId);
            }

            return $cloudCredentials ?: false;
        });
    }

    if (empty($cloudCredentials = $cont->get($contCloudCredId))) {
        $cloudCredentials = new Entity\CloudCredentials();
        $cloudCredentials->accountId = (empty($cont->environment) || $cont->environment->id != $envId) ? \Scalr_Environment::init()->loadById($envId)->getAccountId() : $cont->environment;
        $cloudCredentials->envId = $envId;
        $cloudCredentials->cloud = $cloud;
    }

    return $cloudCredentials;
});

$container->setShared('saml.config', function ($cont) {
    $settings = $cont->config->get('scalr.connections.saml');

    // Adjust saml service provider settings based on the scalr base url
    $baseUrl = $cont->config('scalr.endpoint.scheme') . "://" . rtrim($cont->config('scalr.endpoint.host'), '/');

    $settings['sp']['entityId'] = $baseUrl . '/public/saml?metadata';
    $settings['sp']['assertionConsumerService']['url'] = $baseUrl . '/public/saml?acs';
    $settings['sp']['singleLogoutService']['url'] = $baseUrl . '/public/saml?sls';

    return $settings;
});

$container->set('saml', function ($cont) {
    return new OneLogin_Saml2_Auth($cont->{'saml.config'});
});

$container->setShared('auditlogger', function ($cont) {
    /* @var $cont Container */
    $request = $cont->initialized('auditlogger.request');

    if (!$request) {
        throw new Exception("Audit logger request has not been initialized.");
    }

    return new AuditLogger($cont->{'auditlogger.request'}->getAuditLoggerConfig());
});

$container->setShared('apilogger', function ($cont) {
    /* @var $cont Container */
    $config = new ApiLoggerConfiguration(ApiLogger::REQUEST_TYPE_API);

    $config->ipAddress = $cont->api->initialized('request') ? $cont->api->request->getIp() : null;
    $config->requestId = $cont->api->initialized('meta') ? $cont->api->meta->requestId : null;

    return new ApiLogger($config);
});


$container->setShared('userlogger', function ($cont) {
    return new UserLogger();
});
