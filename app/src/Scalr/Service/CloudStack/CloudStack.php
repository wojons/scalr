<?php
namespace Scalr\Service\CloudStack;

use DateTime;
use DateTimeZone;
use FilesystemIterator;
use GlobIterator;
use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\Client\QueryClient;
use Scalr\Service\CloudStack\DataType\AssociateIpAddressData;
use Scalr\Service\CloudStack\DataType\CapabilityData;
use Scalr\Service\CloudStack\DataType\CloudIdentifierData;
use Scalr\Service\CloudStack\DataType\DiskOfferingData;
use Scalr\Service\CloudStack\DataType\DiskOfferingList;
use Scalr\Service\CloudStack\DataType\EventResponseData;
use Scalr\Service\CloudStack\DataType\EventResponseList;
use Scalr\Service\CloudStack\DataType\HypervisorsData;
use Scalr\Service\CloudStack\DataType\HypervisorsList;
use Scalr\Service\CloudStack\DataType\IpAddressResponseData;
use Scalr\Service\CloudStack\DataType\IpAddressResponseList;
use Scalr\Service\CloudStack\DataType\JobResultData;
use Scalr\Service\CloudStack\DataType\JobResultList;
use Scalr\Service\CloudStack\DataType\ListAsyncJobsData;
use Scalr\Service\CloudStack\DataType\ListDiskOfferingsData;
use Scalr\Service\CloudStack\DataType\ListEventsData;
use Scalr\Service\CloudStack\DataType\ListIpAddressesData;
use Scalr\Service\CloudStack\DataType\ListOsTypesData;
use Scalr\Service\CloudStack\DataType\ListResourceLimitsData;
use Scalr\Service\CloudStack\DataType\ListServiceOfferingsData;
use Scalr\Service\CloudStack\DataType\LoginResponseData;
use Scalr\Service\CloudStack\DataType\LogoutResponseData;
use Scalr\Service\CloudStack\DataType\OsCategoryData;
use Scalr\Service\CloudStack\DataType\OsCategoryList;
use Scalr\Service\CloudStack\DataType\OsTypeData;
use Scalr\Service\CloudStack\DataType\OsTypeList;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResourceLimitData;
use Scalr\Service\CloudStack\DataType\ResourceLimitList;
use Scalr\Service\CloudStack\DataType\ServiceOfferingData;
use Scalr\Service\CloudStack\DataType\ServiceOfferingList;
use Scalr\Service\CloudStack\DataType\AvailableProductsList;
use Scalr\Service\CloudStack\DataType\AvailableProductsData;
use Scalr\Service\CloudStack\Exception\CloudStackException;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\VirtualTrait;
use Scalr\Service\CloudStack\DataType\ListAccountsData;
use Scalr\Service\CloudStack\DataType\AccountList;
use Scalr\Service\CloudStack\DataType\AccountData;
use Scalr\Service\CloudStack\DataType\UserList;
use Scalr\Service\CloudStack\DataType\UserData;

/**
 * CloudStack api library
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @property \Scalr\Service\CloudStack\Services\BalancerService $balancer
 *
 * @property \Scalr\Service\CloudStack\Services\InstanceService $instance
 *
 * @property \Scalr\Service\CloudStack\Services\NetworkService $network
 *
 * @property \Scalr\Service\CloudStack\Services\TemplateService $template
 *
 * @property \Scalr\Service\CloudStack\Services\IsoService $iso
 *
 * @property \Scalr\Service\CloudStack\Services\VolumeService $volume
 *
 * @property \Scalr\Service\CloudStack\Services\SnapshotService $snapshot
 *
 * @property \Scalr\Service\CloudStack\Services\VpnService $vpn
 *
 * @property \Scalr\Service\CloudStack\Services\FirewallService $firewall
 *
 * @property \Scalr\Service\CloudStack\Services\SshKeyPairService $sshKeyPair
 *
 * @property \Scalr\Service\CloudStack\Services\SecurityGroupService $securityGroup
 *
 * @property \Scalr\Service\CloudStack\Services\VmGroupService $vmGroup
 *
 * @property \Scalr\Service\CloudStack\Services\ZoneService $zone
 *
 */
class CloudStack
{
    use TagsTrait, UpdateTrait, VirtualTrait;

    const SERVICE_BALANCER = 'balancer';

    const SERVICE_INSTANCE = 'instance';

    const SERVICE_NETWORK = 'network';

