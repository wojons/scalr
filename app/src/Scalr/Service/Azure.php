<?php

namespace Scalr\Service;

use Scalr\Model\Entity\CloudCredentialsProperty;
use Scalr\Service\Azure\AbstractService;
use Scalr\Service\Azure\DataType\GeoLocationData;
use Scalr\Service\Azure\DataType\ProviderData;
use Scalr\Service\Azure\DataType\ProviderList;
use Scalr\Service\Azure\DataType\RoleAssignmentData;
use Scalr\Service\Azure\DataType\RoleDefinitionData;
use Scalr\Service\Azure\DataType\SubscriptionData;
use Scalr\Service\Azure\DataType\SubscriptionList;
use Scalr\Service\Azure\Exception\RestApiException;
use Scalr\Service\Azure\Client\QueryClient;

/**
 * Azure compute service interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 *
 * @property \Scalr\Service\Azure\Services\ComputeService $compute
 *
 * @property \Scalr\Service\Azure\Services\NetworkService $network
 *
 * @property \Scalr\Service\Azure\Services\ResourceManagerService $resourceManager
 *
 * @property \Scalr\Service\Azure\Services\StorageService $storage
 */

class Azure
{
    /**
     * Service name for Resource.
     */
    const SERVICE_RESOURCE_MANAGER = 'resourceManager';

    /**
     * Service name for Compute.
     */
    const SERVICE_COMPUTE = 'compute';

    /**
     * Service name for Network.
     */
    const SERVICE_NETWORK = 'network';

    /**
     * Service name for Storage.
     */
    const SERVICE_STORAGE = 'storage';

    /**
     * Token type
     */
    const TOKEN_TYPE_ACCESS = 'access';

    /**
     * Token type
     */
    const TOKEN_TYPE_REFRESH = 'refresh';

    /**
     * Token type
     */
    const TOKEN_TYPE_CLIENT = 'client';

    const URL_LOGIN_WINDOWS = 'https://login.windows.net';

    const URL_MICROSOFT_ONLINE = 'https://management.core.windows.net';

    const URL_GRAPH_WINDOWS = 'https://graph.windows.net';

    const URL_MANAGEMENT_WINDOWS = 'https://management.azure.com';

    const URL_CORE_WINDOWS  = 'https://management.core.windows.net';

    const AUTH_API_VERSION = '1.0';

    const GRAPH_API_VERSION = '1.5';

    const RESOURCE_API_VERSION = '2015-05-01-preview';

    const SUBSCRIPTION_API_VERSION = '2015-01-01';

    /**
     * Application Client ID.
     *
     * @var string
     */
    private $appClientId;

    /**
     * Application Secret Key.
     *
     * @var string
     */
    private $appSecretKey;

    /**
     * Name of used Tenant if account type is 'Live', else equals 'common'.
     *
     * @var string
     */
    private $tenantName;

    /**
     * Token to access Azure AD API.
     *
     * @var object
     */
    private $accessToken;

    /**
     * Tenant validation result (true if valid, else false) for 'Live' account types.
     *
     * @var bool
     */
    private $tenantIsValid;

    /**
     * Token to refresh Access Token.
     *
     * @var object
     */
    private $refreshToken;

    /**
     * Client token
     *
     * @var object
     */
    private $clientToken;

    /**
     * Rest query client
     *
     * @var QueryClient
     */
    private $client;

    /**
     * Application Object ID.
     *
     * @var string
     */
    private $appObjectId;

    /**
     * An environment object
     *
     * @var \Scalr_Environment
     */
    private $environment;

    /**
     * List of instances of Azure services.
     *
     * @var array
     */
    private $services = [];

    /**
     * Proxy Host
     *
     * @var string
     */
    private $proxyHost;

    /**
     * Proxy Port
     *
     * @var int
     */
    private $proxyPort;

    /**
     * The username that is used for proxy
     *
     * @var string
     */
    private $proxyUser;

    /**
     * Proxy password
     *
     * @var string
     */
    private $proxyPass;

    /**
     * The type of the proxy
     *
     * @var int
     */
    private $proxyType;

    /**
     * The auth type of the proxy
     *
     * @var int
     */
    private $authType;

