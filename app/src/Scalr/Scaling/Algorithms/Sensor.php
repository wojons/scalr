<?php

use Scalr\Model\Entity;

class Scalr_Scaling_Algorithms_Sensor
{
    public function __construct()
    {
        $this->logger = \Scalr::getContainer()->logger(get_class($this));
    }

    public function makeDecision(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric, $isInvert = false)
    {
        if ($farmRoleMetric->lastValue > $farmRoleMetric->getSetting('max')) {
            $retval = Scalr_Scaling_Decision::UPSCALE;
        } elseif ($farmRoleMetric->lastValue < $farmRoleMetric->getSetting('min')) {
            $retval = Scalr_Scaling_Decision::DOWNSCALE;
        }

        if (empty($retval)) {
            $retval = Scalr_Scaling_Decision::NOOP;
        } else {
            if ($isInvert) {
                if ($retval == Scalr_Scaling_Decision::UPSCALE) {
                    $retval = Scalr_Scaling_Decision::DOWNSCALE;
                } else {
                    $retval = Scalr_Scaling_Decision::UPSCALE;
                }
            }

            if ($retval == Scalr_Scaling_Decision::UPSCALE) {
                if (($dbFarmRole->GetRunningInstancesCount() + $dbFarmRole->GetPendingInstancesCount()) >= $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES)) {
                    $retval = Scalr_Scaling_Decision::NOOP;
                }
            }

            if ($retval == Scalr_Scaling_Decision::DOWNSCALE) {
                if ($dbFarmRole->GetRunningInstancesCount() <= $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES)) {
                    $retval = Scalr_Scaling_Decision::NOOP;
                }
            }
        }
        
        return $retval;
    }
}