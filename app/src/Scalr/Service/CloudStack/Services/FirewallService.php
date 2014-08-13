<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\CreateIpForwardingRuleData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\CreatePortForwardingRuleData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\EnableStaticNatData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ForwardingRuleResponseData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ForwardingRuleResponseList;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ListIpForwardingRulesData;
use Scalr\Service\CloudStack\Services\Firewall\DataType\ListPortForwardingRulesData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\Firewall\V26032014\FirewallApi getApiHandler()
 *           getApiHandler()
 *           Gets an Firewall API handler for the specific version
 */
class FirewallService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_FIREWALL;
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
     * Lists all port forwarding rules for an IP address.
     *
     * @param ListPortForwardingRulesData|array $filter List port forwarding rule data object.
     * @param PaginationType $pagination Pagination.
     * @return ForwardingRuleResponseList|null
     */
    public function listPortForwardingRules($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListPortForwardingRulesData)) {
            $filter = ListPortForwardingRulesData::initArray($filter);
        }
        return $this->getApiHandler()->listPortForwardingRules($filter, $pagination);
    }

    /**
     * Creates a port forwarding rule
     *
     * @param CreatePortForwardingRuleData|array $request  Create port forwarding rule request data object
     * @return ForwardingRuleResponseData
     */
    public function createPortForwardingRule($request)
    {
        if ($request !== null && !($request instanceof CreatePortForwardingRuleData)) {
            $request = CreatePortForwardingRuleData::initArray($request);
        }
        return $this->getApiHandler()->createPortForwardingRule($request);
    }

    /**
     * Deletes a port forwarding rule
     *
     * @param string $id the ID of the port forwarding rule
     * @return ResponseDeleteData
     */
    public function deletePortForwardingRule($id)
    {
        return $this->getApiHandler()->deletePortForwardingRule($id);
    }

    /**
     * Enables static nat for given ip address
     *
     * @param EnableStaticNatData|array $request Enable Nat request data object
     * @return ResponseDeleteData
     */
    public function enableStaticNat($request)
    {
        if ($request !== null && !($request instanceof EnableStaticNatData)) {
            $request = EnableStaticNatData::initArray($request);
        }
        return $this->getApiHandler()->enableStaticNat($request);
    }

    /**
     * Creates an ip forwarding rule
     *
     * @param CreateIpForwardingRuleData|array $request Create ip rule data object
     * @return ForwardingRuleResponseData
     */
    public function createIpForwardingRule($request)
    {
        if ($request !== null && !($request instanceof CreateIpForwardingRuleData)) {
            $request = CreateIpForwardingRuleData::initArray($request);
        }
        return $this->getApiHandler()->createIpForwardingRule($request);
    }

    /**
     * Deletes an ip forwarding rule
     *
     * @param string $id the id of the forwarding rule
     * @return ResponseDeleteData
     */
    public function deleteIpForwardingRule($id)
    {
        return $this->getApiHandler()->deleteIpForwardingRule($id);
    }

    /**
     * List the ip forwarding rules
     *
     * @param ListIpForwardingRulesData|array $filter List ip rules data object
     * @param PaginationType $pagination Pagination
     * @return ForwardingRuleResponseList|null
     */
    public function listIpForwardingRules($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListIpForwardingRulesData)) {
            $filter = ListIpForwardingRulesData::initArray($filter);
        }
        return $this->getApiHandler()->listIpForwardingRules($filter, $pagination);
    }

    /**
     * Disables static rule for given ip address
     *
     * @param string $ipAddressId the public IP address id for which static nat feature is being disableed
     * @return ResponseDeleteData
     */
    public function disableStaticNat($ipAddressId)
    {
        return $this->getApiHandler()->disableStaticNat($ipAddressId);
    }

}