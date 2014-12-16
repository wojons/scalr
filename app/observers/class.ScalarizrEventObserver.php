<?php

class ScalarizrEventObserver extends EventObserver
{
    public function OnBeforeHostUp(BeforeHostUpEvent $event) 
    {
        try {
            $hostname = $event->DBServer->scalarizr->system->getHostname();
        } catch (Exception $e) {}
        if ($hostname)
            $event->DBServer->SetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME, $hostname);
    }
    
    public function OnFarmTerminated(FarmTerminatedEvent $event)
    {
        $farmRoles = $event->DBFarm->GetFarmRoles();
        foreach ($farmRoles as $farmRole) {
            // For MySQL role need to reset slave2master flag
            if ($farmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL)) {
                $farmRole->SetSetting(DBFarmRole::SETTING_MYSQL_SLAVE_TO_MASTER, 0, DBFarmRole::TYPE_LCL);
            }

            if ($farmRole->GetRoleObject()->getDbMsrBehavior()) {
                $farmRole->SetSetting(Scalr_Db_Msr::SLAVE_TO_MASTER, 0, DBFarmRole::TYPE_LCL);
            }
        }
    }
}