    /**
     * Constructor.
     *
     * @param string $appClientId  Application Client ID
     * @param string $appSecretKey Application Secret Key
     * @param string $tenantName   Name of tenant.
     */
    public function __construct($appClientId, $appSecretKey, $tenantName)
    {
        $this->appClientId = $appClientId;
        $this->appSecretKey = $appSecretKey;
        $this->tenantName = $tenantName;
    }

    /**
     * Sets an Scalr environment object which is associated with the Azure client instance
     *
     * @param   \Scalr_Environment $environment An environment object
     * @return  Azure
     */
    public function setEnvironment(\Scalr_Environment $environment = null)
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * Gets an Scalr Environment object which is associated with the Azure client instance
     *
     * @return  \Scalr_Environment  Returns Scalr Environment object
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Set proxy configuration to connect to AWS services
     *
     * @param string  $host
     * @param integer $port
     * @param string  $user
     * @param string  $pass
     * @param int     $type      Allowed values 4 - SOCKS4, 5 - SOCKS5, 0 - HTTP
     * @param int     $authType  Allowed authtypes: 1 - Basic, Digest - 2, GSSNeg - 4, NTLM - 8, any - -1
     */
    public function setProxy($host, $port = 3128, $user = null, $pass = null, $type = 0, $authType = 1)
    {
        $this->proxyHost = $host;
        $this->proxyPort = $port;
        $this->proxyUser = $user;
        $this->proxyPass = $pass;
        $this->proxyType = $type;
        $this->authType  = $authType;
    }

    /**
     * Gets proxy configuration
     *
     * @return array|bool
     */
    public function getProxy()
    {
        return ($this->proxyHost) ? [
            'host'      => $this->proxyHost,
            'port'      => $this->proxyPort,
            'user'      => $this->proxyUser,
            'pass'      => $this->proxyPass,
            'type'      => $this->proxyType,
            'authtype'  => $this->authType
        ] : false;
    }

    /**
     * Setter for Access Token.
     *
     * @param string $token      Access Token body
     * @param string $expireDate Access Token expire date (unixtime)
     * @param string $error      optional      Access Token error description
     */
    public function setAccessToken($token, $expireDate, $error = null)
    {
        // TODO add token data type
        $this->accessToken = new \stdClass();
        $this->accessToken->token = $token;
        $this->accessToken->expireDate = $expireDate;
        $this->accessToken->error = $error;
    }

    /**
     * List of all available services.
     *
     * @return array Service names
     */
    public function getAvailableServices()
    {
        return [
            self::SERVICE_RESOURCE_MANAGER  => self::SERVICE_RESOURCE_MANAGER,
            self::SERVICE_COMPUTE           => self::SERVICE_COMPUTE,
            self::SERVICE_NETWORK           => self::SERVICE_NETWORK,
            self::SERVICE_STORAGE           => self::SERVICE_STORAGE
        ];
    }

    /**
     * Magic getter.
     *
     * @param string $name
     * @return AbstractService
     * @throws AzureException
     */
    public function __get($name)
    {
        $services = $this->getAvailableServices();

        if (isset($services[$name])) {
            if (!isset($this->services[$name])) {
                $servicePath = __NAMESPACE__ . '\\Azure\\Services\\' . ucfirst($services[$name]) . 'Service';
                $this->services[$name] = new $servicePath ($this);
            }

            return $this->services[$name];
        }

        throw new AzureException(sprintf('Invalid Service name "%s" for Azure', $name));
    }

    /**
     * Gets Client
     *
     * @return  QueryClient Returns QueryClient
     */
    public function getClient()
    {
        if ($this->client === null) {
            $this->client = new QueryClient($this);
        }
        
        return $this->client;
    }

    /**
     * Validate tenant name.
     *
     * @return bool Return true, if tenantName is valid, else return false
     */
    public function validateTenantName()
    {
        $path = '/' . $this->tenantName . '/.well-known/openid-configuration';

        $request = $this->getClient()->prepareRequest($path, 'GET', self::AUTH_API_VERSION, self::URL_LOGIN_WINDOWS);
        $response = $this->getClient()->call($request);

        if (200 == $response->getResponseCode()) {
            $this->tenantIsValid = true;
        } else {
            $this->tenantIsValid = false;
        }

        return $this->tenantIsValid;
    }

