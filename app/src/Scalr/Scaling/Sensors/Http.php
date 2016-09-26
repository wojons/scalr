<?php

use Scalr\System\Http\Client\Request;

class Scalr_Scaling_Sensors_Http extends Scalr_Scaling_Sensor
{
    const SETTING_URL = 'url';

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $start_time = microtime(true);

        $HttpRequest = new Request("GET", $farmRoleMetric->getSetting(self::SETTING_URL));

        $HttpRequest->setOptions([
            "redirect"       => 10,
            "connecttimeout" => 10
        ]);

        try {
            \Scalr::getContainer()->http->sendRequest($HttpRequest);
        } catch (Exception $e) {
            if ($e->innerException) {
                $message = $e->innerException->getMessage();
            } else {
                $message = $e->getMessage();
            }

            throw new Exception("HTTPResponseTime Scaling Sensor cannot get value: {$message}");
        }

        $retval = round(microtime(true) - $start_time, 2);

        return array($retval);
    }
}