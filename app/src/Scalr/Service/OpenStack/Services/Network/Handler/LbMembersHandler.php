<?php
namespace Scalr\Service\OpenStack\Services\Network\Handler;

use Scalr\Service\OpenStack\Services\ServiceHandlerInterface;
use Scalr\Service\OpenStack\Services\AbstractServiceHandler;

/**
 * LBaaS MembersHandler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (14.01.2014)
 *
 * @method   \Scalr\Service\OpenStack\Services\NetworkService getService()
 *           getService()
 *           Gets a service instance
 *
 * @method   \Scalr\Service\OpenStack\Type\DefaultPaginationList|object list()
 *           list(string $memberId = null, \Scalr\Service\OpenStack\Services\Network\Type\ListLbMembersFilter|array $filter = null)
 *           List Members action (GET /lb/members[/member-id])
 *
 * @method   object create()
 *           create(\Scalr\Service\OpenStack\Services\Network\Type\CreateLbMember|array $request)
 *           Creates LBaaS member (POST /lb/members)
 *
 * @method   bool delete()
 *           delete(string $memberId)
 *           Deletes LBaaS member (DELETE /lb/members/member-id)
 *
 * @method   object update()
 *           update(string $memberId, array|object $options)
 *           Updates LBaaS member (PUT /lb/members/member-id)
 */
class LbMembersHandler extends AbstractServiceHandler implements ServiceHandlerInterface
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceHandlerInterface::getServiceMethodAliases()
     */
    public function getServiceMethodAliases()
    {
        return array(
            'list'   => 'listLbMembers',
            'create' => 'createLbMember',
            'delete' => 'deleteLbMember',
            'update' => 'updateLbMember',
        );
    }
}