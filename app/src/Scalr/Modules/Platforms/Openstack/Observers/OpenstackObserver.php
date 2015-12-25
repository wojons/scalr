<?php

namespace Scalr\Modules\Platforms\Openstack\Observers;

use Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper;
use Scalr\Observer\AbstractEventObserver;

class OpenstackObserver extends AbstractEventObserver
{
    public $ObserverName = 'Openstack';

    public function OnResumeComplete(\ResumeCompleteEvent $event) 
    {
        // We need to check and attach floating IP.
    }

    public function OnHostInit(\HostInitEvent $event) 
    {

        if (!$event->DBServer->isOpenstack() || $event->DBServer->platform == \SERVER_PLATFORMS::VERIZON)
            return;

        try {
            $dbServer = $event->DBServer;
            $environment = $dbServer->GetEnvironmentObject();
            $osClient = $environment->openstack($dbServer->platform, $dbServer->GetCloudLocation());

            if ($dbServer->farmId == 0)
                return;

            $tags = $dbServer->getOpenstackTags();

            $osClient->servers->updateServerMetadata($dbServer->GetCloudServerID(), $tags);

        } catch (\Exception $e) {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->error(
                new \FarmLogMessage($event->DBServer->farmId, sprintf(
                    _("Scalr was unable to add custom meta-data (tags) to the server '%s': %s (%s)"),
                    $event->DBServer->serverId,
                    $e->getMessage(),
                    \json_encode($tags)
                ), $event->DBServer->serverId)
            );
        }
    }

    public function OnBeforeHostTerminate(\BeforeHostTerminateEvent $event)
    {
        if (!$event->DBServer->isOpenstack())
            return;

        //DO NOT REMOVE FLOATING IP AT THIS POINT. MESSAGES WON'T BE DELIVERED
    }

    public function OnHostDown(\HostDownEvent $event)
    {
        if (!$event->DBServer->isOpenstack())
            return;

        // DO NOT remove Floating IP from suspended server.
        // Consider make this configurable
        if ($event->isSuspended)
            return; 

        OpenstackHelper::removeServerFloatingIp($event->DBServer);
    }
}