    const SERVICE_TEMPLATE = 'template';

    const SERVICE_ISO = 'iso';

    const SERVICE_VOLUME = 'volume';

    const SERVICE_SNAPSHOT = 'snapshot';

    const SERVICE_VPN = 'vpn';

    const SERVICE_FIREWALL = 'firewall';

    const SERVICE_SSH_KEY_PAIR = 'sshKeyPair';

    const SERVICE_SECURITY_GROUP = 'securityGroup';

    const SERVICE_VM_GROUP = 'vmGroup';

    const SERVICE_ZONE = 'zone';

    /**
     * Available services
     * @var array
     */
    private static $availableServices;

    /**
     * Service instances cache
     * @var array
     */
    private $serviceInstances;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $platformName;

    /**
     * Constructor
     *
     * @param string    $endpoint   Api url
     * @param string    $apiKey     Api key
     * @param string    $secretKey  Api secret key
     * @param string    $platform   Platform name (cloudstack, idcf)
     */
    public function __construct($endpoint, $apiKey, $secretKey, $platform = 'cloudstack')
    {
        $this->setEndpoint($endpoint)->setApiKey($apiKey)->setSecretKey($secretKey)->setPlatform($platform);
    }

    /**
     * Gets the CloudStack api key
     *
     * @return  string Returns api key
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Gets the CloudStack secret key
     *
     * @return  string Returns secret key
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * Gets the CloudStack api url
     *
     * @return  string Returns api url
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * Gets the CloudStack platform
     *
     * @return  string Returns platform
     */
    public function getPlatform()
    {
        return $this->platformName;
    }

    /**
     * Sets the CloudStack api key
     *
     * @param string $apiKey
     * @return  CloudStack
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Sets the CloudStack platform
     *
     * @param string $platform
     * @return  CloudStack
     */
    public function setPlatform($platform)
    {
        $this->platformName = $platform;
        return $this;
    }

    /**
     * Sets the CloudStack secret key
     *
     * @param string $secretKey
     * @return  CloudStack
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
        return $this;
    }

    /**
     * Sets the CloudStack api url
     *
     * @param string $endpoint
     * @return  CloudStack
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = substr($endpoint, -1) == "/" ? substr($endpoint, 0, -1) : $endpoint;
        return $this;
    }

    /**
     * Escapes string to pass it over http request
     *
     * @param   string   $str
     * @return  string
     */
    protected function escape($str)
    {
        return rawurlencode($str);
    }

    /**
     * Gets a list of available services
     *
     * @return  array Returns the list of available services looks like array(serviceName => className)
     */
    public static function getAvailableServices()
    {

        if (!isset(self::$availableServices)) {
            $ns = __NAMESPACE__ . '\\Services';
            $iterator = new GlobIterator(__DIR__ . '/Services/*Service.php', FilesystemIterator::KEY_AS_FILENAME);

            foreach ($iterator as $item) {
                $class = $ns . '\\' . substr($iterator->key(), 0, -4);
                if (get_parent_class($class) == $ns . '\\AbstractService') {
                    self::$availableServices[$class::getName()] = $class;
                }
            }
        }
        return self::$availableServices;
    }

    /**
     * It's used to retrieve service interface instances as public properties
     */
    public function __get($name)
    {
        $available = self::getAvailableServices();
        if (isset($available[$name])) {
            if (!isset($this->serviceInstances[$name])) {
                $this->serviceInstances[$name] = new $available[$name] ($this);
            }
            return $this->serviceInstances[$name];
        }
        throw new CloudStackException(sprintf('Invalid Service name "%s" for the CloudStack', $name));
    }

    /**
     * Gets Client
     *
     * @return  QueryClient Returns QueryClient
     */
    public function getClient()
    {
        if ($this->client === null) {
            $this->client = new QueryClient(
                    $this->getEndpoint(),
                    $this->getApiKey(),
                    $this->getSecretKey(),
                    $this,
                    $this->getPlatform()
                );
        }
        return $this->client;
    }

    /**
     * Enables or disables debug mode
     *
     * In debug mode all requests and responses will be printed to stdout.
     *
     * @param   bool    $debug optional True to enable debug mode
     * @return  CloudStack
     */
    public function setDebug($debug = true)
    {
        $this->getClient()->setDebug($debug);

        return $this;
    }

