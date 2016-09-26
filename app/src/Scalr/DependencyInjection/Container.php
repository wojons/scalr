<?php

namespace Scalr\DependencyInjection;

/**
 * Main DependencyInjection container.
 *
 * @author   Vitaliy Demidov    <vitaliy@scalr.com>
 * @since    19.10.2012
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
 * @property \Scalr\Service\Azure $azure
 *           The Azure instance for the last instantiated user's environment.
 *
 * @property \Scalr_UI_Request $request
 *           The Scalr_UI_Request instance.
 *
 * @property \Scalr_Account_User $user
 *           The Scalr_Account_User instance which is property for the request.
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
 * @property \Scalr\DependencyInjection\ApiContainer $api
 *           Gets REST API sub container
 *
 * @property \Scalr\Logger $logger
 *           Gets logger service
 *
 * @property \Scalr\Util\CryptoTool $crypto
 *           Gets cryptotool
 *
 * @property \Scalr\Util\CryptoTool $srzcrypto
 *           Gets Scalarizr cryptotool
 *
 * @property \Scalr\System\Http\Client $http
 *           Gets PECL http 2.x client
 *
 * @property \Scalr\System\Http\Client $srzhttp
 *           Gets PECL http 2.x client configured for scalarizr
 *
 * @property array $version
 *           Gets information about Scalr version
 *
 * @property \OneLogin_Saml2_Auth $saml
 *           Gets SAML 2.0 Auth
 *
 * @property \Scalr\LogCollector\AuditLogger $auditlogger
 *           Gets AuditLogger instance
 *
 * @property \Scalr\LogCollector\UserLogger $userlogger
 *           Gets UserLogger instance
 *
 * @property \Scalr\LogCollector\ApiLogger $apilogger
 *           Gets ApiLogger instance
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
 * @method   \Scalr\Service\CloudStack\CloudStack cloudstack()
 *           cloudstack(string $platform = null,
 *                      string|\Scalr_Environment $apiUrl = null,
 *                      string $apiKey = null,
 *                      string $secretKey = null)
 *           Gets an CloudStack instance.
 *
 * @method   \Scalr\Service\Azure azure()
 *           azure()
 *           Gets an Azure instance.
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
 *
 * @method   \Scalr\Util\CryptoTool crypto(string $algo = MCRYPT_RIJNDAEL_256, string $method = MCRYPT_MODE_CFB, mixed $cryptoKey = null, int $keySize = null, int $blockSize = null)
 *           crypto(string $algo = MCRYPT_RIJNDAEL_256, string $method = MCRYPT_MODE_CFB, string|resource|SplFileObject|array $cryptoKey = null, int $keySize = null, int $blockSize = null)
 *           Gets cryptographic tool with a given algorithm
 *
 * @method   \Scalr\Util\CryptoTool srzcrypto(mixed $cryptoKey = null)
 *           srzcrypto(string|resource|SplFileObject|array $cryptoKey = null)
 *           Gets Scalarizr cryptographic tool
 *
 * @method   \Scalr\System\Http\Client http()
 *           Gets PECL http 2.x client
 *
 * @method   \Scalr\System\Http\Client srzhttp()
 *           Gets PECL http 2.x client configured for scalarizr
 *
 * @method   array version()
 *           version(string $part = 'full')
 *           Gets information about Scalr version
 *
 * @method   \Scalr\Model\Entity\CloudCredentials keychain(string $cloud, int $envId = null)
 *           keychain(string $cloud, int $envId = null)
 *           Gets specified cloud credentials for specified environment
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
    public static function getInstance()
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
    public static function reset()
    {
        self::$instance = null;
    }
}
