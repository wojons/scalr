<?php
namespace Scalr\Service\OpenStack\Services\Network\V2;

use Scalr\Service\OpenStack\Services\Network\Type\CreateRouter;
use Scalr\Service\OpenStack\Services\Network\Type\ListRoutersFilter;
use Scalr\Service\OpenStack\Services\Network\Type\ListPortsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\CreatePort;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSubnet;
use Scalr\Service\OpenStack\Services\Network\Type\ListSubnetsFilter;
use Scalr\Service\OpenStack\Type\BooleanType;
use Scalr\Service\OpenStack\Services\Network\Type\ListNetworksFilter;
use Scalr\Service\OpenStack\Exception\RestClientException;
use Scalr\Service\OpenStack\Client\ClientInterface;
use Scalr\Service\OpenStack\Services\NetworkService;
use Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter;
use Scalr\Service\OpenStack\Services\Network\Type\CreateLbPool;
use Scalr\Service\OpenStack\Services\Network\Type\CreateLbMember;
use Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\CreateLbHealthMonitor;
use Scalr\Service\OpenStack\Services\Network\Type\CreateLbVip;
use Scalr\Service\OpenStack\Type\DefaultPaginationList;
use Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter;
use Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupRulesFilter;
use Scalr\Service\OpenStack\Services\Network\Type\CreateSecurityGroupRule;

/**
 * OpenStack Quantum API v2.0 (May 7, 2013)
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    07.05.2013
 */
class NetworkApi
{

    /**
     * @var NetworkService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   NetworkService $network
     */
    public function __construct(NetworkService $network)
    {
        $this->service = $network;
    }

    /**
     * Gets HTTP Client
     *
     * @return  ClientInterface Returns HTTP Client
     */
    public function getClient()
    {
        return $this->service->getOpenStack()->getClient();
    }

    /**
     * Escapes string
     *
     * @param   string    $string A string needs to be escapted
     * @return  string    Returns url encoded string
     */
    public function escape($string)
    {
        return rawurlencode($string);
    }