    /**
     * Retrieves a cloud identifier.
     *
     * @param string $userId the user ID for the cloud identifier
     * @return CloudIdentifierData
     */
    public function getCloudIdentifier($userId)
    {
        $result = null;

        $response = $this->getClient()->call(
            'getCloudIdentifier',
                array(
                    'userid' => $this->escape($userId)
                )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadCloudIdentifierData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Logs a user into the CloudStack.
     * Successful login attempt will generate a JSESSIONID cookie value that can be passed in subsequent Query command calls until the "logout" command has been issued or the session has expired.
     *
     * @param string $userName Username
     * @param string $password Password
     * @param string $domain path of the domain that the user belongs to. Example: domain=/com/cloud/internal.
     *                       If no domain is passed in, the ROOT domain is assumed.
     * @return LoginResponseData
     */
    public function login($userName, $password, $domain = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'login',
                array(
                    'username' => $this->escape($userName),
                    'password' => $this->escape($password),
                    'domain' => $this->escape($domain)
                )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadLoginData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Logs out the user
     *
     * return LogoutResponseData
     */
    public function logout()
    {
        $result = null;

        $response = $this->getClient()->call('logout', array());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadLogoutData($resultObject);
            }
        }

        return $result;
    }

    /**
     * List hypervisors
     *
     * @param string $zoneId the zone id for listing hypervisors.
     * @param PaginationType $pagination Pagination
     * @return null|HypervisorsList
     * @throws Exception\RestClientException
     */
    public function listHypervisors($zoneId = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($zoneId !== null) {
            $args['zoneid'] = $zoneId;
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }

        $response = $this->getClient()->call('listHypervisors', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadHypervisorsList($resultObject->hypervisor);
            }
        }

        return $result;
    }

    /**
     * Lists capabilities
     *
     * @param PaginationType $pagination Pagination
     * @return null|CapabilityData
     * @throws Exception\RestClientException
     */
    public function listCapabilities(PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($pagination !== null) {
            $args = $pagination->toArray();
        }

        $response = $this->getClient()->call('listCapabilities', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (isset($resultObject->capability)) {
                $result = $this->_loadCapabilityData($resultObject->capability);
            }
        }

        return $result;
    }

