<?php

/**
 * Dependency injection container configuration
 *
 * @author  Vitaliy Demidov    <vitaliy@scalr.com>
 * @since   01.11.2012
 */

$container = Scalr::getContainer();

/* @var $cont \Scalr\DependencyInjection\Container */

$container->setShared('config', function ($cont) {
    if ($cont('config.type') == 'yaml') {
        $loader = new \Scalr\System\Config\Loader();
        $cfg = $loader->load();
    } else {
        $cfg = @parse_ini_file(APPPATH . '/etc/config.ini', true);
    }
    return $cfg;
});

$container->setShared('config.type', function ($cont) {
    //TODO [SCALRCORE-375] Use new config.yml. This method and related sections will be completely removed by the first of January 2014.
    return is_readable(APPPATH . '/etc/config.ini') ? 'ini' : 'yaml';
});

//ADODB connection
$container->setShared('adodb', function ($cont) {
    return new \Scalr\Db\ConnectionPool($cont('adodb.dsn'));
});

$container->setShared('adodb.dsn', function ($cont) {
    if ($cont('config.type') == 'yaml') {
        $my = $cont->config->get('scalr.connections.mysql');
    } else {
        $my = $cont->config;
        $my = $my['db'];
    }
    $dsn = sprintf(
        "%s://%s:%s@%s/%s",
        (isset($my['driver']) ? $my['driver'] : 'mysqli'),
        $my['user'], rawurlencode($my['pass']),
        (isset($my['host']) ? $my['host'] : 'localhost') . (isset($my['port']) ? ':' . $my['port'] : ''),
        $my['name']
    );

    return $dsn;
});

$container->setShared('dnsdb', function ($cont) {
    return new \Scalr\Db\ConnectionPool($cont('dnsdb.dsn'));
});

$container->setShared('dnsdb.dsn', function ($cont) {
    $my = $cont->config->get('scalr.dns.mysql');
    $dsn = sprintf(
        "%s://%s:%s@%s/%s",
        (isset($my['driver']) ? $my['driver'] : 'mysqli'),
        $my['user'], rawurlencode($my['pass']),
        (isset($my['host']) ? $my['host'] : 'localhost') . (isset($my['port']) ? ':' . $my['port'] : ''),
        $my['name']
    );
    return $dsn;
});

$container->session = function ($cont) {
    return Scalr_Session::getInstance();
};

$container->user = function ($cont) {
    return $cont->initialized('request') &&
           $cont->request->getUser() instanceof Scalr_Account_User ?
           $cont->request->getUser() : null;
};

$container->awsRegion = function ($cont) {
    return $cont->dbServer->GetProperty(EC2_SERVER_PROPERTIES::REGION);
};

$container->awsAccessKeyId = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCESS_KEY);
};

$container->awsSecretAccessKey = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::SECRET_KEY);
};

$container->awsAccountNumber = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::ACCOUNT_ID);
};

$container->awsCertificate = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE);
};

$container->awsPrivateKey = function ($cont) {
    return $cont->environment->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY);
};

