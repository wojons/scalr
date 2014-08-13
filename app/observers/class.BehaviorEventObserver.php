<?php

class BehaviorEventObserver extends EventObserver
{
    public function OnBeforeInstanceLaunch(BeforeInstanceLaunchEvent $event)
    {
        $dbServer = $event->DBServer;

        if ($dbServer->farmRoleId != 0) {
            foreach (Scalr_Role_Behavior::getListForFarmRole($dbServer->GetFarmRoleObject()) as $bObj) {
                $bObj->onBeforeInstanceLaunch($dbServer);
            }
        }
    }

    public function OnRebootComplete(RebootCompleteEvent $event) {
        $dbServer = $event->DBServer;
        if ($dbServer->IsSupported('0.23.0')) {
            try {
                $hostname = $dbServer->scalarizr->system->getHostname();
            } catch (Exception $e) {}
            if ($hostname)
                $dbServer->SetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME, $hostname);
        }
    }

    public function OnFarmTerminated(FarmTerminatedEvent $event)
    {
        $dbFarm = DBFarm::LoadByID($this->FarmID);
        foreach ($dbFarm->GetFarmRoles() as $dbFarmRole) {
            foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $bObj) {
                $bObj->onFarmTerminated($dbFarmRole);
            }
        }
    }

    public function OnBeforeHostTerminate(BeforeHostTerminateEvent $event)
    {
        $dbServer = $event->DBServer;
        if ($dbServer->farmRoleId != 0) {
            try {
                $dbFarmRole = $dbServer->GetFarmRoleObject();
            } catch (Exception $e) {
                return false;
            }

            foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $bObj) {
                $bObj->onBeforeHostTerminate($dbServer);
            }
        }
    }

    public function OnHostDown(HostDownEvent $event)
    {
        $dbServer = $event->DBServer;
        if ($dbServer->farmRoleId != 0) {
            try {
                $dbFarmRole = $dbServer->GetFarmRoleObject();
            } catch (Exception $e) {
            	return false;
            }

            foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $bObj) {
                $bObj->onHostDown($dbServer);
            }
        }
    }
}
