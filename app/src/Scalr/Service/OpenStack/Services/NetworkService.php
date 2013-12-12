<?php
namespace Scalr\Service\OpenStack\Services;

use Scalr\Service\OpenStack\Services\Network\Type\CreateRouter;
use Scalr\Service\OpenStack\Services\Network\Type\ListRoutersFilter;
use Scalr\Service\OpenStack\Services\Network\Type\ListPortsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\CreatePort;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSubnet;
use Scalr\Service\OpenStack\Services\Network\Type\ListSubnetsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\ListNetworksFilter;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\Exception\OpenStackException;
use Scalr\Service\OpenStack\Client\RestClientResponse;
use Scalr\Service\OpenStack\Services\Network\V2\NetworkApi;

/**
 * OpenStack Network (OpenStack Quantum API)
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    07.05.2013
 *
 * @property \Scalr\Service\OpenStack\Services\Network\Handler\NetworksHandler $networks
 *           Gets a Networks service interface handler.
 *
 * @property \Scalr\Service\OpenStack\Services\Network\Handler\SubnetsHandler $subnets
 *           Gets a Subnets service interface handler.
 *
 * @property \Scalr\Service\OpenStack\Services\Network\Handler\PortsHandler $ports
 *           Gets a Ports service interface handler.
 *
 * @property \Scalr\Service\OpenStack\Services\Network\Handler\RoutersHandler $routers
 *           Gets a Routers service interface handler.
 *
 * @property \Scalr\Service\OpenStack\Services\Network\Handler\FloatingIpsHandler $floatingIps
 *           Gets a FloatingIps service interface handler.
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\V2\NetworkApi getApiHandler()
 *           getApiHandler()
 *           Gets an Network API handler for the specific version
 */
class NetworkService extends AbstractService implements ServiceInterface
{

    const VERSION_V2 = 'V2';

    //If you change this version, please be aware of getEndpointUrl() method of this class
    const VERSION_DEFAULT = self::VERSION_V2;

