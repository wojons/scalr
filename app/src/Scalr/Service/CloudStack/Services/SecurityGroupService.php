<?php
namespace Scalr\Service\CloudStack\Services;

use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\IngressruleData;
use Scalr\Service\CloudStack\DataType\PaginationType;
use Scalr\Service\CloudStack\DataType\ResponseDeleteData;
use Scalr\Service\CloudStack\DataType\SecurityGroupList;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\AuthorizeSecurityIngress;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\CreateSecurityGroupData;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\DeleteSecurityGroupData;
use Scalr\Service\CloudStack\Services\SecurityGroup\DataType\ListSecurityGroupsData;

/**
 * CloudStack API v4.3.0 (March 26, 2014)
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 *
 * @method   \Scalr\Service\CloudStack\Services\SecurityGroup\V26032014\SecurityGroupApi getApiHandler()
 *           getApiHandler()
 *           Gets an Volume API handler for the specific version
 */
class SecurityGroupService extends AbstractService implements ServiceInterface
{

    const VERSION_26032014 = 'V26032014';

    const VERSION_DEFAULT = self::VERSION_26032014;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\CloudStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return CloudStack::SERVICE_SECURITY_GROUP;
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
     * Creates a security group
     *
     * @param CreateSecurityGroupData|array $request  Request data object
     * @return SecurityGroupData
     */
    public function create($request)
    {
        if ($request !== null && !($request instanceof CreateSecurityGroupData)) {
            $request = CreateSecurityGroupData::initArray($request);
        }
        return $this->getApiHandler()->createSecurityGroup($request);
    }

    /**
     * Deletes security group
     *
     * @param DeleteSecurityGroupData|array $request Request data object
     * @return ResponseDeleteData
     */
    public function delete($request = null)
    {
        if ($request !== null && !($request instanceof DeleteSecurityGroupData)) {
            $request = DeleteSecurityGroupData::initArray($request);
        }
        return $this->getApiHandler()->deleteSecurityGroup($request);
    }

    /**
     * Authorizes a particular ingress rule for this security group
     *
     * @param AuthorizeSecurityIngress|array $request Request data object
     * @return IngressruleData
     */
    public function authorizeIngress($request = null)
    {
        if ($request !== null && !($request instanceof AuthorizeSecurityIngress)) {
            $request = AuthorizeSecurityIngress::initArray($request);
        }
        return $this->getApiHandler()->authorizeSecurityGroupIngress($request);
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
    public function revokeIngress($id, $account = null, $domainId = null)
    {
        return $this->getApiHandler()->revokeSecurityGroupIngress($id, $account, $domainId);
    }

    /**
     * Lists security groups
     *
     * @param ListSecurityGroupsData|array $filter Request data object
     * @param PaginationType $pagination Pagination
     * @return SecurityGroupList|null
     */
    public function describe($filter = null, PaginationType $pagination = null)
    {
        if ($filter !== null && !($filter instanceof ListSecurityGroupsData)) {
            $filter = ListSecurityGroupsData::initArray($filter);
        }
        return $this->getApiHandler()->listSecurityGroups($filter, $pagination);
    }

}