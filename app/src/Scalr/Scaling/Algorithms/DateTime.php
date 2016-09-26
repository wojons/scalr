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

        $currentDate = array((int)$date->format("Hi"), $date->format("D"), $date->format("H"), $date->format("i"));

        $scalingPeriod = $this->db->GetRow("
            SELECT * FROM farm_role_scaling_times 
            WHERE ? BETWEEN start_time AND end_time
            AND INSTR(days_of_week, ?) != 0 
            AND farm_roleid = ? 
            LIMIT 1
        ", [
            $currentDate[0],
            $currentDate[1],
            $dbFarmRole->ID
        ]);
        
        $minInstances = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);

        if ($scalingPeriod) {
            $this->logger->info("TimeScalingAlgo({$dbFarmRole->FarmID}, {$dbFarmRole->ID}) Found scaling period. Total {$scalingPeriod['instances_count']} instances should be running.");

            $this->instancesNumber = $scalingPeriod['instances_count'];
            $farmRoleRunningInstances = $dbFarmRole->GetRunningInstancesCount();
            $farmRolePendingInstances = $dbFarmRole->GetPendingInstancesCount();
            $roleTotalInstances = $farmRoleRunningInstances + $farmRolePendingInstances;
            
            if ($roleTotalInstances == $this->instancesNumber) {
                return Scalr_Scaling_Decision::NOOP;
            } else {
                $this->lastValue = sprintf("Current time '%s' matching rule '%s'. %s out of %s servers is currently running (pending)",
                    "{$currentDate[1]}, {$currentDate[2]}:{$currentDate[3]}",
                    "{$scalingPeriod['days_of_week']}: {$scalingPeriod['start_time']} - {$scalingPeriod['end_time']}",
                    $roleTotalInstances,
                    $this->instancesNumber
                );
                
                if ($roleTotalInstances < $this->instancesNumber) {
                    return Scalr_Scaling_Decision::UPSCALE;
                } elseif ($roleTotalInstances > $this->instancesNumber) {
                    return Scalr_Scaling_Decision::DOWNSCALE;
                }   
            }
        } else {
            $this->instancesNumber = $minInstances;
            
            if ($roleTotalInstances > $minInstances) {
                $this->lastValue = sprintf("Current time '%s' has no matching rule. Maintaining minimum servers count (%s). %s out of %s servers is currently running (pending)",
                    "{$currentDate[1]}, {$currentDate[2]} {$currentDate[3]}",
                    $minInstances,
                    $roleTotalInstances,
                    $minInstances
                );
                
                return Scalr_Scaling_Decision::DOWNSCALE;
            } else {
                return Scalr_Scaling_Decision::NOOP;
            }
        }
    }
}