    /**
     * Retrieve location where user must go to receive redirect-callback
     * from Azure with authorization code.
     *
     * @return string URL for access code request
     * @throws AzureException
     */
    public function getAuthorizationCodeLocation()
    {
        if ($this->validateTenantName()) {
            $path = '/' . $this->tenantName . '/oauth2/authorize';

            $queryData = [
                'response_type' => 'code',
                'client_id'     => $this->appClientId,
                'resource'      => self::URL_MICROSOFT_ONLINE . '/'
            ];

            $request = $this->getClient()->prepareRequest($path, 'GET', self::AUTH_API_VERSION, self::URL_LOGIN_WINDOWS, $queryData);
            $response = $this->getClient()->call($request);
            $location = null;

            if (302 == $response->getResponseCode()) {
                $location = $response->getHeader('Location');
            }

            return $location;
        } else {
            throw new AzureException('Tenant name is not valid. Failed process authorization.');
        }
    }

    /**
     * Receive token.
     *
     * @param string    $grantType Grant type
     * @param string    $resource Resource
     * @param string    $refreshToken optional Refresh Token value
     * @param string    $authorizationCode
     * @return object   Object with next properties : token, expireDate and error.
     *                  Token is invalid, when error is not null
     * @throws AzureException
     * @throws RestApiException
     * @internal param string $authorisationCode optional Authorisation Code
     */
    protected function getToken($grantType, $resource, $refreshToken = null, $authorizationCode = null)
    {
        $token = new \stdClass();
        $token->token = '';
        $token->expireDate = '';
        $token->error = '';

        $path = '/' . $this->tenantName . '/oauth2/token';

        $postFields = [
            'client_id'     => $this->appClientId,
            'client_secret' => $this->appSecretKey,
            'grant_type'    => $grantType,
            'resource'      => $resource,
        ];

        if ($refreshToken) {
            $postFields['refresh_token'] = $refreshToken;
        }

        if ($authorizationCode) {
            $postFields['code'] = $authorizationCode;
        }

        $request = $this->getClient()->prepareRequest($path, 'POST', self::AUTH_API_VERSION, self::URL_LOGIN_WINDOWS);
        $request->append($postFields);

        $response = $this->getClient()->call($request);

        $responseObj = json_decode($response->getContent());

        if (empty($responseObj)) {
            throw new AzureException('Bad server response format for token-request.', 400);
        }

        if (isset($responseObj->error)) {
            throw new  RestApiException($responseObj->error_description);
        } else {
            if (isset($responseObj->access_token)) {
                $token->token = $responseObj->access_token;
            } else if (isset($responseObj->token)) {
                $token->token = $responseObj->token;
            }

            $token->expireDate = $responseObj->expires_on;

            if (isset($responseObj->refresh_token)) {
                $this->refreshToken = new \stdClass();
                $this->refreshToken->token = $responseObj->refresh_token;
                $this->refreshToken->expireDate = time() + 14 * 24 * 60 * 60;

                $this->environment->keychain(\SERVER_PLATFORMS::AZURE)->properties->saveSettings([
                    CloudCredentialsProperty::AZURE_REFRESH_TOKEN => $this->refreshToken->token,
                    CloudCredentialsProperty::AZURE_REFRESH_TOKEN_EXPIRE => $this->refreshToken->expireDate
                ]);
            }
        }

        return $token;
    }

    /**
     * Receive access token from server using Authorisation Code.
     *
     * @param string $authorizationCode Code, received from server
     *
     * @return object stdClass with next properties : token, expireDate and error.
     *                  $accesToken is invalid, when error is not null
     */
    public function getAccessTokenByAuthCode($authorizationCode)
    {
        $grantType = 'authorization_code';
        $resource = self::URL_CORE_WINDOWS . '/';
        $this->accessToken = $this->getToken($grantType, $resource, null, $authorizationCode);

        $this->environment->keychain(\SERVER_PLATFORMS::AZURE)->properties->saveSettings([
            CloudCredentialsProperty::AZURE_ACCESS_TOKEN => $this->accessToken->token,
            CloudCredentialsProperty::AZURE_ACCESS_TOKEN_EXPIRE => $this->accessToken->expireDate
        ]);

        return $this->accessToken;
    }

