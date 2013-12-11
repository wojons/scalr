<?php
namespace Scalr\Service\OpenStack\Services\Network\Handler;

use Scalr\Service\OpenStack\Services\NetworkService;
use Scalr\Service\OpenStack\Services\ServiceHandlerInterface;
use Scalr\Service\OpenStack\Services\AbstractServiceHandler;

/**
 * Quantum API FloatingIpsHandler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    27.09.2013
 *
 * @method   array    list()
 *           list()
 *           Gets the list of floating ips.
 *
 * @method   object   get()
 *           get(string $floatingIpId)
 *           Gets floating Ip details.
 *
 * @method   object   update()
 *           update(string $floatingIpId, string $portId = null)
 *           Associates/Disassociates quantum port with the floating IP address.
 *
 * @method   object   create()
 *           create(string $floatingNetworkId, string $portId = null)
 *           Creates a new floating IP address.
 *
 * @method   bool     delete()
 *           delete(string $floatingIpId)
 *           Deletes floating IP address
 */
class FloatingIpsHandler extends AbstractServiceHandler implements ServiceHandlerInterface
{
    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceHandlerInterface::getServiceMethodAliases()
     */
    public function getServiceMethodAliases()
    {
        return array(
            'list'   => 'listFloatingIps',
            'get'    => 'getFloatingIp',
            'create' => 'createFloatingIp',
            'delete' => 'deleteFloatingIp',
            'update' => 'updateFloatingIp',
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.AbstractServiceHandler::getService()
     * @return  ServersService
     */
    public function getService()
    {
        return parent::getService();
    }
}