    /**
     * Lists resource limits.
     *
     * @param ListResourceLimitsData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return ResourceLimitList|null
     */
    public function listResourceLimits($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListResourceLimitsData)) {
                $requestData = ListResourceLimitsData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listResourceLimits', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadResourceLimitList($resultObject->resourcelimit);
            }
        }

        return $result;
    }

    /**
     * Acquires and associates a public IP to an account.
     *
     * @param AssociateIpAddressData|array $requestData Request data object
     * @return IpAddressResponseData
     */
    public function associateIpAddress($requestData = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof AssociateIpAddressData)) {
                $requestData = AssociateIpAddressData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }

        $response = $this->getClient()->call('associateIpAddress', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadIpAddressData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Disassociates an ip address from the account.
     *
     * @param string $id the id of the public ip address to disassociate
     * @return ResponseDeleteData
     */
    public function disassociateIpAddress($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'disassociateIpAddress',
             array(
                 'id' => $this->escape($id)
             )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadUpdateData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Lists all public ip addresses
     *
     * @param ListIpAddressesData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return IpAddressResponseList|null
     */
    public function listPublicIpAddresses($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListIpAddressesData)) {
                $requestData = ListIpAddressesData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listPublicIpAddresses', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadIpAddressList($resultObject->publicipaddress);
            }
        }

        return $result;
    }

    /**
     * Retrieves the current status of asynchronous job.
     *
     * @param string $jobId the ID of the asychronous job
     * @return JobResultData
     */
    public function queryAsyncJobResult($jobId)
    {
        $result = null;

        $response = $this->getClient()->call(
            'queryAsyncJobResult',
             array(
                 'jobid' => $this->escape($jobId)
             )
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadJobResultData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Lists all pending asynchronous jobs for the account.
     *
     * @param ListAsyncJobsData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return JobResultList|null
     */
    public function listAsyncJobs($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListAsyncJobsData)) {
                $requestData = ListAsyncJobsData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listAsyncJobs', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadJobResultList($resultObject->asyncjobs);
            }
        }

        return $result;
    }

    /**
     * A command to list events.
     *
     * @param ListEventsData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return EventResponseList|null
     */
    public function listEvents($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListEventsData)) {
                $requestData = ListEventsData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listEvents', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadEventList($resultObject->event);
            }
        }

        return $result;
    }

    /**
     * Lists all supported OS types for this cloud.
     *
     * @param ListOsTypesData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return OsTypeList|null
     */
    public function listOsTypes($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListOsTypesData)) {
                $requestData = ListOsTypesData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listOsTypes', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadOsTypeList($resultObject->ostype);
            }
        }

        return $result;
    }

    /**
     * Lists all supported OS categories for this cloud.
     *
     * @param string $id         List Os category by id
     * @param string $name       List os category by name
     * @param string $keyword    List by keyword
     * @param PaginationType $pagination    Pagination
     * @return OsCategoryList|null
     */
    public function listOsCategories($id = null, $name = null, $keyword = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array(
            'id'        => $this->escape($id),
            'name'      => $this->escape($name),
            'keyword'   => $this->escape($keyword)
        );

        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listOsCategories', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadOsCategoryList($resultObject->oscategory);
            }
        }

        return $result;
    }

    /**
     * Lists all available service offerings.
     *
     * @param ListServiceOfferingsData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return ServiceOfferingList|null
     */
    public function listServiceOfferings($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListServiceOfferingsData)) {
                $requestData = ListServiceOfferingsData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listServiceOfferings', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadServiceOfferingList($resultObject->serviceoffering);
            }
        }

        return $result;
    }

    /**
     * Lists all available disk offerings.
     *
     * @param ListDiskOfferingsData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return DiskOfferingList|null
     */
    public function listDiskOfferings($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListDiskOfferingsData)) {
                $requestData = ListDiskOfferingsData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listDiskOfferings', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadDiskOfferingList($resultObject->diskoffering);
            }
        }

        return $result;
    }

    /**
     * Lists accounts and provides detailed account information for listed accounts
     *
     * @param ListAccountsData|array $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return AccountList|null
     */
    public function listAccounts($requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            if (!($requestData instanceof ListAccountsData)) {
                $requestData = ListAccountsData::initArray($requestData);
            }
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listAccounts', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadAccountList($resultObject->account);
            }
        }

        return $result;
    }

    /**
     * It is a command used in checking the list of products provided as those of server and by
     * selecting one of resulted lists, users can check combination of templateid, serviceofferingid,
     * diskofferingid and zoneid which can be created with VM
     *
     * @return AvailableProductsList|null
     */
    public function listAvailableProductTypes()
    {
        $result = null;

        $response = $this->getClient()->call('listAvailableProductTypes', array());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();

            if (!empty($resultObject) && property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadProductTypesList($resultObject->producttypes);
            }
        }

        return $result;
    }

    /**
     * Loads AvailableProductsList from json object
     *
     * @param   object $productsList
     * @return  AvailableProductsList Returns AvailableProductsList
     */
    public function _loadProductTypesList($productsList)
    {
        $result = new AvailableProductsList();

        if (!empty($productsList)) {
            foreach ($productsList as $product) {
                $item = $this->_loadProductTypesData($product);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads AvailableProductsData from json object
     *
     * @param   object $resultObject
     * @return  AvailableProductsData Returns AvailableProductsData
     */
    public function _loadProductTypesData($resultObject)
    {
        $item = new AvailableProductsData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if (is_object($resultObject->{$property})) {
                    trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                }
                else {
                    $item->{$property} = (string) $resultObject->{$property};
                }
            }
        }

        return $item;
    }

    /**
     * Loads CloudIdentifierData from json object
     *
     * @param   object $resultObject
     * @return  CloudIdentifierData Returns CloudIdentifierData
     */
    protected function _loadCloudIdentifierData($resultObject)
    {
        $item = new CloudIdentifierData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if (is_object($resultObject->{$property})) {
                    trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                }
                else {
                    $item->{$property} = (string) $resultObject->{$property};
                }
            }
        }

        return $item;
    }

    /**
     * Loads LoginResponseData from json object
     *
     * @param   object $resultObject
     * @return  LoginResponseData Returns LoginResponseData
     */
    protected function _loadLoginData($resultObject)
    {
        $item = new LoginResponseData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if (is_object($resultObject->{$property})) {
                    trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                }
                else {
                    $item->{$property} = (string) $resultObject->{$property};
                }
            }
        }

        return $item;
    }

    /**
     * Loads LogoutResponseData from json object
     *
     * @param   object $resultObject
     * @return  LogoutResponseData Returns LogoutResponseData
     */
    protected function _loadLogoutData($resultObject)
    {
        $item = new LogoutResponseData();
        if (property_exists($resultObject, "description")) {
            $item->description = (string) $resultObject->description;
        }

        return $item;
    }

    /**
     * Loads HypervisorsList from json object
     *
     * @param   object $hypervisorsList
     * @return  HypervisorsList Returns HypervisorsList
     */
    protected function _loadHypervisorsList($hypervisorsList)
    {
        $result = new HypervisorsList();

        if (!empty($hypervisorsList)) {
            foreach ($hypervisorsList as $hypervisor) {
                $item = $this->_loadHypervisorsData($hypervisor);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads HypervisorsData from json object
     *
     * @param   object $resultObject
     * @return  HypervisorsData Returns HypervisorsData
     */
    protected function _loadHypervisorsData($resultObject)
    {
        $item = new HypervisorsData();
        if (property_exists($resultObject, "name")) {
            $item->name = (string) $resultObject->name;
        }

        return $item;
    }

    /**
     * Loads CapabilityData from json object
     *
     * @param   object $resultObject
     * @return  CapabilityData Returns CapabilityData
     */
    protected function _loadCapabilityData($resultObject)
    {
        $item = new CapabilityData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if (is_object($resultObject->{$property})) {
                    trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                } else {
                    $item->{$property} = $resultObject->{$property};
                }
            }
        }

        return $item;
    }

    /**
     * Loads ResourceLimitList from json object
     *
     * @param   object $limitsList
     * @return  ResourceLimitList Returns ResourceLimitList
     */
    protected function _loadResourceLimitList($limitsList)
    {
        $result = new ResourceLimitList();

        if (!empty($limitsList)) {
            foreach ($limitsList as $limit) {
                $item = $this->_loadResourceLimitData($limit);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads ResourceLimitData from json object
     *
     * @param   object $resultObject
     * @return  ResourceLimitData Returns ResourceLimitData
     */
    protected function _loadResourceLimitData($resultObject)
    {
        $item = new ResourceLimitData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if (is_object($resultObject->{$property})) {
                    trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                } else {
                    $item->{$property} = (string) $resultObject->{$property};
                }
            }
        }

        return $item;
    }

    /**
     * Loads IpAddressResponseList from json object
     *
     * @param   object $addressList
     * @return  IpAddressResponseList Returns IpAddressResponseList
     */
    protected function _loadIpAddressList($addressList)
    {
        $result = new IpAddressResponseList();

        if (!empty($addressList)) {
            foreach ($addressList as $address) {
                $item = $this->_loadIpAddressData($address);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads IpAddressResponseData from json object
     *
     * @param   object $resultObject
     * @return  IpAddressResponseData Returns IpAddressResponseData
     */
    protected function _loadIpAddressData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new IpAddressResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('allocated' == $property) {
                        $item->{$property} = new DateTime((string)$resultObject->{$property}, new DateTimeZone('UTC'));
                    } else if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
            if (property_exists($resultObject, 'tags')) {
                $item->setTags($this->_loadTagsList($resultObject->tags));
            }
        }

        return $item;
    }

    /**
     * Loads JobResultList from json object
     *
     * @param   object $jobsList
     * @return  JobResultList Returns JobResultList
     */
    protected function _loadJobResultList($jobsList)
    {
        $result = new JobResultList();

        if (!empty($jobsList)) {
            foreach ($jobsList as $job) {
                $item = $this->_loadJobResultData($job);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads JobResultData from json object
     *
     * @param   object $resultObject
     * @return  JobResultData Returns JobResultData
     */
    protected function _loadJobResultData($resultObject)
    {
        $item = new JobResultData();
        $properties = get_object_vars($item);

        foreach($properties as $property => $value) {
            if (property_exists($resultObject, "$property")) {
                if ('created' == $property) {
                    $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                } else if (is_object($resultObject->{$property})) {
                    trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                    $item->{$property} = json_encode($resultObject->{$property});
                } else {
                    $item->{$property} = (string) $resultObject->{$property};
                }
            }
        }
        if (property_exists($resultObject, 'jobresult') && property_exists($resultObject->jobresult, 'virtualmachine')) {
            $item->setVirtualmachine($this->_loadVirtualMachineInstanceData($resultObject->jobresult->virtualmachine));
        }
        if (property_exists($resultObject, 'jobresult') && property_exists($resultObject->jobresult, 'errorcode') && property_exists($resultObject->jobresult, 'errortext')) {
            $item->errorcode = $resultObject->jobresult->errorcode;
            $item->errortext = $resultObject->jobresult->errortext;
        }
        return $item;
    }

    /**
     * Loads EventResponseList from json object
     *
     * @param   object $eventList
     * @return  EventResponseList Returns EventResponseList
     */
    protected function _loadEventList($eventList)
    {
        $result = new EventResponseList();

        if (!empty($eventList)) {
            foreach ($eventList as $event) {
                $item = $this->_loadEventData($event);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads EventResponseData from json object
     *
     * @param   object $resultObject
     * @return  EventResponseData Returns EventResponseData
     */
    protected function _loadEventData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new EventResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property) {
                        $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                    } else if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Loads OsTypeList from json object
     *
     * @param   object $typeList
     * @return  OsTypeList Returns OsTypeList
     */
    protected function _loadOsTypeList($typeList)
    {
        $result = new OsTypeList();

        if (!empty($typeList)) {
            foreach ($typeList as $type) {
                $item = $this->_loadOsTypeData($type);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads OsTypeData from json object
     *
     * @param   object $resultObject
     * @return  OsTypeData Returns OsTypeData
     */
    protected function _loadOsTypeData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new OsTypeData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Loads OsCategoryList from json object
     *
     * @param   object $categoryList
     * @return  OsCategoryList Returns OsCategoryList
     */
    protected function _loadOsCategoryList($categoryList)
    {
        $result = new OsCategoryList();

        if (!empty($categoryList)) {
            foreach ($categoryList as $category) {
                $item = $this->_loadOsCategoryData($category);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads OsCategoryData from json object
     *
     * @param   object $resultObject
     * @return  OsCategoryData Returns OsCategoryData
     */
    protected function _loadOsCategoryData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new OsCategoryData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Loads ServiceOfferingList from json object
     *
     * @param   object $serviceList
     * @return  ServiceOfferingList Returns ServiceOfferingList
     */
    protected function _loadServiceOfferingList($serviceList)
    {
        $result = new ServiceOfferingList();

        if (!empty($serviceList)) {
            foreach ($serviceList as $service) {
                $item = $this->_loadServiceOfferingData($service);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads ServiceOfferingData from json object
     *
     * @param   object $resultObject
     * @return  ServiceOfferingData Returns ServiceOfferingData
     */
    protected function _loadServiceOfferingData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new ServiceOfferingData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property) {
                        $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                    } else if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Loads DiskOfferingList from json object
     *
     * @param   object $serviceList
     * @return  DiskOfferingList Returns DiskOfferingList
     */
    protected function _loadDiskOfferingList($serviceList)
    {
        $result = new DiskOfferingList();

        if (!empty($serviceList)) {
            foreach ($serviceList as $service) {
                $item = $this->_loadDiskOfferingData($service);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads DiskOfferingData from json object
     *
     * @param   object $resultObject
     * @return  DiskOfferingData Returns DiskOfferingData
     */
    protected function _loadDiskOfferingData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new DiskOfferingData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if ('created' == $property) {
                        $item->created = new DateTime((string)$resultObject->created, new DateTimeZone('UTC'));
                    } else if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

    /**
     * Loads AccountList from json object
     *
     * @param   object $accountList
     * @return  AccountList Returns AccountList
     */
    protected function _loadAccountList($accountList)
    {
        $result = new AccountList();

        if (!empty($accountList)) {
            foreach ($accountList as $account) {
                $item = $this->_loadAccountData($account);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads AccountData from json object
     *
     * @param   object $resultObject
     * @return  AccountData Returns AccountData
     */
    protected function _loadAccountData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new AccountData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
            if (property_exists($resultObject, 'user')) {
                $item->setUser($this->_loadUserList($resultObject->user));
            }
        }

        return $item;
    }

    /**
     * Loads UserList from json object
     *
     * @param   object $userList
     * @return  UserList Returns UserList
     */
    protected function _loadUserList($userList)
    {
        $result = new UserList();

        if (!empty($userList)) {
            foreach ($userList as $user) {
                $item = $this->_loadUserData($user);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads UserData from json object
     *
     * @param   object $resultObject
     * @return  UserData Returns UserData
     */
    protected function _loadUserData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new UserData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        trigger_error('Cloudstack error. Unexpected stdObject class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    } else {
                        $item->{$property} = (string) $resultObject->{$property};
                    }
                }
            }
        }

        return $item;
    }

}