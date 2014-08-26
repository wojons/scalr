<?php

class Scalr_Scaling_Sensors_Http extends Scalr_Scaling_Sensor
{
    const SETTING_URL = 'url';

    public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric)
    {
        $start_time = microtime(true);

        $HttpRequest = new HttpRequest();

        $HttpRequest->setOptions(array(
            "redirect" => 10,
            "useragent" => "Scalr (http://scalr.net) HTTPResponseTime Scaling Sensor",
            "connecttimeout" => 10
        ));
        $HttpRequest->setUrl($farmRoleMetric->getSetting(self::SETTING_URL));
        $HttpRequest->setMethod(constant("HTTP_METH_GET"));

        try {
            $HttpRequest->send();
        } catch (Exception $e) {
            if ($e->innerException)
                $message = $e->innerException->getMessage();
            else
                $message = $e->getMessage();

            throw new Exception("HTTPResponseTime Scaling Sensor cannot get value: {$message}");
        }

        $retval = round(microtime(true) - $start_time, 2);

        return array($retval);
    }
}