<?php
namespace Scalr\Service\OpenStack\Services\Network\Handler;

use Scalr\Service\OpenStack\Services\ServiceHandlerInterface;
use Scalr\Service\OpenStack\Services\AbstractServiceHandler;

/**
 * SecurityGroups handler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.08.2014)
 *
 * @method   \Scalr\Service\OpenStack\Services\NetworkService getService()
 *           getService()
 *           Gets a service instance
 *
 * @method   \Scalr\Service\OpenStack\Type\DefaultPaginationList|object list()
 *           list(string $id = null, \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupsFilter|array $filter = null, array $fields = null)
 *           List Security Groups action (GET /security-groups[/security-group-id])
 *
 * @method   object create()
 *           create(string $name, string $description = null)
 *           Creates Security Group (POST /security-groups)
 *
 * @method   bool delete()
 *           delete(string $securityGroupId)
 *           Deletes security group (DELETE /security-groups/security-group-id)
 *
 * @method   \Scalr\Service\OpenStack\Type\DefaultPaginationList|object listRules()
 *           listRules(string $id = null, \Scalr\Service\OpenStack\Services\Network\Type\ListSecurityGroupRulesFilter|array $filter = null, array $fields = null)
 *           List Security Group Rules that are accessible for the tenant
 *
 * @method   object addRule()
 *           addRule(\Scalr\Service\OpenStack\Services\Network\Type\CreateSecurityGroupRule|array $request)
 *           Creates a new rule associated with the security group
 *
 * @method   bool deleteRule()
 *           deleteRule(string $id)
 *           Deletes security group rule with the specified identifier
 */
class SecurityGroupsHandler extends AbstractServiceHandler implements ServiceHandlerInterface
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceHandlerInterface::getServiceMethodAliases()
     */
    public function getServiceMethodAliases()
    {
        return array(
            'list'       => 'listSecurityGroups',
            'create'     => 'createSecurityGroup',
            'delete'     => 'deleteSecurityGroup',
            'listRules'  => 'listSecurityGroupRules',
            'addRule'    => 'createSecurityGroupRule',
            'deleteRule' => 'deleteSecurityGroupRule',
        );
    }
}