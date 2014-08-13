<?php
namespace Scalr\Service\OpenStack\Services\Network\Handler;

use Scalr\Service\OpenStack\Services\ServiceHandlerInterface;
use Scalr\Service\OpenStack\Services\AbstractServiceHandler;

/**
 * LBaaS VipsHandler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (14.01.2014)
 *
 * @method   \Scalr\Service\OpenStack\Services\NetworkService getService()
 *           getService()
 *           Gets a service instance
 *
 * @method   \Scalr\Service\OpenStack\Type\DefaultPaginationList|object list()
 *           list(string $vipId = null, \Scalr\Service\OpenStack\Services\Network\Type\ListLbVipsFilter|array $filter = null)
 *           List VIPs action (GET /lb/vips[/vip-id])
 *
 * @method   object create()
 *           create(\Scalr\Service\OpenStack\Services\Network\Type\CreateLbVip|array $request)
 *           Creates LBaaS vip (POST /lb/vips)
 *
 * @method   object update()
 *           update(string $vipId, array|object $options)
 *           Updates LBaaS VIP (PUT /lb/vips/vip-id)
 *
 * @method   bool delete()
 *           delete(string $vipId)
 *           Deletes LBaaS VIP (DELETE /lb/vips/vip-id)
 */
class LbVipsHandler extends AbstractServiceHandler implements ServiceHandlerInterface
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceHandlerInterface::getServiceMethodAliases()
     */
    public function getServiceMethodAliases()
    {
        return array(
            'list'   => 'listLbVips',
            'create' => 'createLbVip',
            'delete' => 'deleteLbVip',
            'update' => 'updateLbVip',
        );
    }
}