<?php

namespace Scalr\Modules\Platforms\Openstack\Helpers;


class OpenstackHelper
{
    public static function removeIpFromServer(\DBServer $dbServer)
    {
        try {
            if ($dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::FLOATING_IP)) {
                $environment = $dbServer->GetEnvironmentObject();
                $osClient = $environment->openstack($dbServer->platform, $dbServer->GetCloudLocation());

                $ipId = $dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID);

                if ($osClient->hasService('network')) {
                    $osClient->network->floatingIps->delete($ipId);
                } else {
                    $osClient->servers->deleteFloatingIp($ipId);
                }

                $dbServer->SetProperties(array(
                    \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => null,
                    \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => null
                ));
            }
        } catch (\Exception $e) {
            \Logger::getLogger("OpenStackObserver")->fatal("OpenStackObserver observer failed: " . $e->getMessage());
        }
    }
}

