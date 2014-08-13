<?php
namespace Scalr\Service\CloudStack\Services\SecurityGroup\V26032014;

use Scalr\Service\CloudStack\Client\ClientInterface;
use Scalr\Service\CloudStack\DataType\IngressruleData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\SecurityGroupList;
use Scalr\Service\CloudStack\Services\AbstractApi;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\AuthorizeSecurityIngress;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\CreateSecurityGroupData;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\DeleteSecurityGroupData;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\ListSecurityGroupsData;
use Scalr\Service\CloudStack\Services\SecurityGroupService;
use Scalr\Service\CloudStack\Services\TagsTrait;
use Scalr\Service\CloudStack\Services\UpdateTrait;
use Scalr\Service\CloudStack\Services\VirtualTrait;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class SecurityGroupApi extends AbstractApi
{
    use VirtualTrait, TagsTrait, UpdateTrait;

    /**
     * @var SecurityGroupService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   SecurityGroupService $securityGroup
     */
    public function __construct(SecurityGroupService $securityGroup)
    {
        $this->service = $securityGroup;
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
     * Creates a security group
     *
     * @param CreateSecurityGroupData $requestData  Request data object
     * @return SecurityGroupData
     */
    public function createSecurityGroup(CreateSecurityGroupData $requestData)
    {
        $result = null;

        $response = $this->getClient()->call(
            'createSecurityGroup', $requestData->toArray()
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadSecurityGroupData($resultObject->securitygroup);
            }
        }

        return $result;
    }

    /**
     * Deletes security group
     *
     * @param DeleteSecurityGroupData $requestData Request data object
     * @return ResponseDeleteData
     */
    public function deleteSecurityGroup(DeleteSecurityGroupData $requestData = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        $response = $this->getClient()->call(
            'deleteSecurityGroup', $args
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
     * Authorizes a particular ingress rule for this security group
     *
     * @param AuthorizeSecurityIngress $requestData Request data object
     * @return IngressruleData
     */
    public function authorizeSecurityGroupIngress(AuthorizeSecurityIngress $requestData = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        $response = $this->getClient()->call(
            'authorizeSecurityGroupIngress', $args
        );

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (!empty($resultObject)) {
                $result = $this->_loadIngressruleData($resultObject);
            }
        }

        return $result;
    }

    /**
     * Deletes a particular ingress rule from this security group
     *
     * @param string $id       The ID of the ingress rule
     * @param string $account  An optional account for the security group.
     *                         Must be used with domainId.
     * @param string $domainId An optional domainId for the security group.
     *                         If the account parameter is used, domainId must also be used.
     * @return ResponseDeleteData
     */
    public function revokeSecurityGroupIngress($id, $account = null, $domainId = null)
    {
        $result = null;

        $response = $this->getClient()->call(
            'revokeSecurityGroupIngress',
                array(
                    'id' => $this->escape($id),
                    'account' => $this->escape($account),
                    'domainid' => $this->escape($domainId)
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
     * Lists security groups
     *
     * @param ListSecurityGroupsData $requestData Request data object
     * @param PaginationType $pagination Pagination
     * @return SecurityGroupList|null
     */
    public function listSecurityGroups(ListSecurityGroupsData $requestData = null, PaginationType $pagination = null)
    {
        $result = null;
        $args = array();

        if ($requestData !== null) {
            $args = $requestData->toArray();
        }
        if ($pagination !== null) {
            $args = array_merge($args, $pagination->toArray());
        }
        $response = $this->getClient()->call('listSecurityGroups', $args);

        if ($response->hasError() === false) {
            $resultObject = $response->getResult();
            if (property_exists($resultObject, 'count') && $resultObject->count > 0) {
                $result = $this->_loadSecurityGroupList($resultObject->securitygroup);
            }
        }

        return $result;
    }

}