    /**
     * List Extensions action
     *
     * This operation returns a response body. In the response body, each extension is identified
     * by two unique identifiers, a namespace and an alias. Additionally an extension contains
     * documentation links in various formats
     *
     * @return  array      Returns list of available extensions
     * @throws  RestClientException
     */
    public function listExtensions()
    {
        $result = null;
        $response = $this->getClient()->call($this->service, '/extensions');
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->extensions;
        }
        return $result;
    }

    /**
     * List Networks action (GET /networks[/network-id])
     *
     * Lists a summary of all networks defined in Quantum that are accessible
     * to the tenant who submits the request.
     *
     * @param   string             $networkId optional The ID of the network to show detailed info
     * @param   ListNetworksFilter $filter    optional The query filter.
     * @return  array|object Returns the list of the networks or one network
     * @throws  RestClientException
     */
    public function listNetworks($networkId = null, ListNetworksFilter $filter = null)
    {
        $result = null;
        $detailed = ($networkId !== null ? sprintf("/%s", $this->escape($networkId)) : '');
        $response = $this->getClient()->call(
            $this->service,
            '/networks' . $detailed . ($filter !== null ? '?' . $filter->getQueryString() : ''),
            null, 'GET'
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'networks', $result->networks,
                    (isset($result->networks_links) ? $result->networks_links : null)
                );
            } else {
                $result = $result->network;
            }
        }
        return $result;
    }

    /**
     * ListSubnets action (GET /subnets[/subnet-id])
     *
     * Lists all subnets that are accessible to the tenant who submits the
     * request.
     *
     * @param   string            $subnetId optional The ID of the subnet to show detailed info
     * @param   ListSubnetsFilter $filter   optional The filter.
     * @return  DefaultPaginationList|object Returns the list of the subnets or one subnet
     * @throws  RestClientException
     */
    public function listSubnets($subnetId = null, ListSubnetsFilter $filter = null)
    {
        $result = null;
        $detailed = ($subnetId !== null ? sprintf("/%s", $this->escape($subnetId)) : '');
        $response = $this->getClient()->call(
            $this->service,
            '/subnets' . $detailed . ($filter !== null ? '?' . $filter->getQueryString() : '')
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'subnets', $result->subnets,
                    (isset($result->subnets_links) ? $result->subnets_links : null)
                );
            } else {
                $result = $result->subnet;
            }
        }
        return $result;
    }

    /**
     * ListPorts action (GET /ports[/port-id])
     *
     * Lists all ports to which the tenant has access.
     *
     * @param   string          $portId optional The ID of the port to show detailed info
     * @param   ListPortsFilter $filter The filter options
     * @return  DefaultPaginationList|object Returns the list of the ports or the information about one port
     * @throws  RestClientException
     */
    public function listPorts($portId = null, ListPortsFilter $filter = null)
    {
        $result = null;
        $detailed = ($portId !== null ? sprintf("/%s", $this->escape($portId)) : '');
        $response = $this->getClient()->call(
            $this->service,
            '/ports' . $detailed . ($filter !== null ? '?' . $filter->getQueryString() : '')
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'ports', $result->ports,
                    (isset($result->ports_links) ? $result->ports_links : null)
                );
            } else {
                $result = $result->port;
            }
        }
        return $result;
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
        $result = null;

        $network = array();
        if ($name !== null) {
            $network['name'] = (string) $name;
        }
        if ($adminStateUp !== null) {
            $network['admin_state_up'] = (string)BooleanType::init($adminStateUp);
        }
        if ($shared !== null) {
            $network['shared'] = (string)BooleanType::init($shared);
        }
        if ($tenantId !== null) {
            $network['tenantId'] = (string) $tenantId;
        }

        $response = $this->getClient()->call(
            $this->service, '/networks',
            (!empty($network) ? array('network' => $network) : null), 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->network;
        }

        return $result;
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
        $result = null;

        $network = array();
        if ($name !== null) {
            $network['name'] = (string) $name;
        }
        if ($adminStateUp !== null) {
            $network['admin_state_up'] = (string)BooleanType::init($adminStateUp);
        }
        if (empty($network)) {
            throw new \BadFunctionCallException(sprintf(
                "Bad request. Either name or admin_state_up must have been provided for %s action."
            ), __FUNCTION__);
        }

        $response = $this->getClient()->call(
            $this->service, sprintf('/networks/%s', $this->escape($networkId)),
            array('_putData' => json_encode(array('network' => $network))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->network;
        }

        return $result;
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
     * @param   string     $networkId    The ID of the network to remove.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteNetwork($networkId)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/networks/%s', $this->escape($networkId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * Creates Subnet (POST /subnets)
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
     * @param   CreateSubnet $request Create subnet request object
     * @return  object       Returns subnet object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function createSubnet(CreateSubnet $request)
    {
        $result = null;

        $options = array('subnet' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        ));

        $response = $this->getClient()->call(
            $this->service, '/subnets',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->subnet;
        }

        return $result;
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
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/subnets/%s', $this->escape($subnetId)),
            array('_putData' => json_encode(array('subnet' => $options))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->subnet;
        }

        return $result;
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
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/subnets/%s', $this->escape($subnetId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
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
     * @param   CreatePort $request Create port request object
     * @return  object       Returns port object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function createPort(CreatePort $request)
    {
        $result = null;

        $options = array('port' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        ));

        $response = $this->getClient()->call(
            $this->service, '/ports',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->port;
        }

        return $result;
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
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/ports/%s', $this->escape($portId)),
            array('_putData' => json_encode(array('port' => $options))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->port;
        }

        return $result;
    }

    /**
     * Delete Port action (DELETE /ports/port-id)
     *
     * This operation removes a port from a Quantum network. If IP addresses are associated with
     * the port, they are returned to the respective subnets allocation pools.
     *
     * @param   string     $portId    The ID of the port to remove.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deletePort($portId)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/ports/%s', $this->escape($portId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
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
     * @param   string            $routerId     optional The ID of the router to show detailed info
     * @param   ListRoutersFilter $filter       optional The filter options. Filter doesn't apply to detailed info
     * @param   array             $fields       optional The list of the fields to show
     * @return  DefaultPaginationList|object Returns the list of the router or the information about one router
     * @throws  RestClientException
     */
    public function listRouters($routerId = null, ListRoutersFilter $filter = null, array $fields = null)
    {
        $result = null;
        $detailed = ($routerId !== null ? sprintf("/%s", $this->escape($routerId)) : '');
        if (!empty($fields)) {
            $acceptedFields = array('status', 'name', 'admin_state_up', 'id', 'tenant_id', 'external_gateway_info', 'admin_state_up');
            $fields = join('&fields=', array_map("rawurlencode", array_intersect(array_values($fields), $acceptedFields)));
        }
        $querystr = ($filter !== null && $detailed == '' ? $filter->getQueryString() : '')
                  . ($fields ? '&fields=' . $fields : '');
        $querystr = (!empty($querystr) ? '?' . ltrim($querystr, '&') : '');

        $response = $this->getClient()->call(
            $this->service,
            '/routers' . $detailed . $querystr
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'routers', $result->routers,
                    (isset($result->routers_links) ? $result->routers_links : null)
                );
            } else {
                $result = $result->router;
            }
        }

        return $result;
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
     * @param   CreateRouter $request Create router request object
     * @return  object       Returns router object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function createRouter(CreateRouter $request)
    {
        $result = null;

        $options = array('router' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        ));

        $response = $this->getClient()->call(
            $this->service, '/routers',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->router;
        }

        return $result;
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
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/routers/%s', $this->escape($routerId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
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
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/routers/%s', $this->escape($routerId)),
            array('_putData' => json_encode(array('router' => $options))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->router;
        }

        return $result;
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
        $result = null;
        $options = array();

        if (!empty($subnetId)) {
            $options['subnet_id'] = $this->escape($subnetId);
        }
        if (!empty($portId)) {
            $options['port_id'] = $this->escape($portId);
        }
        if (empty($options) || isset($options['port_id']) && isset($options['subnet_id'])) {
            throw new \InvalidArgumentException(sprintf(
                'Either a subnet identifier or a port identifier must be passed in the method.'
            ));
        }

        $response = $this->getClient()->call(
            $this->service, sprintf('/routers/%s/add_router_interface', $this->escape($routerId)),
            array('_putData' => json_encode($options)), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
        }

        return $result;
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
        $result = null;
        $options = array();

        if (!empty($subnetId)) {
            $options['subnet_id'] = $this->escape($subnetId);
        }
        if (!empty($portId)) {
            $options['port_id'] = $this->escape($portId);
        }
        if (empty($options)) {
            throw new \InvalidArgumentException(sprintf(
                'Either a subnet identifier or a port identifier must be passed in the method.'
            ));
        }

        $response = $this->getClient()->call(
            $this->service, sprintf('/routers/%s/remove_router_interface', $this->escape($routerId)),
            array('_putData' => json_encode($options)), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
        }

        return $result;
    }

    /**
     * List Floating Ips action
     *
     * Lists floating IP addresses associated with the tenant or account.
     *
     * @return  DefaultPaginationList Returns the list floating IP addresses associated with the tenant or account.
     * @throws  RestClientException
     */
    public function listFloatingIps()
    {
        $result = null;
        $response = $this->getClient()->call($this->service, '/floatingips');
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = new DefaultPaginationList(
                $this->service, 'floatingips', $result->floatingips,
                (isset($result->floatingips_links) ? $result->floatingips_links : null)
            );
        }
        return $result;
    }

    /**
     * Gets floating Ip details
     *
     * Lists details of the floating IP address associated with floating_IP_address_ID.
     *
     * @param   int    $floatingIpId     The unique identifier associated with allocated floating IP address.
     * @return  object Returns details of the floating IP address.
     * @throws  RestClientException
     */
    public function getFloatingIp($floatingIpId)
    {
        $result = null;
        $response = $this->getClient()->call(
            $this->service,
            sprintf('/floatingips/%s', $floatingIpId)
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->floatingip;
        }
        return $result;
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
    public function createFloatingIp($floatingNetworkId, $portId = null, $fixedIpAddress = null)
    {
        $result = null;
        $options = array();
        if (isset($floatingNetworkId)) {
            $options['floating_network_id'] = (string)$floatingNetworkId;
        }
        if (isset($portId)) {
            $options['port_id'] = (string)$portId;
        }

        if (isset($fixedIpAddress)) {
            $options['fixed_ip_address'] = (string)$fixedIpAddress;
        }

        $options = array('floatingip' => $options);

        $response = $this->getClient()->call(
            $this->service,
            '/floatingips',
            $options,
            'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->floatingip;
        }
        return $result;
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
    public function updateFloatingIp($floatingIpId, $portId = null)
    {
        $result = null;

        $options = new \stdClass();
        if (isset($portId)) {
            $options->port_id = (string)$portId;
        }

        $options = array('floatingip' => $options);

        $response = $this->getClient()->call(
            $this->service,
            sprintf('/floatingips/%s', $this->escape($floatingIpId)),
            array('_putData' => json_encode($options)),
            'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->floatingip;
        }

        return $result;
    }


    /**
     * The operation removes the floating IP
     *
     * If the floating IP being removed is associated with a Quantum port, the association is removed as well.
     *
     * @param   int $floatingIpId Floating IP address ID
     * @return  bool Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteFloatingIp($floatingIpId)
    {
        $result = false;
        $response = $this->getClient()->call(
            $this->service,
            sprintf('/floatingips/%s', $floatingIpId),
            null, 'DELETE'
        );
        if ($response->hasError() === false) {
            $result = true;
        }
        return $result;
    }

    /**
     * List VIPs action (GET /lb/vips[/vip-id])
     *
     * @param   string               $vipId  optional The ID of the VIP to show detailed info
     * @param   ListLbVipsFilter     $filter optional The query filter.
     * @return  DefaultPaginationList|object Returns the list of the VIPs or the requested VIP
     * @throws  RestClientException
     */
    public function listLbVips($vipId = null, ListLbVipsFilter $filter = null)
    {
        $result = null;
        $detailed = ($vipId !== null ? sprintf("/%s", $this->escape($vipId)) : '');
        $response = $this->getClient()->call(
            $this->service,
            '/lb/vips' . $detailed . ($filter !== null ? '?' . $filter->getQueryString() : '')
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'vips', $result->vips,
                    (isset($result->vips_links) ? $result->vips_links : null)
                );
            } else {
                $result = $result->vip;
            }
        }
        return $result;
    }

    /**
     * Creates LBaaS vip (POST /lb/vips)
     *
     * @param   CreateLbVip $request The request object
     * @return  object   Returns detailed info of the created VIP
     * @throws  RestClientException
     */
    public function createLbVip(CreateLbVip $request)
    {
        $result = null;

        if (empty($request->tenant_id)) {
            $request->tenant_id = $this->service->getTenantId();
        }

        $options = array('vip' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        ));

        $response = $this->getClient()->call(
            $this->service, '/lb/vips',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->vip;
        }

        return $result;
    }

    /**
     * Deletes LBaaS VIP (DELETE /lb/vips/vip-id)
     *
     * @param   string  $vipId    The ID of the VIP to delete
     * @return  boolean Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteLbVip($vipId)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/vips/%s', $this->escape($vipId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * Update LBaaS VIP (PUT /lb/vips/vip-id)
     *
     * @param   string       $vipId The Id of the LBaaS VIP to update
     * @param   array|object $options  Raw options object (It will be json_encoded and passed as is.)
     * @return  object       Returns LBaaS VIP object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function updateLbVip($vipId, $options)
    {
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/vips/%s', $this->escape($vipId)),
            array('_putData' => json_encode(array('vip' => $options))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->vip;
        }

        return $result;
    }

    /**
     * List Pools action (GET /lb/pools[/pool-id])
     *
     * @param   string                $poolId  optional The ID of the Pool to show detailed info
     * @param   ListLbPoolsFilter     $filter  optional The query filter.
     * @return  DefaultPaginationList|object Returns the list of the Pools or the requested pool
     * @throws  RestClientException
     */
    public function listLbPools($poolId = null, ListLbPoolsFilter $filter = null)
    {
        $result = null;
        $detailed = ($poolId !== null ? sprintf("/%s", $this->escape($poolId)) : '');
        $response = $this->getClient()->call(
            $this->service,
            '/lb/pools' . $detailed . ($filter !== null ? '?' . $filter->getQueryString() : '')
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'pools', $result->pools,
                    (isset($result->pools_links) ? $result->pools_links : null)
                );
            } else {
                $result = $result->pool;
            }
        }
        return $result;
    }

    /**
     * Creates LBaaS pool (POST /lb/pools)
     *
     * @param   CreateLbPool $request The request object
     * @return  object   Returns detailed info of the created pool
     * @throws  RestClientException
     */
    public function createLbPool(CreateLbPool $request)
    {
        $result = null;

        if (empty($request->tenant_id)) {
            $request->tenant_id = $this->service->getTenantId();
        }

        $options = array('pool' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        ));

        $response = $this->getClient()->call(
            $this->service, '/lb/pools',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->pool;
        }

        return $result;
    }

    /**
     * Deletes LBaaS pool (DELETE /lb/pools/pool-id)
     *
     * @param   string  $poolId The ID of the pool to delete
     * @return  boolean Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteLbPool($poolId)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/pools/%s', $this->escape($poolId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * Update LBaaS pool (PUT /lb/pools/pool-id)
     *
     * @param   string       $poolId The Id of the LBaaS pool to update
     * @param   array|object $options  Raw options object (It will be json_encoded and passed as is.)
     * @return  object       Returns LBaaS pool object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function updateLbPool($poolId, $options)
    {
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/pools/%s', $this->escape($poolId)),
            array('_putData' => json_encode(array('pool' => $options))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->pool;
        }

        return $result;
    }

    /**
     * Associate health monitor with the pool (POST /lb/pools/pool-id/health_monitors)
     *
     * @param   string   $poolId          The identifier of the LBaaS pool
     * @param   string   $healthMonitorId The identifier of the LBaaS health monitor
     * @return  object
     * @throws  RestClientException
     */
    public function associateLbHealthMonitorWithPool($poolId, $healthMonitorId)
    {
        $result = null;

        $options = array(
            'health_monitor' => array(
               'id' => (string) $healthMonitorId,
            ),
        );

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/pools/%s/health_monitors', $this->escape($poolId)),
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->health_monitor;
        }

        return $result;
    }

    /**
     * Disassociates health monitor from a pool (DELETE /lb/pools/pool-id/health_monitors/healthmonitor-id)
     *
     * @param   string   $poolId          The identifier of the LBaaS pool
     * @param   string   $healthMonitorId The identifier of the LBaaS health monitor
     * @return  bool     Returns boolean true on success or throws an exception
     * @throws  RestClientException
     */
    public function disassociateLbHealthMonitorFromPool($poolId, $healthMonitorId)
    {
        $result = false;

        $options = array(
            'health_monitor' => array(
               'id' => (string) $healthMonitorId,
            ),
        );

        $response = $this->getClient()->call(
            $this->service,
            sprintf('/lb/pools/%s/health_monitors/%s', $this->escape($poolId), $this->escape($healthMonitorId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * List Members action (GET /lb/members[/member-id])
     *
     * @param   string                $memberId  optional The ID of the member to show detailed info
     * @param   ListLbPoolsFilter     $filter    optional The query filter.
     * @return  DefaultPaginationList|object Returns the list of the Members or the requested pool
     * @throws  RestClientException
     */
    public function listLbMembers($memberId = null, ListLbMembersFilter $filter = null)
    {
        $result = null;
        $detailed = ($memberId !== null ? sprintf("/%s", $this->escape($memberId)) : '');
        $response = $this->getClient()->call(
            $this->service,
            '/lb/members' . $detailed . ($filter !== null ? '?' . $filter->getQueryString() : '')
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'members', $result->members,
                    (isset($result->members_links) ? $result->members_links : null)
                );
            } else {
                $result = $result->member;
            }
        }
        return $result;
    }

    /**
     * Creates LBaaS member (POST /lb/members)
     *
     * @param   CreateLbMember $request The request object
     * @return  object   Returns LBaaS member object
     * @throws  RestClientException
     */
    public function createLbMember(CreateLbMember $request)
    {
        $result = null;

        if (empty($request->tenant_id)) {
            $request->tenant_id = $this->service->getTenantId();
        }

        $options = array('member' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        ));

        $response = $this->getClient()->call(
            $this->service, '/lb/members',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->member;
        }

        return $result;
    }

    /**
     * Deletes LBaaS member (DELETE /lb/members/member-id)
     *
     * @param   string  $memberId The ID of the member to delete
     * @return  boolean Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteLbMember($memberId)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/members/%s', $this->escape($memberId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * Update LBaaS member (PUT /lb/members/member-id)
     *
     * @param   string       $memberId The Id of the LBaaS member to update
     * @param   array|object $options  Raw options object (It will be json_encoded and passed as is.)
     * @return  object       Returns LBaaS member object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function updateLbMember($memberId, $options)
    {
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/members/%s', $this->escape($memberId)),
            array('_putData' => json_encode(array('member' => $options))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->member;
        }

        return $result;
    }

    /**
     * List LBaaS Health Monitors action (GET /lb/health_monitors[/health-monitors-id])
     *
     * @param   string                     $healthMonitorId  optional The ID of the health monitor to show detailed info
     * @param   ListLbHealthMonitorsFilter $filter           optional The query filter.
     * @return  DefaultPaginationList|object Returns the list of the Health Monitors or the requested pool
     * @throws  RestClientException
     */
    public function listLbHealthMonitors($healthMonitorId = null, ListLbHealthMonitorsFilter $filter = null)
    {
        $result = null;
        $detailed = ($healthMonitorId !== null ? sprintf("/%s", $this->escape($healthMonitorId)) : '');
        $response = $this->getClient()->call(
            $this->service,
            '/lb/health_monitors' . $detailed . ($filter !== null ? '?' . $filter->getQueryString() : '')
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'health_monitors', $result->health_monitors,
                    (isset($result->health_monitors_links) ? $result->health_monitors_links : null)
                );
            } else {
                $result = $result->health_monitor;
            }
        }
        return $result;
    }

    /**
     * Creates LBaaS Health Monitor (POST /lb/health_monitors)
     *
     * @param   CreateLbHealthMonitor $request The request object
     * @return  object   Returns LBaaS health monitor object
     * @throws  RestClientException
     */
    public function createLbHealthMonitor(CreateLbHealthMonitor $request)
    {
        $result = null;

        if (empty($request->tenant_id)) {
            $request->tenant_id = $this->service->getTenantId();
        }

        $options = array('health_monitor' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        ));

        $response = $this->getClient()->call(
            $this->service, '/lb/health_monitors',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->health_monitor;
        }

        return $result;
    }

    /**
     * Deletes LBaaS health monitor (DELETE /lb/health_monitors/health_monitor-id)
     *
     * @param   string  $healthMonitorId The ID of the health monitor to delete
     * @return  boolean Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteLbHealthMonitor($healthMonitorId)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/health_monitors/%s', $this->escape($healthMonitorId)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * Update LBaaS health monitor (PUT /lb/health_monitors/health_monitors-id)
     *
     * @param   string       $healthMonitorId The Id of the LBaaS health monitor to update
     * @param   array|object $options  Raw options object (It will be json_encoded and passed as is.)
     * @return  object       Returns LBaaS health monitor object on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function updateLbHealthMonitor($healthMonitorId, $options)
    {
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/lb/health_monitors/%s', $this->escape($healthMonitorId)),
            array('_putData' => json_encode(array('health_monitor' => $options))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->health_monitor;
        }

        return $result;
    }

    /**
     * Gets the list of the security groups
     *
     * @param   string $id optional
     *          The ID of the security group to view
     *
     * @param   ListSecurityGroupsFilter $filter optional
     *          The filter options. Filter doesn't apply to detailed info
     *
     * @param   array $fields optional
     *          The list of the fields to show
     *
     * @return  DefaultPaginationList|object Returns the list of the security groups or specified security group
     * @throws  RestClientException
     */
    public function listSecurityGroups($id = null, ListSecurityGroupsFilter $filter = null, array $fields = null)
    {
        $result = null;

        $detailed = ($id !== null ? sprintf("/%s", $this->escape($id)) : '');

        if (!empty($fields)) {
            $acceptedFields = ['name', 'description', 'id', 'tenant_id', 'security_group_rules'];

            $fields = join('&fields=', array_map("rawurlencode", array_intersect(array_values($fields), $acceptedFields)));
        }

        $querystr = ($filter !== null && $detailed == '' ? $filter->getQueryString() : '')
                  . ($fields ? '&fields=' . $fields : '');

        $querystr = (!empty($querystr) ? '?' . ltrim($querystr, '&') : '');

        $response = $this->getClient()->call(
            $this->service,
            '/security-groups' . $detailed . $querystr
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());

            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'security_groups', $result->security_groups,
                    (isset($result->security_groups_links) ? $result->security_groups_links : null)
                );
            } else {
                $result = $result->security_group;
            }
        }

        return $result;
    }

    /**
     * Creates Security group (POST /security-groups)
     *
     * @param   string   $name        The name of the security group
     * @param   string   $description optional The description of the security group
     * @return  object   Returns security group object
     * @throws  RestClientException
     */
    public function createSecurityGroup($name, $description = null)
    {
        $result = null;

        $request = ['name' => $name];

        if (!empty($description)) {
            $request['description'] = $description;
        }

        $response = $this->getClient()->call($this->service, '/security-groups', ['security_group' => $request], 'POST');

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->security_group;
        }

        return $result;
    }

    /**
     * Deletes Security Group (DELETE /security-groups/security-group-id)
     *
     * This operation deletes an OpenStack Networking security group and its associated security group rules,
     * provided that a port is not associated with the security group
     *
     * @param   string  $id The UUID of the security group to delete
     * @return  boolean Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteSecurityGroup($id)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/security-groups/%s', $this->escape($id)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
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
     * @param   ListSecurityGroupRulesFilter $filter optional
     *          The filter options. Filter doesn't apply to detailed info
     *
     * @param   array $fields optional
     *          The list of the fields to show
     *
     * @return  DefaultPaginationList|object Returns the list of the security groups or specified security group
     * @throws  RestClientException
     */
    public function listSecurityGroupRules($id = null, ListSecurityGroupRulesFilter $filter = null, array $fields = null)
    {
        $result = null;

        $detailed = ($id !== null ? sprintf("/%s", $this->escape($id)) : '');

        if (!empty($fields)) {
            $acceptedFields = [
                'id', 'security_group_id', 'tenant_id', 'direction' , 'ethertype', 'port_range_max', 'port_range_min',
                'protocol', 'remote_group_id', 'remote_ip_prefix',
            ];

            $fields = join('&fields=', array_map("rawurlencode", array_intersect(array_values($fields), $acceptedFields)));
        }

        $querystr = ($filter !== null && $detailed == '' ? $filter->getQueryString() : '')
                  . ($fields ? '&fields=' . $fields : '');

        $querystr = (!empty($querystr) ? '?' . ltrim($querystr, '&') : '');

        $response = $this->getClient()->call(
            $this->service,
            '/security-group-rules' . $detailed . $querystr
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());

            if (empty($detailed)) {
                $result = new DefaultPaginationList(
                    $this->service, 'security_group_rules', $result->security_group_rules,
                    (isset($result->security_group_rules_links) ? $result->security_group_rules_links : null)
                );
            } else {
                $result = $result->security_group_rule;
            }
        }

        return $result;
    }

    /**
     * Creates Security Group Rule (POST /security-group-rules)
     *
     * @param   CreateSecurityGroupRule  $request The request object
     * @return  object                   Returns Security Group Rule object
     * @throws  RestClientException
     */
    public function createSecurityGroupRule(CreateSecurityGroupRule $request)
    {
        $result = null;

        $options = ['security_group_rule' => array_filter(
            get_object_vars($request),
            [$this, 'filterNull']
        )];

        $response = $this->getClient()->call($this->service, '/security-group-rules', $options, 'POST');

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->security_group_rule;
        }

        return $result;
    }

    /**
     * Deletes Security Group Rule (DELETE /security-group-rules/​rules-security-groups-id})
     *
     * Deletes a specified rule from a OpenStack Networking security group.
     *
     * @param   string  $id The UUID of the security group rule to delete
     * @return  boolean Returns true on success or throws an exception
     * @throws  RestClientException
     */
    public function deleteSecurityGroupRule($id)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/security-group-rules/%s', $this->escape($id)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * NULL value filter callback
     *
     * @param    mixed   $v The value
     * @return   boolean Returns true if the value does not equal NULL
     */
    private function filterNull($v)
    {
        return $v !== null;
    }
}