<?php
namespace Scalr\Observer;

use Scalr\Model\Entity;

class ScalarizrEventObserver extends AbstractEventObserver
{
    public $isScalarizrRequired = true;

    public function OnBeforeHostUp(\BeforeHostUpEvent $event)
    {
        try {
            $hostname = $event->DBServer->scalarizr->system->getHostname();
        } catch (\Exception $e) {}
        if ($hostname)
            $event->DBServer->SetProperty(\Scalr_Role_Behavior::SERVER_BASE_HOSTNAME, $hostname);
    }
    
    public function OnFarmTerminated(\FarmTerminatedEvent $event)
    {
        $farmRoles = $event->DBFarm->GetFarmRoles();
        foreach ($farmRoles as $farmRole) {
            // For MySQL role need to reset slave2master flag
            if ($farmRole->GetRoleObject()->hasBehavior(\ROLE_BEHAVIORS::MYSQL)) {
                $farmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_SLAVE_TO_MASTER, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }

            if ($farmRole->GetRoleObject()->getDbMsrBehavior()) {
                $farmRole->SetSetting(\Scalr_Db_Msr::SLAVE_TO_MASTER, 0, Entity\FarmRoleSetting::TYPE_LCL);
            }
        }
    }
}
