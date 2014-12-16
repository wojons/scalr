<?php

namespace Scalr\DependencyInjection;

/**
 * Main DependencyInjection container.
 *
 * @author   Vitaliy Demidov    <vitaliy@scalr.com>
 * @since    19.10.2012
 *
 * @property string $awsRegion
 *           The AWS region derived from user's environment.
 *
 * @property string $awsSecretAccessKey
 *           The AWS sercret access key taken from user's environment.
 *
 * @property string $awsAccessKeyId
 *           The Aws access key id taken from user's environment.
 *
 * @property string $awsAccountNumber
 *           The Aws account number.
 *
 * @property \Scalr_Session $session
 *           The Scalr Session isntance.
 *
 * @property \Scalr\Service\Cloudyn $cloudyn
 *           The Cloudyn instance for the current user
 *
 * @property \Scalr_Environment $environment
 *           Recently loaded Scalr_Environment instance.
 *
 * @property \Scalr\Service\Aws $aws
 *           The Aws instance for the last instantiated user's environment.
 *
 * @property \Scalr\Service\CloudStack\CloudStack $cloudstack
 *           The Cloudstack instance for the last instantiated user's environment.
 *
 * @property \Scalr_UI_Request $request
 *           The Scalr_UI_Request instance.
 *
 * @property \Scalr_Account_User $user
 *           The Scalr_Account_User instance which is property for the request.
 *
 * @property \Scalr\Logger\AuditLog $auditLog
 *           The AuditLog.
 *
 * @property \Scalr\Logger\LoggerStorageInterface $auditLogStorage
 *           The AuditLogStorage
 *
 * @property \Scalr\SimpleMailer $mailer
 *           Returns the new instance of the SimpleMailer class.
 *           This is not a singletone.
 *
 * @property \ADODB_mysqli $adodb
 *           Gets an ADODB mysqli Connection object
 *
 * @property \ADODB_mysqli $dnsdb
 *           Gets an ADODB mysqli Connection to PDNS Database
 *
 * @property \ADODB_mysqli $cadb
 *           Gets an ADODB mysqli Connection to Cost Analytics database
 *
 * @property \Scalr\System\Config\Yaml $config
 *           Gets configuration
 *
 * @property \Scalr\Acl\Acl $acl
 *           Gets an ACL shared service
 *
 * @property \Scalr\DependencyInjection\AnalyticsContainer $analytics
 *           Gets Cost Analytics sub container
 *
 * @property \Scalr\Logger $logger
 *           Gets logger service
 *
 *
 * @method   mixed config()
 *           config(string $name)
 *           Gets config value for the dot notation access key
 *
 * @method   \Scalr\Service\Aws aws()
 *           aws(string|\DBServer|\DBFarmRole|\DBEBSVolume $awsRegion = null,
 *               string|\Scalr_Environment $awsAccessKeyId = null,
 *               string $awsSecretAccessKey = null,
 *               string $certificate = null,
 *               string $privateKey = null)
 *           Gets an Aws instance.
 *
 * @method   \Scalr\Service\Eucalyptus eucalyptus()
 *           eucalyptus(string|\DBServer|\DBFarmRole $cloudLocation, \Scalr_Environment $env = null)
 *           Gets an Eucalyptus instance
 *
 * @method   \Scalr\Service\CloudStack\CloudStack cloudstack()
 *           cloudstack(string $platform = null,
 *                      string|\Scalr_Environment $apiUrl = null,
 *                      string $apiKey = null,
 *                      string $secretKey = null)
 *           Gets an CloudStack instance.
 *
 * @method   \Scalr\Service\OpenStack\OpenStack openstack()
 *           openstack(string|\Scalr\Service\OpenStack\OpenStackConfig $platform, string $region, \Scalr_Environment $env = null)
 *           Gets an Openstack instance for the current environment
 *
 * @method   \ADODB_mysqli adodb()
 *           adodb()
 *           Gets an ADODB mysqli Connection object
 *
 * @method   \Scalr\Net\Ldap\LdapClient ldap()
 *           ldap($user, $password)
 *           Gets a new instance of LdapClient for specified user
 *
 * @method   \Scalr\Logger logger()
 *           logger(string $name = null)
 *           Gets logger for specified category or class
 *
 * @method   \Scalr\DependencyInjection\Container warmup()
 *           warmup()
 *           Releases static cache from the dependency injection service
 */
class Container extends BaseContainer
{
    /**
     * @var Container
     */
    static private $instance;

    /**
     * Gets singleton instance of the Container
     *
     * @return Container
     */
    static public function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Container();
        }
        return self::$instance;
    }

    /**
     * Resets singleton object.
     *
     * It can be used for phpunit testing purposes.
     */
    static public function reset()
    {
        self::$instance = null;
    }
}