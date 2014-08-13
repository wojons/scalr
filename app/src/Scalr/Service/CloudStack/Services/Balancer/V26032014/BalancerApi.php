<?php
namespace Scalr\Service\CloudStack\Services\Balancer\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesList;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\Balancer\DataType\BalancerResponseData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\BalancerResponseList;
use Scalr\Service\CloudStack\Services\Balancer\DataType\CreateBalancerRuleData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\ListBalancerRuleInstancesData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\ListBalancerRulesData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\UpdateBalancerRuleData;
use Scalr\Service\CloudStack\Services\BalancerService;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\VirtualTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class BalancerApi extends AbstractApi
{
    use TagsTrait, UpdateTrait, VirtualTrait;

    /**
     * @var BalancerService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   BalancerService $balancer
     */
    public function __construct(BalancerService $balancer)
    {
        $this->service = $balancer;
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
     * Creates a load balancer rule
     *
     * @param CreateBalancerRuleData $requestData load balancer request data
     * @return BalancerResponseData
     */
    public function createLoadBalancerRule(CreateBalancerRuleData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createLoadBalancerRule', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadBalancerResponseData($resultObject);
            }
        }

        return $result;

    }

    /**
     * Deletes a load balancer rule.
     *
     * @param string $id the ID of the load balancer rule
     * @return ResponseDeleteData
     */
    public function deleteLoadBalancerRule($id)
    {
        $result = null;

        $response = $this->getClient()->call(
            'deleteLoadBalancerRule',
             array('id' => $this->escape($id))
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
     * Removes a virtual machine or a list of virtual machines from a load balancer rule.
     *
     * @param string $id The ID of the load balancer rule
     * @param string $virtualMachineIds the list of IDs of the virtual machines that are being removed from the load balancer rule (i.e. virtualMachineIds=1,2,3)
     * @return ResponseDeleteData
     */
    public function removeFromLoadBalancerRule($id, $virtualMachineIds)
    {
        $result = null;

        $response = $this->getClient()->call(
            'removeFromLoadBalancerRule',
             array(
                 'id' => $this->escape($id),
                 'virtualmachineids' => $this->escape($virtualMachineIds)
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
     * Assigns virtual machine or a list of virtual machines to a load balancer rule.
     *
     * @param string $id the ID of the load balancer rule
     * @param string $virtualMachineIds the list of IDs of the virtual machine that are being assigned to the load balancer rule(i.e. virtualMachineIds=1,2,3)
     * @return ResponseDeleteData
     */
    public function assignToLoadBalancerRule($id, $virtualMachineIds)
    {
        $result = null;

        $response = $this->getClient()->call(
            'assignToLoadBalancerRule',
             array(
                 'id' => $this->escape($id),
                 'virtualmachineids' => $this->escape($virtualMachineIds)
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
     * Lists load balancer rules.
     *
     * @param ListBalancerRulesData $requestData    List load balancer rules request data
     * @param PaginationType        $pagination     Pagination data
     * @return BalancerResponseList|null
     */
    public function listLoadBalancerRules(ListBalancerRulesData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listLoadBalancerRules', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadBalancerResponseList($resultObject->loadbalancerrule);
            }
        }

        return $result;
    }

    /**
     * List all virtual machine instances that are assigned to a load balancer rule.
     *
     * @param ListBalancerRuleInstancesData $requestData    List load balancer rule instances request data
     * @param PaginationType                $pagination     Pagination data
     * @return VirtualMachineInstancesList|null
     */
    public function listLoadBalancerRuleInstances(ListBalancerRuleInstancesData $requestData, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listLoadBalancerRuleInstances', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadVirtualMachineInstancesList($resultObject->loadbalancerruleinstance);
            }
        }

        return $result;
    }

    /**
     * Updates load balancer
     *
     * @param UpdateBalancerRuleData $requestData Update load balancer rule request data
     * @return BalancerResponseData
     */
    public function updateLoadBalancerRule(UpdateBalancerRuleData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call('updateLoadBalancerRule', $requestData->toArray());

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadBalancerResponseData($resultObject);
            }
        }

        return $result;

    }

    /**
     * Loads BalancerResponseList from json object
     *
     * @param   object $balancerList
     * @return  BalancerResponseList Returns BalancerResponseList
     */
    protected function _loadBalancerResponseList($balancerList)
    {
        $result = new BalancerResponseList();

        if (!empty($balancerList)) {
            foreach ($balancerList as $balancer) {
                $item = $this->_loadBalancerResponseData($balancer);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads BalancerResponseData from json object
     *
     * @param   object $resultObject
     * @return  BalancerResponseData Returns BalancerResponseData
     */
    protected function _loadBalancerResponseData($resultObject)
    {
        $item = null;

        if (property_exists($resultObject, 'id')) {
            $item = new BalancerResponseData();
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