<?php

namespace Scalr\Modules\Platforms\Openstack\Helpers;


class OpenstackHelper
{
    public static function removeIpFromServer(\DBServer $dbServer)
    {
        try {
            if ($dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::FLOATING_IP)) {
                
                if ($dbServer->farmRoleId) {
                    if ($dbServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_OPENSTACK_KEEP_FIP_ON_SUSPEND)) {
                        if (in_array($dbServer->status, array(\SERVER_STATUS::PENDING_SUSPEND,\SERVER_STATUS::SUSPENDED)) || 
                            $dbServer->GetRealStatus()->isSuspended()) {
                            return false;
                        }
                    }
                }
                
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