    /**
     * Miscellaneous cache
     * @var array
     */
    private $cache;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return OpenStack::SERVICE_NETWORK;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::getVersion()
     */
    public function getVersion()
    {
        return self::VERSION_DEFAULT;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.AbstractService::getEndpointUrl()
     */
    public function getEndpointUrl()
    {
        //Endpoint url in the service catalog does not include version
        $cfg = $this->getOpenStack()->getConfig();
        return $cfg->getAuthToken() === null ?
            $cfg->getIdentityEndpoint() :
            rtrim(parent::getEndpointUrl(), '/') . '/v2.0';
    }

    /**
     * List Networks action (GET /networks[/network-id])
     *
     * Lists a summary of all networks defined in Quantum that are accessible
     * to the tenant who submits the request.
     *
     * @param   string                   $networkId optional The ID of the network to show detailed info
     * @param   ListNetworksFilter|array $filter    optional The query filter.
     * @return  array|object Returns the list of the networks or one network
     * @throws  RestClientException
     */
    public function listNetworks($networkId = null, $filter = null)
    {
        if (!empty($filter) && !($filter instanceof ListNetworksFilter)) {
            $filter = ListNetworksFilter::initArray($filter);
        }
        return $this->getApiHandler()->listNetworks($networkId, $filter);
    }

    /**
     * ListSubnets action (GET /subnets[/subnet-id])
     *
     * Lists all subnets that are accessible to the tenant who submits the
     * request.
     *
     * @param   string                  $subnetId optional The ID of the subnet to show detailed info
     * @param   ListSubnetsFilter|array $filter   optional The filter.
     * @return  array|object Returns the list of the subnets or one subnet
     * @throws  RestClientException
     */
    public function listSubnets($subnetId = null, $filter = null)
    {
        if (!empty($filter) && !($filter instanceof ListSubnetsFilter)) {
            $filter = ListSubnetsFilter::initArray($filter);
        }
        return $this->getApiHandler()->listSubnets($subnetId, $filter);
    }

    /**
     * ListPorts action (GET /ports[/port-id])
     *
     * Lists all ports to which the tenant has access.
     *
     * @param   string                $portId optional The ID of the port to show detailed info
     * @param   ListPortsFilter|array $filter The filter options
     * @return  array|object Returns the list of the ports or the information about one port
     * @throws  RestClientException
     */
    public function listPorts($portId = null, $filter = null)
    {
        if (!empty($filter) && !($filter instanceof ListPortsFilter)) {
            $filter = ListPortsFilter::initArray($filter);
        }
        return $this->getApiHandler()->listPorts($portId, $filter);
    }

    /**
     * Create Network action (POST /networks)
     *
     * Creates a new Quantum network.
     *
     * @param   string     $name         optional A string specifying a symbolic name for the network,
     *                                   which is not required to be unique
     * @param   bool       $adminStateUp optional The administrative status of the network
     * @param   bool       $shared       optional Whether this network should be shared across all
     *                                   tenants or not. Note that the default policy setting restrict
     *                                   usage of this attribute to administrative users only
     * @param   string     $tenantId     optional The tenant which will own the network. Only administrative
     *                                   users can set the tenant identifier. This cannot be changed using
     *                                   authorization policies
     * @return  object Returns detailed information for the created network
     * @throws  RestClientException
     */
    public function createNetwork($name = null, $adminStateUp = null, $shared = null, $tenantId = null)
    {
        return $this->getApiHandler()->createNetwork($name, $adminStateUp, $shared, $tenantId);
    }

    /**
     * Update Network action (PUT /networks/network-id)
     *
     * Updates the specified network.
     * Either name or admin_state_up must be provided for this action.
     *
     * @param   string     $networkId    The ID of the network to update.
     * @param   string     $name         optional A string specifying a symbolic name for the network,
     *                                   which is not required to be unique
     * @param   bool       $adminStateUp optional The administrative status of the network
     * @return  object Returns detailed information for the updated network
     * @throws  RestClientException
     * @throws  \BadFunctionCallException
     */
    public function updateNetwork($networkId, $name = null, $adminStateUp = null)
    {
        return $this->getApiHandler()->updateNetwork($networkId, $name, $adminStateUp);
    }

    /**
     * Delete Network action (DELETE /networks/network-id)
     *
     * This operation deletes a Quantum network and its associated subnets provided that no
     * port is currently configured on the network.
     *
     * If ports are still configured on the network that you want to delete, a 409 Network In Use
     * error is returned.
     *
     * @param   string     $networkId    The ID of the network to update.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteNetwork($networkId)
    {
        return $this->getApiHandler()->deleteNetwork($networkId);
    }

    /**
     * Creates Subnet
     *
     * This operation creates a new subnet on the specified network. The network ID,
     * network_id, is required. You must also specify the cidr attribute for the subnet.
     *
     * The remaining attributes are optional.
     * By default, Quantum creates IP v4 subnets. To create an IP v6 subnet, you must specify the
     * value 6 for the ip_version attribute in the request body. Quantum does not try to derive
     * the correct IP version from the provided CIDR. If the parameter for the gateway address,
     * gateway_ip, is not specified, Quantum allocates an address from the cidr for the gateway
     * for the subnet.
     *
     * To specify a subnet without a gateway, specify the value null for the gateway_ip
     * attribute in the request body. If allocation pools attribute, allocation_pools, is not
     * specified, Quantum automatically allocates pools for covering all IP addresses in the CIDR,
     * excluding the address reserved for the subnet gateway. Otherwise, you can explicitly
     * specify allocation pools as shown in the following example.
     *
     * When allocation_pools and gateway_ip are both specified, it is up to the user
     * ensuring the gateway ip does not overlap with the specified allocation pools; otherwise a
     * 409 Conflict error will be returned.
     *
     * @param   CreateSubnet|array $request Create subnet request object
     * @return  object       Returns subnet object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function createSubnet($request)
    {
        if (!($request instanceof CreateSubnet)) {
            $request = CreateSubnet::initArray($request);
        }
        return $this->getApiHandler()->createSubnet($request);
    }

    /**
     * Update Subnet action (PUT /subnets/subnet-id)
     *
     * This operation updates the specified subnet. Some attributes, such as IP version
     * (ip_version), CIDR (cidr), and IP allocation pools (allocation_pools) cannot be
     * updated. Attempting to update these attributes results in a 400 Bad Request error.
     *
     * @param   string       $subnetId The Id of the subnet
     * @param   array|object $options  Raw options object (It will be json_encoded and passed as is.)
     * @return  object       Returns subnet object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function updateSubnet($subnetId, $options)
    {
        return $this->getApiHandler()->updateSubnet($subnetId, $options);
    }

    /**
     * Delete Subnet action (DELETE /subnets/subnet-id)
     *
     * This operation removes a subnet from a Quantum network. The operation fails if IP
     * addresses from the subnet that you want to delete are still allocated.
     *
     * @param   string     $subnetId    The ID of the subnet to remove.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteSubnet($subnetId)
    {
        return $this->getApiHandler()->deleteSubnet($subnetId);
    }

    /**
     * Create Port action (POST /ports)
     *
     * This operation creates a new Quantum port. The network where the port is created must
     * be specified in the network_id attribute in the request body. You can also specify the
     * following optional attributes:
     *
     * • A symbolic name for the port
     *
     * • MAC address. If an invalid address is specified a 400 Bad Request error will be
     * returned, whereas a 409 Conflict error will be returned if the specified MAC address
     * is already in use.
     *
     * When the MAC address is not specified, Quantum will try to allocate one for the port
     * being created. If there is a failure while generating the address, a 503 Service
     * Unavailable error will be returned.
     *
     * • Administrative state. Set to true for up, and false for down.
     *
     * • Fixed IPs
     *
     * • If you specify just a subnet ID, Quantum allocates an available IP from that subnet to
     * the port.
     *
     * • If you specify both a subnet ID and an IP address, Quantum tries to allocate the
     * specified address to the port.
     *
     * • Host routes for the port, in addition to the host routes defined for the subnets that the
     * port is associated with.
     *
     * @param   CreatePort|array $request Create port request object
     * @return  object           Returns port object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function createPort($request)
    {
        if (!($request instanceof CreatePort)) {
            $request = CreatePort::initArray($request);
        }
        return $this->getApiHandler()->createPort($request);
    }

    /**
     * Update port action (PUT /ports/port-id)
     *
     * You can use this operation to update information for a port, such as its symbolic name and
     * associated IPs. When you update IPs for a port, the previously associated IPs are removed,
     * returned to the respective subnets allocation pools, and replaced by the IPs specified in the
     * body for the update request. Therefore, this operation replaces the fixed_ip attribute
     * when it is specified in the request body. If the new IP addresses are not valid, for example,
     * they are already in use, the operation fails and the existing IP addresses are not removed
     * from the port.
     *
     * @param   string       $portId  The ID of the port
     * @param   array|object $options The list of the options to change
     * @return  object       Returns port object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function updatePort($portId, $options)
    {
        return $this->getApiHandler()->updatePort($portId, $options);
    }

    /**
     * Delete Port action (DELETE /ports/port-id)
     *
     * @param   string     $portId    The ID of the port to remove.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deletePort($portId)
    {
        return $this->getApiHandler()->deletePort($portId);
    }

    /**
     * Gets the routers list
     *
     * This operation returns a list of routers to which the tenant has access.
     * Default policy settings return only those routers that are owned by the tenant who submits the request,
     * unless the request is submitted by an user with administrative rights.
     * Users can control which attributes should be returned by using the fields query parameter.
     * Additionally, results can be filtered by using query string parameters.
     *
     * @param   string                  $routerId     optional The ID of the router to show detailed info
     * @param   ListRoutersFilter|array $filter       optional The filter options
     * @param   array                   $fields       optional The list of the fields to show
     * @return  array|object Returns the list of the routers or the information about one router
     * @throws  RestClientException
     */
    public function listRouters($routerId = null, $filter = null, array $fields = null)
    {
        if (!empty($filter) && !($filter instanceof ListRoutersFilter)) {
            $filter = ListRoutersFilter::initArray($filter);
        }
        return $this->getApiHandler()->listRouters($routerId, $filter, $fields);
    }

    /**
     * Create Router action (POST /routers)
     *
     * This operation creates a new logical router.
     * When it is created, a logical router does not have any internal interface.
     * In other words, it is not associated to any subnet.
     * The user can optionally specify an external gateway for a router at create time;
     * a router's external gateway must be plugged into an external network,
     * that is to say a network for which the extended field router:external is set to true.
     *
     * @param   CreateRouter|array $request Create router request object
     * @return  object             Returns router object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function createRouter($request)
    {
        if (!($request instanceof CreateRouter)) {
            $request = CreateRouter::initArray($request);
        }
        return $this->getApiHandler()->createRouter($request);
    }

    /**
     * Delete Router action (DELETE /routers/router-id)
     *
     * This operation removes a logical router;
     * The operation will fail if the router still has some internal interfaces.
     * Users must remove all router interfaces before deleting the router,
     * by removing all internal interfaces through remove router interface operation.
     *
     * @param   string     $routerId    The ID of the router to remove.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteRouter($routerId)
    {
        return $this->getApiHandler()->deleteRouter($routerId);
    }

    /**
     * Update Router action (PUT /routers/router-id)
     *
     * This operation updates a logical router. Beyond the name and the administrative state,
     * the only parameter which can be updated with this operation is the external gateway.
     *
     * Please note that this operation does not allow to update router interfaces.
     * To this aim, the add router interface and remove router interface should be used.
     *
     * @param   string       $routerId The Id of the router
     * @param   array|object $options  Raw options object (It will be json_encoded and passed as is.)
     * @return  object       Returns router object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function updateRouter($routerId, $options)
    {
        return $this->getApiHandler()->updateRouter($routerId, $options);
    }

    /**
     * Add Router Interface action (PUT /routers/router-id/add_router_interface)
     *
     * This operation attaches a subnet to an internal router interface.
     * Either a subnet identifier or a port identifier must be passed in the request body.
     * If both are specified, a 400 Bad Request error is returned.
     *
     * When the subnet_id attribute is specified in the request body,
     * the subnet's gateway ip address is used to create the router interface;
     * otherwise, if port_id is specified, the IP address associated with the port is used
     * for creating the router interface.
     *
     * It is worth remarking that a 400 Bad Request error is returned if several IP addresses are associated with the specified port,
     * or if no IP address is associated with the port;
     * also a 409 Conflict is returned if the port is already used.
     *
     * @param   string     $routerId The ID of the router
     * @param   string     $subnetId optional The identifier of the subnet
     * @param   string     $portId   optional The identifier of the port
     * @throws  \InvalidArgumentException
     * @throws  RestClientException
     * @return  object     Returns both port and subnet identifiers as object's properties
     */
    public function addRouterInterface($routerId, $subnetId = null, $portId = null)
    {
        return $this->getApiHandler()->addRouterInterface($routerId, $subnetId, $portId);
    }

    /**
     * Remove Router Interface action (PUT /routers/router-id/remove_router_interface)
     *
     * This operation removes an internal router interface, thus detaching a subnet from the router.
     * Either a subnet identifier (subnet_id) or a port identifier (port_id) should be passed in the request body;
     * this will be used to identify the router interface to remove.
     * If both are specified, the subnet identifier must correspond to the one of the first ip address on the port specified by the port identifier;
     * Otherwise a 409 Conflict error will be returned.
     *
     * The response will contain information about the affected router and interface.
     *
     * A 404 Not Found error will be returned either if the router or the subnet/port do not exist or are not visible to the user.
     * As a consequence of this operation, the port connecting the router with the subnet is removed from the subnet's network.
     *
     * @param   string     $routerId The ID of the router
     * @param   string     $subnetId optional The identifier of the subnet
     * @param   string     $portId   optional The identifier of the port
     * @throws  \InvalidArgumentException
     * @throws  RestClientException
     * @return  object     Returns raw response as object
     */
    public function removeRouterInterface($routerId, $subnetId = null, $portId = null)
    {
        return $this->getApiHandler()->removeRouterInterface($routerId, $subnetId, $portId);
    }

    /**
     * List Floating Ips action
     *
     * @return  array Returns the list floating IP addresses
     * @throws  RestClientException
     */
    public function listFloatingIps()
    {
        return $this->getApiHandler()->listFloatingIps();
    }

    /**
     * Gets floating Ip details
     *
     * @param   int   $floatingIpAddressId  The unique identifier associated with allocated floating IP address.
     * @return  object Returns details of the floating IP address.
     * @throws  RestClientException
     */
    public function getFloatingIp($floatingIpAddressId)
    {
        return $this->getApiHandler()->getFloatingIp($floatingIpAddressId);
    }

    /**
     * This operation creates a floating IP.
     *
     * Creates a new floating IP,
     * and configures its association with an internal port,
     * if the relevant information are specified.
     *
     * @param   string   $floatingNetworkId The identifier of the external network
     * @param   string   $portId            optional Internal port
     * @return  object   Returns allocated floating ip details
     * @throws  RestClientException
     */
    public function createFloatingIp($floatingNetworkId, $portId = null)
    {
        return $this->getApiHandler()->createFloatingIp($floatingNetworkId, $portId);
    }

    /**
     * Removes the floating IP address.
     *
     * @param   int $floatingIpAddressId Floating IP address ID
     * @return  bool Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteFloatingIp($floatingIpAddressId)
    {
        return $this->getApiHandler()->deleteFloatingIp($floatingIpAddressId);
    }

    /**
     * This operation updates a floating IP.
     *
     * This operation has the name of setting, unsetting, or updating the
     * assocation between a floating ip and a Quantum port.
     * The association process is exactly the same as the one discussed
     * for the create floating IP operation.
     *
     * @param   string   $floatingIpId      The identifier of the floating IP
     * @param   string   $portId            optional Internal port
     * @return  object   Returns allocated floating ip details
     * @throws  RestClientException
     */
    public function updateFloatingIp($floatingIpAddressId, $portId = null)
    {
        return $this->getApiHandler()->updateFloatingIp($floatingIpAddressId, $portId);
    }
}