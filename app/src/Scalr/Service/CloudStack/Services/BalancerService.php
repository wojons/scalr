<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\VirtualMachineInstancesList;
use Scalr\Service\CloudStack\Services\Balancer\DataType\BalancerResponseData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\BalancerResponseList;
use Scalr\Service\CloudStack\Services\Balancer\DataType\CreateBalancerRuleData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\ListBalancerRuleInstancesData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\ListBalancerRulesData;
use Scalr\Service\CloudStack\Services\Balancer\DataType\UpdateBalancerRuleData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Balancer\V26032014\BalancerApi getApiHandler()
 *           getApiHandler()
 *           Gets an Network API handler for the specific version
 */
class BalancerService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_BALANCER;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getVersion()
     */
    public function getVersion()
    {
        return self::VERSION_DEFAULT;
    }

    /**
     * Creates a load balancer rule
     *
     * @param CreateBalancerRuleData|array $request load balancer request data
     * @return BalancerResponseData
     */
    public function createRule($request)
    {
        if ($request !== null && !($request instanceof CreateBalancerRuleData)) {
            $request = CreateBalancerRuleData::initArray($request);
        }
        return $this->getApiHandler()->createLoadBalancerRule($request);
    }

    /**
     * Deletes a load balancer rule.
     *
     * @param string $id the ID of the load balancer rule
     * @return ResponseDeleteData
     */
    public function deleteRule($id)
    {
        return $this->getApiHandler()->deleteLoadBalancerRule($id);
    }

    /**
     * Removes a virtual machine or a list of virtual machines from a load balancer rule.
     *
     * @param string $id The ID of the load balancer rule
     * @param string $virtualMachineIds the list of IDs of the virtual machines that are being removed from the load balancer rule (i.e. virtualMachineIds=1,2,3)
     * @return ResponseDeleteData
     */
    public function removeFromRule($id, $virtualMachineIds)
    {
        return $this->getApiHandler()->removeFromLoadBalancerRule($id, $virtualMachineIds);
    }

    /**
     * Assigns virtual machine or a list of virtual machines to a load balancer rule.
     *
     * @param string $id the ID of the load balancer rule
     * @param string $virtualMachineIds the list of IDs of the virtual machine that are being assigned to the load balancer rule(i.e. virtualMachineIds=1,2,3)
     * @return ResponseDeleteData
     */
    public function assignToRule($id, $virtualMachineIds)
    {
        return $this->getApiHandler()->assignToLoadBalancerRule($id, $virtualMachineIds);
    }

    /**
     * Lists load balancer rules.
     *
     * @param ListBalancerRulesData|array $filter    List load balancer rules request data
     * @param PaginationType        $pagination     Pagination data
     * @return BalancerResponseList|null
     */
    public function listRules($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListBalancerRulesData)) {
            $filter = ListBalancerRulesData::initArray($filter);
        }
        return $this->getApiHandler()->listLoadBalancerRules($filter, $pagination);
    }

    /**
     * List all virtual machine instances that are assigned to a load balancer rule.
     *
     * @param ListBalancerRuleInstancesData|array $filter    List load balancer rule instances request data
     * @param PaginationType                $pagination     Pagination data
     * @return VirtualMachineInstancesList|null
     */
    public function listRuleInstances($filter, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListBalancerRuleInstancesData)) {
            $filter = ListBalancerRuleInstancesData::initArray($filter);
        }
        return $this->getApiHandler()->listLoadBalancerRuleInstances($filter, $pagination);
    }

    /**
     * Updates load balancer
     *
     * @param UpdateBalancerRuleData|array $request Update load balancer rule request data
     * @return BalancerResponseData
     */
    public function updateRule($request)
    {
        if ($request !== null && !($request instanceof UpdateBalancerRuleData)) {
            $request = UpdateBalancerRuleData::initArray($request);
        }
        return $this->getApiHandler()->updateLoadBalancerRule($request);
    }
}