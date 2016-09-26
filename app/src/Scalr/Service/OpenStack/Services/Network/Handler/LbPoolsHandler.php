<?php
namespace Scalr\Service\OpenStack\Services\Network\Handler;

use Scalr\Service\OpenStack\Services\ServiceHandlerInterface;
use Scalr\Service\OpenStack\Services\AbstractServiceHandler;

/**
 * LBaaS PoolsHandler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (15.01.2014)
 *
 * @method   \Scalr\Service\OpenStack\Services\NetworkService getService()
 *           getService()
 *           Gets a service instance
 *
 * @method   \Scalr\Service\OpenStack\Type\DefaultPaginationList|object list()
 *           list(string $poolId = null, \Scalr\Service\OpenStack\Services\Network\Type\ListLbPoolsFilter|array $filter = null)
 *           List Pools action (GET /lb/pools[/pool-id])
 *
 * @method   object create()
 *           create(\Scalr\Service\OpenStack\Services\Network\Type\CreateLbPool|array $request)
 *           Creates LBaaS pool (POST /lb/pools)
 *
 * @method   bool delete()
 *           delete(string $poolId)
 *           Deletes LBaaS pool (DELETE /lb/pools/pool-id)
 *
 * @method   object update()
 *           update(string $poolId, array|object $options)
 *           Updates LBaaS pool (PUT /lb/pools/pool-id)
 *
 * @method   object associateHealthMonitor()
 *           associateHealthMonitor(string $poolId, string $healthMonitorId)
 *           Associates health monitor with the pool (POST /lb/pools/pool-id/health_monitors)
 *
 * @method   object disassociateHealthMonitor()
 *           disassociateHealthMonitor(string $poolId, string $healthMonitorId)
 *           Disassociates health monitor from a pool (DELETE /lb/pools/pool-id/health_monitors/healthmonitor-id)
 */
class LbPoolsHandler extends AbstractServiceHandler implements ServiceHandlerInterface
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceHandlerInterface::getServiceMethodAliases()
     */
    public function getServiceMethodAliases()
    {
        return array(
            'list'                      => 'listLbPools',
            'create'                    => 'createLbPool',
            'delete'                    => 'deleteLbPool',
            'update'                    => 'updateLbPool',
            'associateHealthMonitor'    => 'associateLbHealthMonitorWithPool',
            'disassociateHealthMonitor' => 'disassociateLbHealthMonitorFromPool',
        );
    }
}