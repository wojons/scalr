<?php

use Scalr\Exception\InvalidEntityConfigurationException;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmRoleSetting;

class Scalr_Role_Behavior_MySql extends Scalr_Role_DbMsrBehavior implements Scalr_Role_iBehavior
{
    /** DBFarmRole settings **/


    //In Scalr_Db_Msr

    public function __construct($behaviorName)
    {
        parent::__construct($behaviorName);
    }

    public function getSecurityRules()
    {
        return ["tcp:3306:3306:0.0.0.0/0"];
    }

    /*
    public function handleMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
    {
        switch (get_class($message))
        {
            case "Scalr_Messaging_Msg_HostUp":

                if ($message->redis->volumeConfig)
                    $this->setVolumeConfig($message->cfCloudController->volumeConfig, $dbServer->GetFarmRoleObject(), $dbServer);
                else
                    throw new Exception("Received hostUp message from CF Cloud Controller server without volumeConfig");

                break;
        }
    }

    public function extendMessage(Scalr_Messaging_Msg $message, DBServer $dbServer)
    {
        $message = parent::extendMessage($message);



        return $message;
    }
    */

    public static function setupBehavior(FarmRole $farmRole)
    {
        if ($farmRole->platform == SERVER_PLATFORMS::EC2) {
            if ($farmRole->settings[FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE] == MYSQL_STORAGE_ENGINE::EBS) {

                if ($farmRole->role->generation != 2) {
                    $availabilityZones = $farmRole->settings[FarmRoleSetting::AWS_AVAIL_ZONE];
                    if ($availabilityZones == "" ||
                        $availabilityZones == "x-scalr-diff" ||
                        stristr($availabilityZones, 'x-scalr-custom')
                    ) {
                        throw new InvalidEntityConfigurationException("Requirement for EBS MySQL data storage is specific 'Placement' parameter for role '{$farmRole->role->name}'");
                    }
                }
            }
        }
    }
}