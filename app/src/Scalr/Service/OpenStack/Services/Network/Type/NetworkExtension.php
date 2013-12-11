<?php

namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\StringType;

/**
 * NetworkExtension
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    26.08.2012
 */
class NetworkExtension extends StringType
{
    const EXT_AGENT_SCHEDULERS         = 'agent_scheduler';
    const EXT_SECURITY_GROUP           = 'security-group';
    const EXT_PORT_BINDING             = 'binding';
    const EXT_QUOTA_MANAGEMENT_SUPPORT = 'quotas';
    const EXT_AGENT                    = 'agent';
    const EXT_PROVIDER_NETWORK         = 'provider';
    const EXT_QUANTUM_L3_ROUTER        = 'router';
    const EXT_LOADBALANCING_SERVICE    = 'lbaas';
    const EXT_QUANTUM_EXTRA_ROUTE      = 'extraroute';

    public static function getPrefix()
    {
        return 'EXT_';
    }
}