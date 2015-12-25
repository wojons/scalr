<?php

use Scalr\Model\Entity;

class Scalr_Scaling_Algorithms_DateTime
{
    public $instancesNumber;

    public function __construct()
    {
        $this->logger = \Scalr::getContainer()->logger(get_class($this));
        $this->db = \Scalr::getDb();
    }

    public function makeDecision(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric, $isInvert = false)
    {
        // Get data from BW sensor
        $dbFarm = $dbFarmRole->GetFarmObject();

        $tz = $dbFarm->GetSetting(Entity\FarmSetting::TIMEZONE);

        $date = new DateTime();

        if ($tz) {
            $date->setTimezone(new DateTimeZone($tz));
        }

        $currentDate = array((int)$date->format("Hi"), $date->format("D"));

        $scaling_period = $this->db->GetRow("
            SELECT * FROM farm_role_scaling_times
            WHERE '{$currentDate[0]}' >= start_time
            AND '{$currentDate[0]}' <= end_time
            AND INSTR(days_of_week, '{$currentDate[1]}') != 0
            AND farm_roleid = '{$dbFarmRole->ID}'
            LIMIT 1
        ");

        if ($scaling_period) {
            $this->logger->info("TimeScalingAlgo({$dbFarmRole->FarmID}, {$dbFarmRole->ID}) Found scaling period. Total {$scaling_period['instances_count']} instances should be running.");

            $this->instancesNumber = $scaling_period['instances_count'];

            $this->lastValue = "(" . implode(' / ', $currentDate) . ") {$scaling_period['start_time']} - {$scaling_period['end_time']} = {$scaling_period['instances_count']}";

            if (($dbFarmRole->GetRunningInstancesCount()+$dbFarmRole->GetPendingInstancesCount()) < $this->instancesNumber) {
                return Scalr_Scaling_Decision::UPSCALE;
            } elseif (($dbFarmRole->GetRunningInstancesCount()+$dbFarmRole->GetPendingInstancesCount()) > $this->instancesNumber) {
                return Scalr_Scaling_Decision::DOWNSCALE;
            } else {
                return Scalr_Scaling_Decision::NOOP;
            }
        } else {
            if ($dbFarmRole->GetRunningInstancesCount() > $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES)) {
                $this->lastValue = "No period defined. Using Min instances setting.";
                return Scalr_Scaling_Decision::DOWNSCALE;
            } else {
                return Scalr_Scaling_Decision::NOOP;
            }
        }
    }
}
