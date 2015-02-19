<?php
namespace Scalr\Service\OpenStack;

use Scalr\Service\OpenStack\Type\Marker;
use Scalr\Service\OpenStack\Exception\RestClientException;
use Scalr\Service\OpenStack\Client\AuthToken;
use Scalr\Service\OpenStack\Client\RestClient;
use Scalr\Service\OpenStack\Client\ClientInterface;
use Scalr\Service\OpenStack\Exception\OpenStackException;
use Scalr\Service\OpenStack\Services\Network\Type\NetworkExtension;
use Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSecurityGroupRule;
use Scalr\Service\OpenStack\Type\DefaultPaginationList;
use GlobIterator;
use FilesystemIterator;

/**
 * OpenStack api library
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    04.12.2012
 *
 * @property \Scalr\Service\OpenStack\Services\ServersService $servers
 *           A Next Generation Cloud Servers service interface.
 *
 * @property \Scalr\Service\OpenStack\Services\VolumeService $volume
 *           A Cloud Block Storage (Volume) service interface.
 *
 * @property \Scalr\Service\OpenStack\Services\NetworkService $network
 *           A Quantum API (Network) service interface.
 *           
 *
 * @property \Scalr\Service\OpenStack\Services\SwiftService $swift
 *           Object Storage (SWIFT) service interface.
 */
class OpenStack
{

    const SERVICE_COMPUTE = 'compute';

    const SERVICE_VOLUME = 'volume';

    const SERVICE_NETWORK = 'network';

    const SERVICE_METERING = 'metering';

    const SERVICE_IMAGE = 'image';

    const SERVICE_EC2 = 'ec2';

    const SERVICE_OBJECT_STORE = 'object-store';

    const SERVICE_IDENTITY = 'identity';

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
     * OpenStack config
     *
     * @var  OpenStackConfig
     */
    private $config;

    /**
     * Misc. cache
     *
     * @var array
     */
    private $cache;

    /**
     * Constructor
     *
     * @param OpenStackConfig $config OpenStack configuration object
     */
    public function __construct(OpenStackConfig $config)
    {
        $this->config = $config;
        $this->cache = array();
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
            /* @var $item \SplFileInfo */
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
        throw new OpenStackException(sprintf('Invalid Service name "%s" for the OpenStack', $name));
    }

    /**
     * Gets the OpenStack config
     *
     * @return  OpenStackConfig Returns OpenStack config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Gets Client
     *
     * @return  RestClient Returns RestClient
     */
    public function getClient()
    {
        if ($this->client === null) {
            $this->client = new RestClient($this->getConfig());
        }
        return $this->client;
    }

    /**
     * Enables or disables debug mode
     *
     * In debug mode all requests and responses will be printed to stdout.
     *
     * @param   bool    $debug optional True to enable debug mode
     * @return  OpenStack
     */
    public function setDebug($debug = true)
    {
        $this->getClient()->setDebug($debug);

        return $this;
    }

    /**
     * Performs an authentication request
     *
     * @return  object Returns auth token
     * @throws  RestClientException
     */
    public function auth()
    {
        return $this->getClient()->auth();
    }

