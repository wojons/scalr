<?php
namespace Scalr\Service\OpenStack;

use Scalr\Exception\NotSupportedException;
use Scalr\Service\OpenStack\Client\Auth\RequestBuilderInterface;
use Scalr\Service\OpenStack\Client\Auth\RequestBuilderV2;
use Scalr\Service\OpenStack\Client\Auth\RequestBuilderV3;
use Scalr\Service\OpenStack\Client\AuthToken;
use Scalr\Service\OpenStack\Exception\OpenStackException;

/**
 * OpenStack configuration object
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    06.12.2012
 */
class OpenStackConfig
{

    /**
     * OpenStack Account Location
     * @var string
     */
    private $identityEndpoint;

    /**
     * OpenStack Region
     * @var string
     */
    private $region;

    /**
     * OpenStack Username
     * @var string
     */
    private $username;

    /**
     * OpenStack user Id
     * @var string
     */
    private $userId;

    /**
     * User password
     * @var string|null
     */
    private $password;

    /**
     * User API Key
     * @var string
     */
    private $apiKey;

    /**
     * @var \Closure
     */
    private $updateTokenCallback;

    /**
     * Authentication token.
     * @var AuthToken
     */
    private $authToken;

    /**
     * OpenStack tenant name.
     * Is project name to Identity v3.
     *
     * @var string
     */
    private $tenantName;

    /**
     * OpenStack project id.
     *
     * @var string
     */
    private $projectId;

    /**
     * OpenStack version
     * @var string
     */
    private $identityVersion;

    /**
     * @var RequestBuilderInterface
     */
    private $authRequestBuilder;

    /**
     * Proxy settings:
     *  host
     *  port
     *  user
     *  pass
     *  type
     *
     * @see config.yml
     *
     * @var array
     */
    private $proxySettings;

    /**
     * Convenient constructor
     *
     * @param   string                    $username            An user name
     * @param   string                    $identityEndpoint    OpenStack Identity Endpoint
     * @param   string                    $region              OpenStack Region
     * @param   string                    $apiKey              optional An User's API Key
     * @param   \Closure                  $updateTokenCallback optional Update Token Callback
     *                                                         This function must accept one parameter AuthToken object.
     * @param   AuthToken                 $authToken           optional Authentication token for the OpenStack service.
     * @param   string                    $password            optional An User's password
     * @param   string                    $tenantName          optional Either tenant name for V2 or project for V3
     * @param   string                    $identityVersion     optional The version of the identity
     * @param   array                     $proxySettings       optional Proxy settings
     */
    public function __construct($username, $identityEndpoint, $region, $apiKey = null, \Closure $updateTokenCallback = null,
                                AuthToken $authToken = null, $password = null, $tenantName = null, $identityVersion = null, array $proxySettings = null)
    {
        if ($identityVersion === null) {
            $identityVersion = static::parseIdentityVersion($identityEndpoint);
        }

        $this
            ->setUsername($username)
            ->setIdentityEndpoint($identityEndpoint)
            ->setRegion($region)
            ->setPassword($password)
            ->setApiKey($apiKey)
            ->setUpdateTokenCallback($updateTokenCallback)
            ->setAuthToken($authToken)
            ->setTenantName($tenantName)
            ->setIdentityVersion($identityVersion)
            ->setProxySettings($proxySettings)
        ;
    }

    /**
     * Gets OpenStack tenant name
     *
     * @return  string Returns OpenStack tenant name.
     */
    public function getTenantName()
    {
        return $this->tenantName;
    }

    /**
     * Sets OpenStack tenant name
     *
     * @param   string $tenantName OpenStack tenant name
     * @return  OpenStackConfig
     */
    public function setTenantName($tenantName)
    {
        $this->tenantName = $tenantName;
        return $this;
    }

    /**
     * Gets OpenStack user id
     *
     * @return  string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Sets OpenStack user id
     *
     * @param   string  $userId OpenStack user id
     *
     * @return  $this
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Gets OpenStack project id
     *
     * @return  string
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * Sets OpenStack project id
     *
     * @param   string  $projectId OpenStack project id
     *
     * @return  $this
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;

        return $this;
    }

    /**
     * Gets OpenStack project id
     * It same as tenant name for Identity v2
     *
     * @return  string
     */
    public function getProjectName()
    {
        return $this->tenantName;
    }

