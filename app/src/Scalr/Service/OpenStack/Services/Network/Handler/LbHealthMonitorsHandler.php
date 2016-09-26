<?php
namespace Scalr\Service\OpenStack\Services\Network\Handler;

use Scalr\Service\OpenStack\Services\ServiceHandlerInterface;
use Scalr\Service\OpenStack\Services\AbstractServiceHandler;

/**
 * LBaaS Health Monitors handler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (16.01.2014)
 *
 * @method   \Scalr\Service\OpenStack\Services\NetworkService getService()
 *           getService()
 *           Gets a service instance
 *
 * @method   \Scalr\Service\OpenStack\Type\DefaultPaginationList|object list()
 *           list(string $healthMonitorId = null, \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter|array $filter = null)
 *           List LBaaS Health Monitors action (GET /lb/health_monitors[/health-monitor-id])
 *
 * @method   object create()
 *           create(\Scalr\Service\OpenStack\Services\Network\Type\CreateLbHealthMonitor|array $request)
 *           Creates LBaaS health monitor (POST /lb/health_monitors)
 *
 * @method   bool delete()
 *           delete(string $healthMonitorId)
 *           Deletes LBaaS health monitor (DELETE /lb/health_monitors/health-monitor-id)
 *
 * @method   object update()
 *           update(string $healthMonitorId, array|object $options)
 *           Updates LBaas health monitor (PUT /lb/health_monitors/health-monitor-id)
 */
class LbHealthMonitorsHandler extends AbstractServiceHandler implements ServiceHandlerInterface
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceHandlerInterface::getServiceMethodAliases()
     */
    public function getServiceMethodAliases()
    {
        return array(
            'list'   => 'listLbHealthMonitors',
            'create' => 'createLbHealthMonitor',
            'delete' => 'deleteLbHealthMonitor',
            'update' => 'updateLbHealthMonitor',
        );
    }
}