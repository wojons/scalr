<?php

class Scalr_Scaling_Sensors_BandWidth extends Scalr_Scaling_Sensor
{
    const SETTING_BW_TYPE = 'type';

    public function __construct()
    {
        $this->db = \Scalr::getDb();
    }

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $curTime = time();
        $newData = [];
        $servers = $dbFarmRole->GetServersByFilter(['status' => SERVER_STATUS::RUNNING]);

        if (!empty($farmRoleMetric->dtLastPolled)) {
            $interval = $curTime - $farmRoleMetric->dtLastPolled;

            if ($interval < 1) {
                // The service was started less than one second ago
                return false;
            }
        }

        $mbitsPerInterface = [];

        foreach ($servers as $DBServer) {
            $type = $farmRoleMetric->getSetting(self::SETTING_BW_TYPE) == 'inbound' ? 'receive' : 'transmit';

            $netStat = (array)$DBServer->scalarizr->system->netStat();

            foreach ($netStat as $interface => $usage) {
                if ($interface != 'lo' && !empty($usage)) {
                    $totalBytes = round($usage->{$type}->bytes);
                    $dataKey = $DBServer->serverId . '-' . $interface . '-' . $type;
                    $newData[$dataKey] = $totalBytes;

                    if (isset($interval)) {
                        if (is_array($farmRoleMetric->lastData) && array_key_exists($dataKey, $farmRoleMetric->lastData)) {
                            $lastTotalBytes = intval($farmRoleMetric->lastData[$dataKey]);
                            $usedBytes = $totalBytes - $lastTotalBytes;

                            if ($usedBytes > 0) {
                                $usedMBits = ($usedBytes) * 8 / 1024 / 1024 / $interval;
                                array_push($mbitsPerInterface, round($usedMBits, 2));
                            } else {
                                // The last value is considered to be incorrect as the server has been restarted
                                $missStep = true;
                            }

                        } else {
                            // The last value hasn't been set yet
                            $missStep = true;
                        }
                    }
                }
            }
        }

        $farmRoleMetric->lastData = $newData;

        if (!isset($interval) || !empty($missStep)) {
            // Sets the Server time to avoid differences between Database and Server time
            $farmRoleMetric->dtLastPolled = $curTime;
            $farmRoleMetric->save(false, ['settings', 'metric_id']);

            return false;
        }

        return $mbitsPerInterface;
    }
}