$container->aws = function ($cont, array $arguments = null) {
    /* @var $env \Scalr_Environment */
    $params = array();
    $traitFetchEnvProperties = function ($env) use (&$params) {
        $params['accessKeyId'] = $env->getPlatformConfigValue(Modules_Platforms_Ec2::ACCESS_KEY);
        $params['secretAccessKey'] = $env->getPlatformConfigValue(Modules_Platforms_Ec2::SECRET_KEY);
        $params['certificate'] = $env->getPlatformConfigValue(Modules_Platforms_Ec2::CERTIFICATE);
        $params['privateKey'] = $env->getPlatformConfigValue(Modules_Platforms_Ec2::PRIVATE_KEY);
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
    } elseif (isset($arguments[1]) && $arguments[1] instanceof \Scalr_Environment) {
        $params['region'] = !empty($arguments[0]) ? (string)$arguments[0] : null;
        $env = $arguments[1];
        $traitFetchEnvProperties($env);
    } else {
        $params['region'] = isset($arguments[0]) ? $arguments[0] : ($cont->initialized('dbServer') ? $cont->awsRegion : null);
        $params['accessKeyId'] = isset($arguments[1]) ? $arguments[1] : $cont->awsAccessKeyId;
        $params['secretAccessKey'] = isset($arguments[2]) ? $arguments[2] : $cont->awsSecretAccessKey;
        $params['certificate'] = isset($arguments[3]) ? $arguments[3] : $cont->awsCertificate;
        $params['privateKey'] = isset($arguments[4]) ? $arguments[4] : $cont->awsPrivateKey;
        $params['environment'] = !isset($arguments[2]) ? $cont->environment : null;
    }

    $serviceid = 'aws.' . hash('sha256', sprintf("%s|%s|%s|%s|%s",
        $params['accessKeyId'], $params['secretAccessKey'], $params['region'],
        (!empty($params['certificate']) ? crc32($params['certificate']) : '-'),
        (!empty($params['privateKey']) ? crc32($params['privateKey']) : '-')
    ), false);

    if (!$cont->initialized($serviceid)) {
        $cont->setShared($serviceid, function($cont) use ($params) {
            $aws = new \Scalr\Service\Aws(
                $params['accessKeyId'], $params['secretAccessKey'], $params['region'],
                $params['certificate'], $params['privateKey']
            );
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

$container->setShared('auditLogStorage', function ($cont) {
    $dsn = $cont->get('adodb.dsn');
    $type = 'Mysql';
    $storageClass = 'Scalr\\Logger\\' . $type . 'LoggerStorage';
    return new $storageClass (array('dsn' => $dsn));
});

$container->auditLog = function ($cont) {
    $cont->auditLogEnabled = $cont->config->get('scalr.auditlog.enabled') ? true : false;
    $serviceid = 'auditLog.' . ((string)$cont->user->getId());
    if (!$cont->initialized($serviceid)) {
        $cont->setShared($serviceid, function ($cont) {
            $obj = new \Scalr\Logger\AuditLog(
                $cont->user, $cont->auditLogStorage, array('enabled' => $cont->auditLogEnabled)
            );
            $obj->setContainer($cont);
            return $obj;
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
                ($cont('config.type') == 'yaml' ? $cont->config->get('scalr.cloudyn.environment') : 'PROD')
            );
        });
    }
    return $cont->get($serviceid);
};

$container->openstack = function ($cont, array $arguments = null) {
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

        $params['username'] = $env->getPlatformConfigValue($platform . "." . Modules_Platforms_Openstack::USERNAME);
        $params['identityEndpoint'] = $env->getPlatformConfigValue($platform . "." . Modules_Platforms_Openstack::KEYSTONE_URL);

        $params['apiKey'] = $env->getPlatformConfigValue($platform . "." . Modules_Platforms_Openstack::API_KEY);
        if (empty($params['apiKey'])) $params['apiKey'] = null;

        $params['updateTokenCallback'] = function ($token) use ($env, $platform) {
            if ($env && $token instanceof \Scalr\Service\OpenStack\Client\AuthToken) {
                $env->setPlatformConfig(array(
                    $platform . "." . Modules_Platforms_Openstack::AUTH_TOKEN => serialize($token),
                ));
            }
        };

        $params['authToken'] = $env->getPlatformConfigValue($platform . "." . Modules_Platforms_Openstack::AUTH_TOKEN);
        $params['authToken'] = empty($params['authToken']) ? null : unserialize($params['authToken']);

        $params['password'] = $env->getPlatformConfigValue($platform . "." . Modules_Platforms_Openstack::PASSWORD);

        $params['tenantName'] = $env->getPlatformConfigValue($platform. "." . Modules_Platforms_Openstack::TENANT_NAME);
        if (empty($params['tenantName'])) $params['tenantName'] = null;
    }

    //calculates unique identifier of the service
    $serviceid = 'openstack.' . hash('sha256',
        sprintf('%s|%s|%s', $params['username'], crc32($params['identityEndpoint']), $params['region']),
        false
    );

    if (!$cont->initialized($serviceid)) {
        $cont->setShared($serviceid, function ($cont) use ($params) {
            if (!isset($params['config'])) {
                $params['config'] = new \Scalr\Service\OpenStack\OpenStackConfig(
                    $params['username'], $params['identityEndpoint'], $params['region'], $params['apiKey'],
                    $params['updateTokenCallback'], $params['authToken'], $params['password'], $params['tenantName']
                );
            }
            return new \Scalr\Service\OpenStack\OpenStack($params['config']);
        });
    }

    return $cont->get($serviceid);
};

$container->mailer = function ($cont) {
    $mailer = new \Scalr\SimpleMailer();
    if ($cont('config.type') == 'yaml') {
        if ($cont->config->get('scalr.email.address')) {
            $mailer->setFrom($cont->config->get('scalr.email.address'), $cont->config->get('scalr.email.name'));
        }
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
    if ($ldapCf->bindType != \Scalr\Net\Ldap\LdapClient::BIND_TYPE_SIMPLE) {
        //Adjusts username with default domain if it has not been provided.
        if (($pos = strpos($user, '@')) === false) {
            if (stripos($user, 'DC=') === false)
                $user = $user . '@' . $ldapCf->getDomain();
        } elseif (stripos($ldapCf->getDomain(), substr($user, $pos + 1)) === 0) {
            $user = substr($user, 0, $pos + 1) . $ldapCf->getDomain();
        }
    }
    return new \Scalr\Net\Ldap\LdapClient($ldapCf, $user, $pass);
});

$container->setShared('acl', function ($cont) {
    $acl = new \Scalr\Acl\Acl();
    $acl->setDb($cont->adodb);
    return $acl;
});

$container->set('eucalyptus', function($cont, array $arguments = null) {
    $params = array();
    $traitFetchEnvProperties = function ($env) use (&$params) {
        $params['accessKeyId'] = $env->getPlatformConfigValue(Modules_Platforms_Eucalyptus::ACCESS_KEY, true, $params['region']);
        $params['secretAccessKey'] = $env->getPlatformConfigValue(Modules_Platforms_Eucalyptus::SECRET_KEY, true, $params['region']);
        $params['certificate'] = $env->getPlatformConfigValue(Modules_Platforms_Eucalyptus::CERTIFICATE, true, $params['region']);
        $params['privateKey'] = $env->getPlatformConfigValue(Modules_Platforms_Eucalyptus::PRIVATE_KEY, true, $params['region']);
        $params['ec2url'] = $env->getPlatformConfigValue(Modules_Platforms_Eucalyptus::EC2_URL, true, $params['region']);
        $params['s3url'] = $env->getPlatformConfigValue(Modules_Platforms_Eucalyptus::S3_URL, true, $params['region']);
        $params['environment'] = $env;
    };

    if (!empty($arguments) && is_object($arguments[0])) {
        //Makes it possible to get aws instance by dbserver object
        if ($arguments[0] instanceof \DBServer) {
            $env = $arguments[0]->GetEnvironmentObject();
            $params['region'] = $arguments[0]->GetProperty(EUCA_SERVER_PROPERTIES::REGION);
        } elseif ($arguments[0] instanceof \DBFarmRole) {
            $env = $arguments[0]->GetFarmObject()->GetEnvironmentObject();
            $params['region'] = $arguments[0]->CloudLocation;
        } else {
            throw new InvalidArgumentException(
                'string|DBServer|DBFarmRole are only accepted. Invalid argument ' . get_class($arguments[0])
            );
        }
        $traitFetchEnvProperties($env);
    } elseif (isset($arguments[1]) && $arguments[1] instanceof \Scalr_Environment) {
        $params['region'] = !empty($arguments[0]) ? (string)$arguments[0] : null;
        $env = $arguments[1];
        $traitFetchEnvProperties($env);
    } else {
        $params['region'] = isset($arguments[0]) ? (string)$arguments[0] : null;
        if ($cont->environment instanceof \Scalr_Environment) {
            $traitFetchEnvProperties($cont->environment);
        } else {
            throw new \Exception("Environment has not been defined for DI container.");
        }
    }

    $serviceid = 'eucalyptus.' . hash('sha256', sprintf("%s|%s|%s|%s|%s",
        $params['accessKeyId'], $params['secretAccessKey'], $params['region'],
        (!empty($params['certificate']) ? crc32($params['certificate']) : '-'),
        (!empty($params['privateKey']) ? crc32($params['privateKey']) : '-')
    ), false);

    if (!$cont->initialized($serviceid)) {
        $cont->setShared($serviceid, function($cont) use ($params) {
            $client = new \Scalr\Service\Eucalyptus(
                $params['accessKeyId'], $params['secretAccessKey'], $params['region'],
                $params['certificate'], $params['privateKey']
            );
            $client->setUrl('ec2', $params['ec2url']);
            $client->setUrl('s3', $params['s3url']);
            if (isset($params['environment']) && $params['environment'] instanceof \Scalr_Environment) {
                $client->setEnvironment($params['environment']);
            }
            return $client;
        });
    }

    return $cont->get($serviceid);
});