<?php

class Scalr_Scaling_Sensors_BandWidth extends Scalr_Scaling_Sensor
{
    const SETTING_BW_TYPE = 'type';
    const SETTING_BW_LAST_VALUE_RAW = 'raw_last_value';

    public function __construct()
    {
        $this->db = \Scalr::getDb();
    }

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
        $DBFarm = $dbFarmRole->GetFarmObject();

        if (count($servers) == 0)
            return 0;

        $roleBWRaw = array();
        $retval = array();

        foreach ($servers as $DBServer) {
            $type = $farmRoleMetric->getSetting(self::SETTING_BW_TYPE) == 'inbound' ? 'receive' : 'transmit';            

            $netStat = (array)$DBServer->scalarizr->system->netStat();
            foreach ($netStat as $interface => $usage) {
                if ($interface != 'lo')
                    break;
            }

            if ($usage)
                array_push($roleBWRaw, round($usage->{$type}->bytes / 1024 / 1024, 2));
        }

        $roleBW = round(array_sum($roleBWRaw) / count($roleBWRaw), 2);

        if ($farmRoleMetric->getSetting(self::SETTING_BW_LAST_VALUE_RAW) !== null &&
            $farmRoleMetric->getSetting(self::SETTING_BW_LAST_VALUE_RAW) !== '') {
            $time = time() - $farmRoleMetric->dtLastPolled;

            $bandwidthUsage = ($roleBW - (float)$farmRoleMetric->getSetting(self::SETTING_BW_LAST_VALUE_RAW)) * 8;

            $bandwidthChannelUsage = $bandwidthUsage/$time; // in Mbits/sec

            $retval = round($bandwidthChannelUsage, 2);
        } else {
            $retval = 0;
        }

        $farmRoleMetric->setSetting(self::SETTING_BW_LAST_VALUE_RAW, $roleBW);

        return array($retval);
    }
}