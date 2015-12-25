<?php

use Scalr\Model\Entity;

class ScalrEnvironment20150410 extends ScalrEnvironment20120701
{
    public function ListFarmRoleParamsJson()
    {
        $farmRoleId = $this->GetArg("farm-role-id");
        if (!$farmRoleId)
            throw new Exception("'farm-role-id' required");

        $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
        if ($dbFarmRole->FarmID != $this->DBServer->farmId)
            throw new Exception("You can request this information ONLY for roles within server farm");

        $result = new stdClass();

        // Base configuration
        if ($this->DBServer->farmRoleId == $farmRoleId) {
            $data = Scalr_Role_Behavior::loadByName(ROLE_BEHAVIORS::BASE)->getBaseConfiguration($this->DBServer);
            
            foreach ((array)$data as $k => $v)
                $result->{$k} = $v;
        }

        $role = $dbFarmRole->GetRoleObject();
        $behaviors = $role->getBehaviors();
        foreach ($behaviors as $behavior) {
            $data = null;

            if ($behavior == ROLE_BEHAVIORS::MONGODB || $behavior == ROLE_BEHAVIORS::CHEF || $behavior == ROLE_BEHAVIORS::HAPROXY ||
                $behavior == ROLE_BEHAVIORS::NGINX || $behavior == ROLE_BEHAVIORS::RABBITMQ || $behavior == ROLE_BEHAVIORS::APACHE ||
                $behavior == ROLE_BEHAVIORS::VPC_ROUTER) {
                $data = Scalr_Role_Behavior::loadByName($behavior)->getConfiguration($this->DBServer);
            }

            if ($data === null) {
                if ($behavior == ROLE_BEHAVIORS::MYSQL) {
                    $data = new stdClass();
                    $data->logFile = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LOG_FILE);
                    $data->logPos = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_LOG_POS);
                    $data->rootPassword = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_ROOT_PASSWORD);
                    $data->replPassword = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_REPL_PASSWORD);
                    $data->statPassword = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_STAT_PASSWORD);
                    $data->replicationMaster = (int)$this->DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER);
                } else {
                    try {
                        $dbMsrInfo = Scalr_Db_Msr_Info::init($dbFarmRole, $this->DBServer, $behavior);
                        $data = $dbMsrInfo->getMessageProperties();
                    } catch (Exception $e) {}
                }
            }

            if ($data)
                $result->{$behavior} = $data;
        }

        return $result;
    }
}
