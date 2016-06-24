<?php

use Scalr\Model\Entity;

class Scalr_Scaling_Sensors_LoadAverage extends Scalr_Scaling_Sensor
{
    const SETTING_LA_PERIOD = 'period';

    public function __construct()
    {

    }

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
        $dbFarm = $dbFarmRole->GetFarmObject();

        $roleLA = 0;

        if (count($servers) == 0)
            return false;

        $retval = array();

        foreach ($servers as $DBServer) {
            if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_EXCLUDE_DBMSR_MASTER) == 1) {
                $isMaster = ($DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER) == 1 ||
                             $DBServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER) == 1);

                if ($isMaster) {
                    continue;
                }
            }

            try {
                $period = $farmRoleMetric->getSetting(self::SETTING_LA_PERIOD);
                $index = 0;

                if ($period == 15)
                    $index = 2;
                elseif ($period == 5)
                    $index = 1;

                $la = $DBServer->scalarizr->system->loadAverage();

                if ($la[$index] !== null && $la[$index] !== false) {
                    $la = (float)number_format($la[$index], 2);
                }

                $retval[] = $la;
            } catch (Exception $e) {
                \Scalr::getContainer()->logger(__CLASS__)->warn(new FarmLogMessage(
                    $DBServer,
                    sprintf("Unable to read LoadAverage value from server %s: %s",
                        $DBServer->getNameByConvention(),
                        $e->getMessage()
                    )
                ));
            }
        }

        return count($retval) > 0 ? $retval : false;
    }
}