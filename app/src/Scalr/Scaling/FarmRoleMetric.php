<?php

use Scalr\Model\Entity;

class Scalr_Scaling_FarmRoleMetric extends Scalr_Model
{
    protected $dbTableName = 'farm_role_scaling_metrics';
    protected $dbPrimaryKey = "id";
    protected $dbMessageKeyNotFound = "FarmRoleMetric #%s not found in database";

    protected $dbPropertyMap = array(
        'id'				=> 'id',
        'farm_roleid'		=> array('property' => 'farmRoleId', 'is_filter' => true),
        'metric_id'			=> array('property' => 'metricId', 'is_filter' => true),
        'dtlastpolled'		=> array('property' => 'dtLastPolled', 'createSql' => 'NOW()', 'updateSql' => 'NOW()', 'type' => 'datetime', 'update' => true),
        'last_value'		=> array('property' => 'lastValue'),
        'settings'			=> array('property' => 'settingsRaw')
    );

    public
        $id,
        $farmRoleId,
        $metricId,
        $dtLastPolled,
        $instancesNumber,
        $lastValue;

    protected $metric,
        $settingsRaw,
        $settings,
        $logger;

    function __construct($id = null)
    {
        parent::__construct($id);
        $this->logger = Logger::getLogger(__CLASS__);
    }

    function loadBy($info)
    {
        parent::loadBy($info);

        $this->settings = unserialize($this->settingsRaw);

        return $this;
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function getSetting($name)
    {
        return $this->settings[$name];
    }

    public function setSettings($settings)
    {
        foreach ($settings as $k=>$v)
            $this->setSetting($k, $v);
    }

    public function setSetting($name, $value)
    {
        $this->settings[$name] = $value;
        $this->settingsRaw = serialize($this->settings);
    }

    function getScalingDecision()
    {
        $algo = Entity\ScalingMetric::getAlgorithm($this->getMetric()->algorithm);
        $sensor = Scalr_Scaling_Sensor::get($this->getMetric()->alias);

        $dbFarmRole = DBFarmRole::LoadByID($this->farmRoleId);

        if ($sensor) {
            try {
                $sensorValue = $sensor->getValue($dbFarmRole, $this);
                if ($sensorValue === false)
                    return Scalr_Scaling_Decision::NOOP;
            } catch (Exception $e) {
                $this->logger->warn(new FarmLogMessage($dbFarmRole->FarmID,
                    sprintf("Unable to read Scaling Metric (%s) on farmrole %s value: %s",
                    $this->getMetric()->alias, $dbFarmRole->ID, $e->getMessage())
                ));

                return Scalr_Scaling_Decision::NOOP;
            }

            $this->logger->info(sprintf(_("Raw sensor value (id: %s, metric_id: %s, metric name: %s): %s"),
                $this->id,
                $this->metricId,
                $this->getMetric()->name,
                serialize($sensorValue)
            ));

            switch($this->getMetric()->calcFunction) {
                default:
                    $value = $sensorValue[0];
                    break;

                case "avg":
                    $value = (is_array($sensorValue) && count($sensorValue) != 0) ? @array_sum($sensorValue) / count($sensorValue) : 0;
                    break;

                case "sum":
                    $value = @array_sum($sensorValue);
                    break;
                case "max":
                    $value = @max($sensorValue);
                    break;
            }

            $this->lastValue = round($value, 5);
            $this->save();

            $invert = $sensor->isInvert;
        } else {
            $invert = false;
        }

        if ($this->getMetric()->name == 'DateAndTime') {
            $decision = $algo->makeDecision($dbFarmRole, $this, $invert);
            $this->instancesNumber = $algo->instancesNumber;
            $this->lastValue = $algo->lastValue;

            return $decision;
        } elseif ($this->getMetric()->name == 'FreeRam') {
            if ($this->lastValue == 0)
                return Scalr_Scaling_Decision::NOOP;
            else
                return $algo->makeDecision($dbFarmRole, $this, $invert);
        } else
            return $algo->makeDecision($dbFarmRole, $this, $invert);
    }

    /**
     *
     * @return Entity\ScalingMetric
     */
    function getMetric()
    {
        if (!$this->metric)
            $this->metric = Entity\ScalingMetric::findPk($this->metricId);

        return $this->metric;
    }
}