    /**
     * Gets region
     *
     * @return  string   Returns OpenStack Region
     */
    public function getRegion()
    {
        return $this->region;
    }

    /**
     * Sets OpenStack Region
     *
     * @param   string   $region OpenStack Region
     * @return  OpenStackConfig
     */
    public function setRegion($region)
    {
        $this->region = $region;
        return $this;
    }

    /**
     * Gets OpenStack identity endpoint
     *
     * @return  string Returns identity endpoint
     */
    public function getIdentityEndpoint()
    {
        return $this->identityEndpoint;
    }

    /**
     * Gets an username
     *
     * @return  string Returns an username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Gets user's password
     * @return  string Returns user's password
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Gets User's API Key
     * @return  string  $apiKey Returns user API key
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Sets a OpenStack identity endpoint
     *
     * @param   string $identityEndpoint OpenStack identity endpoint
     * @return  OpenStackConfig
     */
    public function setIdentityEndpoint($identityEndpoint)
    {
        $this->identityEndpoint = $identityEndpoint;

        return $this;
    }

    /**
     * Sets username
     *
     * @param   string $username An User name.
     * @return  OpenStackConfig
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Sets user's password
     *
     * @param   string $password An User password.
     * @return  OpenStackConfig
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Sets API Key
     *
     * @param   string $apiKey An User API Key
     * @return  OpenStackConfig
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }
    /**
     * Gets update token callback
     *
     * @return  \Closure Returns update token callback
     */
    public function getUpdateTokenCallback()
    {
        return $this->updateTokenCallback;
    }

    /**
     * Sets update token callback
     *
     * @param   \Closure $updateTokenCallback Update token callback must accept one argument - AuthToken
     */
    public function setUpdateTokenCallback(\Closure $updateTokenCallback = null)
    {
        $this->updateTokenCallback = $updateTokenCallback;

        return $this;
    }

    /**
     * Gets an Auth Token
     *
     * @return  AuthToken An authentication token.
     */
    public function getAuthToken()
    {
        return $this->authToken;
    }

    /**
     * Checks whether this is OpenStack Endpoint
     *
     * @return  bool Returns TRUE if it is OpenStack Endpoint
     */
    public function isOpenStack()
    {
        return $this->getTenantName() !== null;
    }

    /**
     * Sets an Auth Token
     *
     * @param   AuthToken  $authToken An authentication token.
     * @return  OpenStackConfig
     */
    public function setAuthToken(AuthToken $authToken = null)
    {
        $this->authToken = $authToken;

        return $this;
    }

    /**
     * Gets auth query string
     *
     * @return array Returns auth query
     *
     * @throws NotSupportedException
     * @throws OpenStackException
     */
    public function getAuthQueryString()
    {
        return $this->authRequestBuilder->makeRequest($this);
    }

    /**
     * Sets OpenStack API version
     *
     * @param int $version
     *
     * @return $this
     *
     * @throws NotSupportedException
     */
    public function setIdentityVersion($version = null)
    {
        $this->identityVersion = $version ?: 2;

        switch ($this->identityVersion) {
            case 2:
                $this->authRequestBuilder = new RequestBuilderV2();
                break;

            case 3:
                $this->authRequestBuilder = new RequestBuilderV3();
                break;

            default:
                throw new NotSupportedException("Unsupported api version: {$this->identityVersion}");
        }

        return $this;
    }

    /**
     * Gets OpenStack API version
     *
     * @return string Returns the version of the identity
     */
    public function getIdentityVersion()
    {
        return $this->identityVersion ?: 2;
    }

    /**
     * Parses the version of identity endpoint url
     *
     * @param    string    $keystone  The identity endpoint url
     * @return   int|null  Returns the major version number or NULL if it cannot be obtained from the specified URL
     */
    public static function parseIdentityVersion($keystone)
    {
        preg_match_all('/\/v(?P<major>\d+)(?:\.(?P<minor>\d+))?/', $keystone, $matches);

        return array_shift($matches['major']);
    }

    public function setProxySettings(array $proxySettings = null)
    {
        $this->proxySettings = $proxySettings;

        return $this;
    }

    public function getProxySettings()
    {
        return $this->proxySettings;
    }
}