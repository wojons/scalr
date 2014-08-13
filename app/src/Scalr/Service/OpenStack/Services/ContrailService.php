<?php
namespace Scalr\Service\OpenStack\Services;

use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\Type\DefaultPaginationList;

/**
 * Contrail API
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (12.02.2014)
 *
 * @method   \Scalr\Service\OpenStack\Services\Contrail\V2\ContrailApi getApiHandler()
 *           getApiHandler()
 *           Gets an Contrail API handler for the specific version
 */
class ContrailService extends AbstractService implements ServiceInterface
{

    const VERSION_V2 = 'V2';

    const VERSION_DEFAULT = self::VERSION_V2;

    const CONTRAIL_PORT = 8082;

    /**
     * Miscellaneous cache
     *
     * @var array
     */
    private $cache;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return OpenStack::SERVICE_CONTRAIL;
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
        if ($cfg->getAuthToken() === null) {
            return $cfg->getIdentityEndpoint();
        } else if (!isset($this->cache['endpoint'])) {
            $this->cache['endpoint'] = http_build_url($cfg->getIdentityEndpoint(), array(
                'port' => self::CONTRAIL_PORT,
                'path' => '/',
            ));
        }
        return $this->cache['endpoint'];
    }

    /**
     * Gets virtual DNSs (GET /virtual-DNSs)
     *
     * @param   string        $virtualDnsId   optional The identifier of the virtual DNS to view.
     * @return  DefaultPaginationList Returns the list of the virtual DNSs
     * @throws  RestClientException
     */
    public function listVirtualDns($virtualDnsId = null)
    {
        return $this->getApiHandler()->listVirtualDns($virtualDnsId);
    }

    /**
     * Creates  virtual DNS (POST /virtual-DNSs)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created virtual DNS on success or throws an exception otherwise
     */
    public function createVirtualDns($request)
    {
        return $this->getApiHandler()->createVirtualDns($request);
    }

    /**
     * Updates virtual DNS (PUT /virtual-DNS/virtual-DNS-uuid)
     *
     * @param   string   $virtualDnsId The identifier of the virtual DNS
     * @param   array    $request  Request
     * @return  object
     */
    public function updateVirtualDns($virtualDnsId, $request)
    {
        return $this->getApiHandler()->updateVirtualDns($virtualDnsId, $request);
    }

    /**
     * Deletes virtual DNS  (DELETE /virtual-DNS/virtual-DNS-uuid)
     *
     * @param   string     $virtualDnsId The ID of the virtual dns to delete.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteVirtualDns($virtualDnsId)
    {
        return $this->getApiHandler()->deleteVirtualDns($virtualDnsId);
    }

    /**
     * Gets IPAMs (GET /network-ipams[/virtual-DNS-uuid])
     *
     * @param   string       $ipamId          optional The ipam id
     * @return  DefaultPaginationList|object  Returns the list of the network IPAMs or specified IPAM object
     * @throws  RestClientException
     */
    public function listIpam($ipamId = null)
    {
        return $this->getApiHandler()->listIpam($ipamId);
    }

    /**
     * Creates network IPAM (POST /network-ipams)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created network ipams on success or throws an exception otherwise
     */
    public function createIpam($request)
    {
        return $this->getApiHandler()->createIpam($request);
    }

    /**
     * Updates network IPAM (PUT /network-ipam/network-IPAM-uuid)
     *
     * @param   string   $ipamId The identifier of the network IPAM
     * @param   array    $request  Request
     * @return  object
     */
    public function updateIpam($ipamId, $request)
    {
        return $this->getApiHandler()->updateIpam($ipamId, $request);
    }

    /**
     * Updates virtual network (PUT /virtual-network/virtual-network-uuid)
     *
     * @param   string   $virtualNetworkId The identifier of the virtual network
     * @param   array    $request  Request
     * @return  object
     */
    public function updateVirtualNetwork($virtualNetworkId, $request)
    {
        return $this->getApiHandler()->updateVirtualNetwork($virtualNetworkId, $request);
    }

    /**
     * Deletes network IPAM  (DELETE /network-ipam/uuid)
     *
     * @param   string     $imapId The ID of the network IPAM to delete.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteIpam($ipamId)
    {
        return $this->getApiHandler()->deleteIpam($ipamId);
    }

    /**
     * Gets virtual networks (GET /virtual-networks[/virtual-network-uuid])
     *
     * @param   string       $virtualNetworkId optional The ID of the virtual network
     * @return  DefaultPaginationList|object  Returns the list of the virtual networks or specified virtual network
     * @throws  RestClientException
     */
    public function listVirtualNetworks($virtualNetworkId = null)
    {
        return $this->getApiHandler()->listVirtualNetworks($virtualNetworkId);
    }

    /**
     * Creates virtual network (POST /virtual-networks)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created virtual network on success or throws an exception otherwise
     */
    public function createVirtualNetwork($request)
    {
        return $this->getApiHandler()->createVirtualNetwork($request);
    }

    /**
     * Deletes virtual network  (DELETE /virtual-network/uuid)
     *
     * @param   string     $virtualNetworkId The ID of the virtual network to delete.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteVirtualNetwork($virtualNetworkId)
    {
        return $this->getApiHandler()->deleteVirtualNetwork($virtualNetworkId);
    }

    /**
     * Gets network policies (GET /network-policys[/network-policy-id])
     *
     * @param   string       $networkPolicyId optional The ID of the network policy
     * @return  DefaultPaginationList|object  Returns the list of the network policies or specified network policy
     * @throws  RestClientException
     */
    public function listNetworkPolicies($networkPolicyId = null)
    {
        return $this->getApiHandler()->listNetworkPolicies($networkPolicyId);
    }

    /**
     * Creates network policy (POST /network-policys)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created network policy on success or throws an exception otherwise
     */
    public function createNetworkPolicy($request)
    {
        return $this->getApiHandler()->createNetworkPolicy($request);
    }

    /**
     * Updates network policy (PUT /network-policy/uuid)
     *
     * @param   string   $policyId The identifier of the network policy
     * @param   array    $request  Request
     * @return  object
     */
    public function updateNetworkPolicy($policyId, $request)
    {
        return $this->getApiHandler()->updateNetworkPolicy($policyId, $request);
    }

    /**
     * Deletes network policy  (DELETE /network-policy/uuid)
     *
     * @param   string     $networkPolicyId The ID of the network policy to delete.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteNetworkPolicy($networkPolicyId)
    {
        return $this->getApiHandler()->deleteNetworkPolicy($networkPolicyId);
    }
}