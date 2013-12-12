<?php

class Modules_Platforms_Openstack_Observers_Openstack extends EventObserver
{
    public $ObserverName = 'Openstack';

    private function cleanupFloatingIps(DBServer $dbServer)
    {
        try {
            if ($dbServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::FLOATING_IP)) {
                $environment = $dbServer->GetEnvironmentObject();
                $osClient = $environment->openstack($dbServer->platform, $dbServer->GetCloudLocation());

                $ipId = $dbServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID);

                if ($osClient->hasService('network')) {
                    $osClient->network->floatingIps->delete($ipId);
                } else {
                    $osClient->servers->deleteFloatingIp($ipId);
                }

                $dbServer->SetProperties(array(
                    OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => null,
                    OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => null
                ));
            }
        } catch (Exception $e) {
            Logger::getLogger("OpenStackObserver")->fatal("OpenStackObserver observer failed: " . $e->getMessage());
        }
    }

    public function OnBeforeHostTerminate(BeforeHostTerminateEvent $event)
    {
        if (!$event->DBServer->isOpenstack())
            return;

        $this->cleanupFloatingIps($event->DBServer);
    }

    public function OnHostDown(HostDownEvent $event) {
        if (!$event->DBServer->isOpenstack())
            return;

        $this->cleanupFloatingIps($event->DBServer);
    }
}
