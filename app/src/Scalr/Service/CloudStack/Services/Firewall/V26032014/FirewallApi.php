<?php
namespace Scalr\Service\CloudStack\Services\Firewall\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\Firewall\DataType\CreateIpForwardingRuleData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\CreatePortForwardingRuleData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\EnableStaticNatData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ForwardingRuleResponseData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ForwardingRuleResponseList;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ListIpForwardingRulesData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ListPortForwardingRulesData;
use Scalr\Service\CloudStack\Services\FirewallService;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class FirewallApi extends AbstractApi
{
    use TagsTrait, UpdateTrait;
    /**
     * @var FirewallService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   FirewallService $firewall
     */
    public function __construct(FirewallService $firewall)
    {
        $this->service = $firewall;
    }

    /**
     * Gets HTTP Client
     *
     * @return  ClientInterface Returns HTTP Client
     */
    public function getClient()
    {
        return $this->service->getCloudStack()->getClient();
    }

    /**
     * Lists all port forwarding rules for an IP address.
     *
     * @param ListPortForwardingRulesData $requestData List port forwarding rule data object.
     * @param PaginationType $pagination Pagination.
     * @return ForwardingRuleResponseList|null
     */
    public function listPortForwardingRules(ListPortForwardingRulesData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listPortForwardingRules', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadPortForwardingRulesList($resultObject->portforwardingrule);
            }
        }

        return $result;
    }

    /**
     * Creates a port forwarding rule
     *
     * @param CreatePortForwardingRuleData $requestData  Create port forwarding rule request data object
     * @return ForwardingRuleResponseData
     */
    public function createPortForwardingRule(CreatePortForwardingRuleData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createPortForwardingRule', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadPortForwardingRulesData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes a port forwarding rule
     *
     * @param string $id the ID of the port forwarding rule
     * @return ResponseDeleteData
     */
    public function deletePortForwardingRule($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deletePortForwardingRule',
                array(
                    'id' => $this->escape($id),
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
     * Enables static nat for given ip address
     *
     * @param EnableStaticNatData $requestData Enable Nat request data object
     * @return ResponseDeleteData
     */
    public function enableStaticNat(EnableStaticNatData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'enableStaticNat', $requestData->toArray()
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
     * Creates an ip forwarding rule
     *
     * @param CreateIpForwardingRuleData $requestData Create ip rule data object
     * @return ForwardingRuleResponseData
     */
    public function createIpForwardingRule(CreateIpForwardingRuleData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createIpForwardingRule', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadPortForwardingRulesData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes an ip forwarding rule
     *
     * @param string $id the id of the forwarding rule
     * @return ResponseDeleteData
     */
    public function deleteIpForwardingRule($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteIpForwardingRule',
                array(
                    'id' => $this->escape($id),
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
     * List the ip forwarding rules
     *
     * @param ListIpForwardingRulesData $requestData List ip rules data object
     * @param PaginationType $pagination Pagination
     * @return ForwardingRuleResponseList|null
     */
    public function listIpForwardingRules(ListIpForwardingRulesData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listIpForwardingRules', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadPortForwardingRulesList($resultObject->ipforwardingrule);
            }
        }

        return $result;
    }

    /**
     * Disables static rule for given ip address
     *
     * @param string $ipAddressId the public IP address id for which static nat feature is being disableed
     * @return ResponseDeleteData
     */
    public function disableStaticNat($ipAddressId)
    {
        $result = null;

        $response = $this->getClient()->call(
            'disableStaticNat',
                array(
                    'ipaddressid' => $this->escape($ipAddressId),
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
     * Loads ForwardingRuleResponseList from json object
     *
     * @param   object $rulesList
     * @return  ForwardingRuleResponseList Returns ForwardingRuleResponseList
     */
    protected function _loadPortForwardingRulesList($rulesList)
    {
        $result = new ForwardingRuleResponseList();

        if (!empty($rulesList)) {
            foreach ($rulesList as $rule) {
                $item = $this->_loadPortForwardingRulesData($rule);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads ForwardingRuleResponseData from json object
     *
     * @param   object $resultObject
     * @return  ForwardingRuleResponseData Returns ForwardingRuleResponseData
     */
    protected function _loadPortForwardingRulesData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new ForwardingRuleResponseData();
            $properties = get_object_vars($item);

            foreach($properties as $property => $value) {
                if (property_exists($resultObject, "$property")) {
                    if (is_object($resultObject->{$property})) {
                        // Fix me. Temporary fix.
                        trigger_error('Cloudstack error. Unexpected sdt object class received in property: ' . $property . ', value: ' . json_encode($resultObject->{$property}), E_USER_WARNING);
                        $item->{$property} = json_encode($resultObject->{$property});
                    }
                    else {
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

}