<?php
namespace Scalr\Service\OpenStack\Services\Contrail\V2;

use Scalr\Service\OpenStack\Client\ClientInterface;
use Scalr\Service\OpenStack\Services\ContrailService;
use Scalr\Service\OpenStack\Type\DefaultPaginationList;
use Scalr\Service\OpenStack\Exception\RestClientException;

/**
 * Contrail API
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (12.02.2014)
 */
class ContrailApi
{
    /**
     * @var ContrailService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   ContrailService $service
     */
    public function __construct(ContrailService $service)
    {
        $this->service = $service;
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
     * Lists extensions for the service
     *
     * @return array Returns empty array because of contrail does not have such action.
     */
    public function listExtensions()
    {
        return array();
    }

    /**
     * Checks availability of the service
     *
     * This operation may take 1 - 5 seconds if service is unavailable.
     *
     * @return array Returns array of the links
     * @throws RestClientException
     */
    public function discoverApiServerResources()
    {
        $result = null;

        $response = $this->getClient()->call($this->service , '/' , array(
            '__speedup' => 1,
        ));

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->links;
        }

        return $result;
    }

    /**
     * Executes common POST create action
     *
     * @param    array|object      $request
     * @param    string            $subject
     * @return   object
     * @throws   RestClientException
     */
    private function _createAction($request, $subject)
    {
        $result = null;

        $options = array($subject => $request);

        $response = $this->getClient()->call(
            $this->service, '/' . $subject . 's',
            $options, 'POST'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->$subject;
        }

        return $result;
    }

    /**
     * Executes common delete action
     *
     * @param   string    $subject  The subject
     * @param   string    $id       The identifier of the subject
     * @return  boolean   Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    private function _deleteAction($subject, $id)
    {
        $result = false;

        $response = $this->getClient()->call(
            $this->service, sprintf('/%s/%s', $subject, $this->escape($id)),
            null, 'DELETE'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = true;
        }

        return $result;
    }

    /**
     * Executes common list/get action
     *
     * @param    string     $subject  The subject
     * @param    string     $id       optional the identifier of the subject to show
     * @return   DefaultPaginationList|object
     * @throws   RestClientException
     */
    private function _listAction($subject, $id = null)
    {
        $result = null;
        $subject = !empty($id) ? $subject : $subject . 's';
        $response = $this->getClient()->call(
            $this->service,
            '/' . $subject . (!empty($id) ? sprintf('/%s', $this->escape($id)) : ''),
            null, 'GET'
        );
        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            if (empty($id)) {
                $result = new DefaultPaginationList(
                    $this->service, $subject, $result->$subject,
                    (isset($result->{$subject . "_links"}) ? $result->{$subject . "_links"} : null)
                );
            } else {
                $result = $result->$subject;
            }
        }

        return $result;
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
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/virtual-DNS/%s', $this->escape($virtualDnsId)),
            array('_putData' => json_encode(array('virtual-DNS' => $request))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->{'virtual-DNS'};
        }

        return $result;
    }

    /**
     * Gets virtual DNSs (GET /virtual-DNSs[/virtual-DNS-uuid])
     *
     * @param   string       $virtualDnsId    optional The identifier of the virtual DNS
     * @return  DefaultPaginationList|object  Returns the list of the virtual DNSs or specified DNS object
     * @throws  RestClientException
     */
    public function listVirtualDns($virtualDnsId = null)
    {
        return $this->_listAction('virtual-DNS', $virtualDnsId);
    }

    /**
     * Creates  virtual DNS (POST /virtual-DNSs)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created virtual DNS on success or throws an exception otherwise
     */
    public function createVirtualDns($request)
    {
        return $this->_createAction($request, 'virtual-DNS');
    }

    /**
     * Deletes virtual DNS  (DELETE /virtual-DNS/uuid)
     *
     * @param   string     $virtualDnsId The ID of the virtual dns to delete.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteVirtualDns($virtualDnsId)
    {
        return $this->_deleteAction('virtual-DNS', $virtualDnsId);
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
        return $this->_listAction('network-ipam', $ipamId);
    }

    /**
     * Creates network IPAM (POST /network-ipams)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created network ipams on success or throws an exception otherwise
     */
    public function createIpam($request)
    {
        return $this->_createAction($request, 'network-ipam');
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
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/network-ipam/%s', $this->escape($ipamId)),
            array('_putData' => json_encode(array('network-ipam' => $request))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->{'network-ipam'};
        }

        return $result;
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
        return $this->_deleteAction('network-ipam', $ipamId);
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
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/virtual-network/%s', $this->escape($virtualNetworkId)),
            array('_putData' => json_encode(array('virtual-network' => $request))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->{'virtual-network'};
        }

        return $result;
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
        return $this->_listAction('virtual-network', $virtualNetworkId);
    }

    /**
     * Creates virtual network (POST /virtual-networks)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created virtual network on success or throws an exception otherwise
     */
    public function createVirtualNetwork($request)
    {
        return $this->_createAction($request, 'virtual-network');
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
        return $this->_deleteAction('virtual-network', $virtualNetworkId);
    }

    /**
     * Updates network policy (PUT /network-policy/network-policy-uuid)
     *
     * @param   string   $networkPolicyId The identifier of the network policy
     * @param   array    $request  Request
     * @return  object
     */
    public function updateNetworkPolicy($networkPolicyId, $request)
    {
        $result = null;

        $response = $this->getClient()->call(
            $this->service, sprintf('/network-policy/%s', $this->escape($networkPolicyId)),
            array('_putData' => json_encode(array('network-policy' => $request))), 'PUT'
        );

        if ($response->hasError() === false) {
            $result = json_decode($response->getContent());
            $result = $result->{'network-policy'};
        }

        return $result;
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
        return $this->_listAction('network-policy', $networkPolicyId);
    }

    /**
     * Creates network policy (POST /network-policys)
     *
     * @param   array|object   $request The request body
     * @return  object         Returns created network policy on success or throws an exception otherwise
     */
    public function createNetworkPolicy($request)
    {
        return $this->_createAction($request, 'network-policy');
    }

    /**
     * Deletes network policy  (DELETE /network-policy/uuid)
     *
     * @param   string     $virtualNetworkId The ID of the network policy to delete.
     * @return  bool       Returns true on success or throws an exception otherwise
     * @throws  RestClientException
     */
    public function deleteNetworkPolicy($networkPolicyId)
    {
        return $this->_deleteAction('network-policy', $networkPolicyId);
    }
}