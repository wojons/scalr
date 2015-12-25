<?php

use Scalr\Model\Entity;

class Scalr_Scaling_Sensors_FreeRam extends Scalr_Scaling_Sensor
{
    const SETTING_USE_CACHED = 'use_cached';

    /**
     * FreeRam sensor has inverted logic
     *
     * @var bool
     */
    public $isInvert = true;

    public function __construct()
    {
        
    }

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
        $dbFarm = $dbFarmRole->GetFarmObject();

        if (count($servers) == 0) {
            return false;
        }

        $retval = array();

        foreach ($servers as $DBServer) {
            if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_EXCLUDE_DBMSR_MASTER) == 1) {
                $isMaster = ($DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER) == 1 ||
                             $DBServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER) == 1);

                if ($isMaster) {
                    continue;
                }
            }

            $ramUsage = $DBServer->scalarizr->system->memInfo();
            $ram = (float)$ramUsage->total_free;

            if ($farmRoleMetric->getSetting(self::SETTING_USE_CACHED)) {
                $ram = $ram+(float)$ramUsage->cached;
            }

            $retval[] = round($ram/1024, 2);
        }

        return $retval;
    }
}