    /**
     * List tenants action
     *
     * @param   Marker $marker  Marker Data.
     * @return  array  Return tenants list
     */
    public function listTenants(Marker $marker = null)
    {
        $result = null;
        if ($marker !== null) {
            $options = $marker->getQueryData();
        } else {
            $options = array();
        }
        $response = $this->getClient()->call($this->config->getIdentityEndpoint(), '/tenants', $options);
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->tenants;
        }
        return $result;
    }

    /**
     * Gets the list of available zones for the current endpoint
     *
     * @return  array Zones list looks like array(stdClass1, stdClass2, ...)
     */
    public function listZones()
    {
        $cfg = $this->getConfig();
        $client = $this->getClient();
        if (!($cfg->getAuthToken() instanceof AuthToken)) {
            $client->auth();
        }
        $ret = array();
        foreach ($cfg->getAuthToken()->getZones() as $regionName) {
            $obj = new \stdClass();
            $obj->name = $regionName;
            $ret[] = $obj;
            unset($obj);
        }
        return $ret;
    }

    /**
     * Gets the list of allowed services for this tenant
     *
     * @return  array Returns the list of allowed services for this tenant
     */
    public function listServices()
    {
        if (!isset($this->cache['services'])) {
            $cfg = $this->getConfig();
            $client = $this->getClient();
            if (!($cfg->getAuthToken() instanceof AuthToken)) {
                $client->auth();
            }
            $region = $cfg->getRegion();
            foreach ($cfg->getAuthToken()->getRegionEndpoints() as $service => $info) {
                if ($info[$region])
                    $this->cache['services'][$service] = $service;
            }
        }
        return array_values($this->cache['services']);
    }

    /**
     * Checks whether specified service does exist in the retrieved endpoints for this user.
     *
     * @param   string     $servicename The name of the service to check
     * @param   string     $ns          optional The namespace
     * @return  boolean    Returns true if specified service does exist for this user.
     */
    public function hasService($serviceName, $ns = null)
    {
        if (!isset($this->cache['services'])) {
            $this->listServices();
        }

        return array_key_exists((isset($ns) ? $ns . ':' : '') . $serviceName, $this->cache['services']);
    }


    /**
     * Decamelizes a string
     *
     * @param   string    $str A string "FooName"
     * @return  string    Returns decamelized string "foo_name"
     */
    public static function decamelize($str)
    {
        return strtolower(preg_replace_callback('/([a-z])([A-Z]+)/', function ($m) {
            return $m[1] . '_' . $m[2];
        }, $str));
    }

    /**
     * Gets list of security groups
     *
     * @param   string $serverId optional
     *          The ID of the security group to view
     *
     * @param   ListSecurityGroupsFilter|array $filter optional
     *          The filter options. Filter doesn't apply to detailed info
     *
     * @param   array $fields optional
     *          The list of the fields to show
     *
     * @return  DefaultPaginationList|object  Returns the list of the security groups
     */
    public function listSecurityGroups($serverId = null, $filter = null, array $fields = null)
    {
        if ($this->hasNetworkSecurityGroupExtension()) {
            $securityGroup = $this->network->securityGroups->list($serverId, $filter, $fields);
        } else {
            $securityGroup = $this->servers->securityGroups->list($serverId);

        }

        return $securityGroup;
    }

    /**
     * Create Security Group action
     *
     * @param   string     $name        A security group name.
     * @param   string     $description A description.
     * @return  object     Returns created secrurity group.
     */
    public function createSecurityGroup($name, $description)
    {
        if ($this->hasNetworkSecurityGroupExtension()) {
            $securityGroup = $this->network->securityGroups->create($name, $description);
        } else {
            $securityGroup = $this->servers->securityGroups->create($name, $description);
        }

        return $securityGroup;
    }

    /**
     * Removes a specific security group
     *
     * @param   int      $securityGroupId  Security group unique identifier.
     * @return  bool     Returns true on success or throws an exception
     */
    public function deleteSecurityGroup($securityGroupId)
    {
        if ($this->hasNetworkSecurityGroupExtension()) {
            $result = $this->network->securityGroups->delete($securityGroupId);
        } else {
            $result = $this->servers->securityGroups->delete($securityGroupId);
        }

        return $result;
    }

    /**
     * Gets the list of the security group rules (GET /security-group-rules/[rules-security-groups-id] )
     *
     * Lists a summary of all OpenStack Networking security group rules that the specified tenant can access.
     *
     * @param   string $id optional
     *          The ID of the security group rule to view
     *
     * @param   ListSecurityGroupRulesFilter|array $filter optional
     *          The filter options. Filter doesn't apply to detailed info
     *
     * @param   array $fields optional
     *          The list of the fields to show
     *
     * @return  DefaultPaginationList|object|null Returns the list of the security groups, specified security group or null
     */
    public function listSecurityGroupRules($id = null, $filter = null, array $fields = null)
    {
        if ($this->hasNetworkSecurityGroupExtension()) {
            return $this->network->securityGroups->listRules($id, $filter, $fields);
        }

        return null;
    }

    /**
     * Creates Security Group Rule (POST /security-group-rules)
     *
     * @param   CreateSecurityGroupRule|object|array $request The request object
     * @return  object                               Returns Security Group Rule object
     */
    public function createSecurityGroupRule($request)
    {
        if (!is_array($request)) {
            $request = get_object_vars($request);
        }
        if ($this->hasNetworkSecurityGroupExtension()) {
            $requestObject = CreateSecurityGroupRule::initArray($request);
            $result = $this->network->securityGroups->addRule($requestObject);
        } else {
            $requestData = array(
                'parent_group_id'  => $request['security_group_id'],
                'ip_protocol'      => !empty($request['protocol']) ? $request['protocol'] : null,
                'from_port'        => $request['port_range_min'],
                'to_port'          => $request['port_range_max'],
                'cidr'             => !empty($request['remote_ip_prefix']) ? $request['remote_ip_prefix'] : null,
                'group_id'         => !empty($request['remote_group_id']) ? $request['remote_group_id'] : null
            );
            $result = $this->servers->securityGroups->addRule($requestData);
        }

        return $result;
    }

    /**
     * Deletes Security Group Rule (DELETE /security-group-rules/​rules-security-groups-id})
     *
     * @param   string  $securityGroupRuleId The UUID of the security group rule to delete
     * @return  bool    Returns true on success or throws an exception
     */
    public function deleteSecurityGroupRule($securityGroupRuleId)
    {
        if ($this->hasNetworkSecurityGroupExtension()) {
            $result = $this->network->securityGroups->deleteRule($securityGroupRuleId);
        } else {
            $result = $this->servers->securityGroups->deleteRule($securityGroupRuleId);
        }

        return $result;
    }

    /**
     * Checks whether openstack has network service as well as security group extension
     *
     * @return bool Returns true if network service exists and has security group extension
     */
    public function hasNetworkSecurityGroupExtension()
    {
        return $this->hasService(OpenStack::SERVICE_NETWORK) &&
               $this->network->isExtensionSupported(NetworkExtension::securityGroup());
    }

}
