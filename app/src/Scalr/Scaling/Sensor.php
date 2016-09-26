<?php

abstract class Scalr_Scaling_Sensor
{

    private static $sensors = array();

    /**
     * Whether this sensor has inverted logic
     *
     * @var bool
     */
    public $isInvert = false;

    /**
     * Gets metric
     *
     * @param     string    $metricAlias  An alias
     * @return    Scalr_Scaling_Sensor
     */
    public static function get($metricAlias)
    {
        if (empty(self::$sensors[$metricAlias])) {
            switch ($metricAlias) {
                case "la":
                    self::$sensors[$metricAlias] = new Scalr_Scaling_Sensors_LoadAverage();
                    break;

                case "bw":
                    self::$sensors[$metricAlias] = new Scalr_Scaling_Sensors_BandWidth();
                    break;

                case "custom":
                    self::$sensors[$metricAlias] = new Scalr_Scaling_Sensors_Custom();
                    break;

                case "sqs":
                    self::$sensors[$metricAlias] = new Scalr_Scaling_Sensors_Sqs();
                    break;

                case "http":
                    self::$sensors[$metricAlias] = new Scalr_Scaling_Sensors_Http();
                    break;

                case "ram":
                    self::$sensors[$metricAlias] = new Scalr_Scaling_Sensors_FreeRam();
                    break;
            }
        }

        return isset(self::$sensors[$metricAlias]) ? self::$sensors[$metricAlias] : null;
    }

    /**
     * Calculates metric value for specified metric and Farm role
     *
     * @param DBFarmRole                   $dbFarmRole      Farm role for which metrics calculated
     * @param Scalr_Scaling_FarmRoleMetric $farmRoleMetric  Farm role metric
     *
     * @return mixed
     */
    abstract public function getValue(DBFarmRole $dbFarmRole, Scalr_Scaling_FarmRoleMetric $farmRoleMetric);
}
