<?php

class Scalr_Scaling_Sensors_Custom extends Scalr_Scaling_Sensor
{
    public function __construct()
    {
    }

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
        $dbFarm = $dbFarmRole->GetFarmObject();

        $retval = array();

        if (count($servers) == 0) {
            return array();
        }

        foreach ($servers as $dbServer) {
            $metrics = $dbServer->scalarizr->system->scalingMetrics();
            foreach ($metrics as $metric) {
                if ($metric->id == $farmRoleMetric->metricId) {
                    if ($metric->error) {
                        \Scalr::getContainer()->logger(__CLASS__)->warn(new FarmLogMessage(
                            $dbServer->farmId,
                            sprintf("Unable to read '%s' value from server %s: %s",
                                $metric->name,
                                $dbServer->getNameByConvention(),
                                $metric->error
                            ),
                            $dbServer->serverId
                        ));
                    } else {
                        $retval[] = $metric->value;
                    }

                    break;
                }
            }
        }

        return $retval;
    }
}