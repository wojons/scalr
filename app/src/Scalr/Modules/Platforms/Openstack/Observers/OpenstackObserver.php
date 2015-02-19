<?php

namespace Scalr\Modules\Platforms\Openstack\Observers;

use Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper;

class OpenstackObserver extends \EventObserver
{
    public $ObserverName = 'Openstack';

    public function OnHostInit(\HostInitEvent $event) {
        
        if (!$event->DBServer->isOpenstack())
            return;
    
        try {
            $dbServer = $event->DBServer;
            $environment = $dbServer->GetEnvironmentObject();
            $osClient = $environment->openstack($dbServer->platform, $dbServer->GetCloudLocation());
    
            if ($dbServer->farmId == 0)
                return;
            
            $tags = array(
                "scalr-env-id" => $dbServer->envId,
                "scalr-owner" => $dbServer->GetFarmObject()->createdByUserEmail,
                "scalr-farm-id" => $dbServer->farmId,
                "scalr-farm-role-id" => $dbServer->farmRoleId,
                "scalr-server-id" => $dbServer->serverId
            );
    
            //Tags governance
            $governance = new \Scalr_Governance($dbServer->envId);
            $gTags = (array)$governance->getValue($dbServer->platform, \Scalr_Governance::OPENSTACK_TAGS);
            if (count($gTags) > 0) {
                foreach ($gTags as $tKey => $tValue) {
                    $tags[$tKey] = $dbServer->applyGlobalVarsToValue($tValue);
                }
            } else {
                //Custom tags
                $cTags = $dbServer->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_BASE_CUSTOM_TAGS);
                $tagsList = @explode("\n", $cTags);
                foreach ((array)$tagsList as $tag) {
                    $tag = trim($tag);
                    if ($tag) {
                        $tagChunks = explode("=", $tag);
                        $tags[trim($tagChunks[0])] = $dbServer->applyGlobalVarsToValue(trim($tagChunks[1]));
                    }
                }
            }
            
            $osClient->servers->updateServerMetadata($dbServer->GetCloudServerID(), $tags);
            
        } catch (\Exception $e) {
            \Logger::getLogger(\LOG_CATEGORY::FARM)->error(
                new \FarmLogMessage($event->DBServer->farmId, sprintf(
                    _("Scalr was unable to add custom meta-data (tags) to the server '%s': %s (%s)"),
                    $event->DBServer->serverId,
                    $e->getMessage(),
                    \json_encode($tags)
                ))
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

        OpenstackHelper::removeIpFromServer($event->DBServer);
    }
}