    /**
     * Gets Access Token.
     *
     * @return object stdClass with next properties : token, expireDate and error.
     *                  $accesToken is invalid, when error is not null
     */
    public function getAccessToken()
    {
        if (!$this->accessToken) {
            $this->accessToken = $this->loadToken(self::TOKEN_TYPE_ACCESS);
        }

        if (!$this->accessToken || $this->accessToken->expireDate <= time()) {
            $grantType = 'refresh_token';
            $resource = self::URL_CORE_WINDOWS . '/';

            $token = $this->getRefreshToken();
            $this->accessToken = $this->getToken($grantType, $resource, $token->token);

            $this->environment->keychain(\SERVER_PLATFORMS::AZURE)->properties->saveSettings([
                CloudCredentialsProperty::AZURE_ACCESS_TOKEN => $this->accessToken->token,
                CloudCredentialsProperty::AZURE_ACCESS_TOKEN_EXPIRE => $this->accessToken->expireDate
            ]);
        }

        return $this->accessToken;
    }

    /**
     * Receive short-time-live client token to use in GraphClient.
     *
     * @param string $resource
     * @return object stdClass with next properties : token, expireDate and error.
     *                  $clientToken is invalid, when error is not null
     */
    public function getClientToken($resource = self::URL_GRAPH_WINDOWS)
    {
        $this->clientToken = $this->loadToken(self::TOKEN_TYPE_CLIENT);

        if (!$this->clientToken || $this->clientToken->expireDate <= (time() + 120)) {
            $grantType = 'client_credentials';
            $this->clientToken = $this->getToken($grantType, $resource . '/');

            $this->environment->keychain(\SERVER_PLATFORMS::AZURE)->properties->saveSettings([
                CloudCredentialsProperty::AZURE_CLIENT_TOKEN => $this->clientToken->token,
                CloudCredentialsProperty::AZURE_CLIENT_TOKEN_EXPIRE => $this->clientToken->expireDate
            ]);
        }

        return $this->clientToken;
    }

    /**
     * Getter for Refresh Token.
     *
     * @return object stdClass with next properties : token, expireDate and error.
     * @throws AzureException
     */
    public function getRefreshToken()
    {
        if (!$this->refreshToken) {
            $this->refreshToken = $this->loadToken(self::TOKEN_TYPE_REFRESH);
        }

        if (!$this->refreshToken || $this->refreshToken->expireDate <= time()) {
            throw new AzureException('Refresh token is expired or not exists! Please process authorisation flow to continue working.');
        }

        $this->environment->keychain(\SERVER_PLATFORMS::AZURE)->properties->saveSettings([
            CloudCredentialsProperty::AZURE_REFRESH_TOKEN => $this->refreshToken->token,
            CloudCredentialsProperty::AZURE_REFRESH_TOKEN_EXPIRE => $this->refreshToken->expireDate
        ]);

        return $this->refreshToken;
    }

    /**
     * Load token from storage (DB in future).
     *
     * @param string $type 'access' or 'refresh'
     *
     * @return object stdClass with next properties : token, expireDate and error.
     */
    private function loadToken($type)
    {
        $azureConstantsPrefix = '\Scalr\Model\Entity\CloudCredentialsProperty::AZURE';
        $ccProps = $this->environment->keychain(\SERVER_PLATFORMS::AZURE)->properties;

        $type = strtoupper($type);
        $token = new \stdClass();
        $token->token = $ccProps[constant("{$azureConstantsPrefix}_{$type}_TOKEN")];
        $token->expireDate = $ccProps[constant("{$azureConstantsPrefix}_{$type}_TOKEN_EXPIRE")];
        $token->error = '';

        return !empty($token->token) ? $token : null;
    }

    /**
     * Receive from server Application Object ID for app with specified App Client ID
     * using Client Token (AuthClient::getClientToken()).
     *
     * @return string Application Object ID
     * @throws AzureException
     */
    public function getAppObjectId()
    {
        $path = '/' . $this->tenantName . '/servicePrincipals';

        $headers = ['Authorization' => 'Bearer ' . $this->getClientToken()->token];
        
        $query = ['$filter' => "appId eq '{$this->appClientId}'"];
        
        $request = $this->getClient()->prepareRequest($path, 'GET', self::GRAPH_API_VERSION, self::URL_GRAPH_WINDOWS, $query, [], $headers);
        $response = $this->getClient()->call($request);

        $responseObj = json_decode($response->getContent());

        if (empty($responseObj)) {
            throw new AzureException('Bad server response format for app-object-id-request.', 1);
        }

        if (!$response->hasError()) {
            foreach ($responseObj->value as $value) {
                if (isset($value->appId) && $value->appId == $this->appClientId) {
                    $this->appObjectId = $value->objectId;
                    break;
                }
            }
        }

        return $this->appObjectId;
    }

    /**
     * Get list of all user subscriptions.
     *
     * @return SubscriptionList List of subscription objects
     */
    public function getSubscriptionsList()
    {
        $result = null;

        $path = '/subscriptions';

        $step = $this->environment->keychain(\SERVER_PLATFORMS::AZURE)->properties[CloudCredentialsProperty::AZURE_AUTH_STEP];

        if ($step == 3) {
            $token = $this->getClientToken(Azure::URL_MANAGEMENT_WINDOWS);
        }

        if (empty($token) && $step != 3) {
            $token = $this->getAccessToken();
        }

        $headers = ['Authorization' => 'Bearer ' . $token->token];
        $request = $this->getClient()->prepareRequest($path, 'GET', self::SUBSCRIPTION_API_VERSION, self::URL_MANAGEMENT_WINDOWS, [], [], $headers);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new SubscriptionList();

            foreach ($resultArray as $array) {
                $result->append(SubscriptionData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Get Contributor Role Id for specified subscription.
     *
     * @param string $subscriptionId subscription::subscriptionId value of one of user's subscriptions
     *
     * @return string Contributor Role Id
     */
    public function getContributorRoleId($subscriptionId)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers/Microsoft.Authorization/roleDefinitions/';

        $headers = ['Authorization' => 'Bearer ' . $this->getAccessToken()->token];
        $request = $this->getClient()->prepareRequest($path, 'GET', self::RESOURCE_API_VERSION, self::URL_MANAGEMENT_WINDOWS, [], [], $headers);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $responseObj = $response->getResult();

            foreach ($responseObj as $value) {
                $dataObject = RoleDefinitionData::initArray($value);
                /* @var $dataObject RoleDefinitionData */
                if ($dataObject->properties->roleName == 'Contributor') {
                    $result = $dataObject->name;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Assign Contributor Role Id to specified app.
     *
     * @param string $subscriptionId    subscription::subscriptionId value of one of user's subscriptions
     * @param string $roleAssignmentId  New role assignment id
     * @param string $appObjectId       Application Object Id
     * @param string $contributorRoleId Contributor Role Id
     *
     * @return RoleAssignmentData Server response body (JSON)
     */
    public function assignContributorRoleToApp($subscriptionId, $roleAssignmentId, $appObjectId, $contributorRoleId)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers/Microsoft.Authorization/roleAssignments/' . $roleAssignmentId;
        $headers = ['Authorization' => 'Bearer ' . $this->getAccessToken()->token];

        $requestData = [
            'properties' => [
                'roleDefinitionId'  => "/subscriptions/" . $subscriptionId . "/providers/Microsoft.Authorization/roleDefinitions/" . $contributorRoleId,
                'principalId'       => $appObjectId,
                'scope'             => '/subscriptions/' . $subscriptionId
            ]
        ];

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', self::RESOURCE_API_VERSION,
            Azure::URL_MANAGEMENT_WINDOWS, [], $requestData, $headers
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = RoleAssignmentData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Delete role assignment.
     *
     * @param string $subscriptionId    subscription::subscriptionId value of one of user's subscriptions
     * @param string $roleAssignmentId  Role assignment id
     *
     * @return bool
     */
    public function deleteRoleAssignment($subscriptionId, $roleAssignmentId)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers/Microsoft.Authorization/roleAssignments/' . $roleAssignmentId;
        $headers = ['Authorization' => 'Bearer ' . $this->getAccessToken()->token];

        $request = $this->getClient()->prepareRequest($path, 'DELETE', self::RESOURCE_API_VERSION, Azure::URL_MANAGEMENT_WINDOWS, [], [], $headers);

        $response = $this->getClient()->call($request);

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Get Role Assignment info in specified subscription.
     *
     * @param string $subscriptionId    subscription::subscriptionId value of one of user's subscriptions
     * @param string $roleAssignmentId  Role assignment id
     *
     * @return RoleAssignmentData Server response body (JSON)
     */
    public function getRoleAssignmentInfo($subscriptionId, $roleAssignmentId)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers/Microsoft.Authorization/roleAssignments/' . $roleAssignmentId;
        $headers = ['Authorization' => 'Bearer ' . $this->getAccessToken()->token];

        $request = $this->getClient()->prepareRequest($path, 'GET', self::RESOURCE_API_VERSION, Azure::URL_MANAGEMENT_WINDOWS, [], [], $headers);

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = RoleAssignmentData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Get Role Assignment list in specified subscription.
     *
     * @param string $subscriptionId    subscription::subscriptionId value of one of user's subscriptions
     * @param string $appObjectId       Application Object Id
     * @param string $contributorRoleId Contributor Role Id
     *
     * @return RoleAssignmentData Server response body (JSON)
     */
    public function getContributorRoleAssignmentInfo($subscriptionId, $appObjectId, $contributorRoleId)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers/Microsoft.Authorization/roleAssignments';
        $headers = ['Authorization' => 'Bearer ' . $this->getAccessToken()->token];

        $request = $this->getClient()->prepareRequest($path, 'GET', self::RESOURCE_API_VERSION, Azure::URL_MANAGEMENT_WINDOWS, [], [], $headers);

        $response = $this->getClient()->call($request);

        $contributorRoleDefinitionId = "/subscriptions/" . $subscriptionId . "/providers/Microsoft.Authorization/roleDefinitions/" . $contributorRoleId;

        if (!$response->hasError()) {
            $responseObject = $response->getResult();

            foreach ($responseObject as $roleAssignment) {
                $roleAssignment = RoleAssignmentData::initArray($roleAssignment);
                /* @var $roleAssignment RoleAssignmentData */
                if (!empty($roleAssignment->properties->roleDefinitionId) && !empty($roleAssignment->properties->principalId)
                    && $roleAssignment->properties->roleDefinitionId == $contributorRoleDefinitionId
                    && $roleAssignment->properties->principalId == $appObjectId
                ) {
                    $result = $roleAssignment;
                    break;
                }
            }
        }


        return $result;
    }

    /**
     * List all of the available geo-locations where user can create resource groups and resources.
     *
     * @param string $namespace Resource provider
     * @return GeoLocationData Object described on https://msdn.microsoft.com/en-us/library/azure/dn790540.aspx
     * @throws AzureException
     */
    public function getLocationsList($namespace = GeoLocationData::RESOURCE_PROVIDER_COMPUTE)
    {
        $result = null;

        $path = '/providers/' . $namespace;
        $request = $this->getClient()->prepareRequest($path, 'GET', self::RESOURCE_API_VERSION);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = GeoLocationData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * List information about all of the available resource providers and whether they are registered with the subscription.
     *
     * @param string $subscriptionId    subscription::subscriptionId value of one of user's subscriptions
     * @return ProviderList
     * @throws AzureException
     */
    public function getProvidersList($subscriptionId)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers';

        $request = $this->getClient()->prepareRequest($path, 'GET', self::SUBSCRIPTION_API_VERSION, self::URL_MANAGEMENT_WINDOWS);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new ProviderList();

            foreach ($resultArray as $array) {
                $result->append(ProviderData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Register a subscription with a resource provider.
     *
     * @param string $subscriptionId subscription::subscriptionId value of one of user's subscriptions
     * @param string $resourceProvider The namespace of the resource provider with which you want to register your subscription
     * @return ProviderData
     * @throws AzureException
     */
    public function registerSubscription($subscriptionId, $resourceProvider)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers/' . $resourceProvider . '/register';

        $request = $this->getClient()->prepareRequest($path, 'POST', self::SUBSCRIPTION_API_VERSION, self::URL_MANAGEMENT_WINDOWS);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = ProviderData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Unregister a subscription from a resource provider.
     *
     * @param string $subscriptionId subscription::subscriptionId value of one of user's subscriptions
     * @param string $resourceProvider The namespace of the resource provider with which you want to unregister from your subscription
     * @return ProviderData
     * @throws AzureException
     */
    public function unregisterSubscription($subscriptionId, $resourceProvider)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/providers/' . $resourceProvider . '/unregister';

        $request = $this->getClient()->prepareRequest($path, 'POST', self::SUBSCRIPTION_API_VERSION, self::URL_MANAGEMENT_WINDOWS);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = ProviderData::initArray($response->getResult());
        }

        return $result;
    }

}
