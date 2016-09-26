<?php

use Scalr\Model\Entity;

class Scalr_Scaling_FarmRoleMetric extends Scalr_Model
{
    protected $dbTableName = 'farm_role_scaling_metrics';
    protected $dbPrimaryKey = "id";
    protected $dbMessageKeyNotFound = "FarmRoleMetric #%s not found in database";

    protected $dbPropertyMap = [
        'id' => 'id',
        'farm_roleid'   => ['property' => 'farmRoleId', 'is_filter' => true],
        'metric_id'     => ['property' => 'metricId', 'is_filter' => true],
        'dtlastpolled'  => ['property' => 'dtLastPolled', 'type' => 'datetime'],
        'last_value'    => ['property' => 'lastValue'],
        'settings'      => ['property' => 'settingsRaw'],
        'last_data'     => ['property' => 'lastData', 'type' => 'serialize'],
    ];

    public
        $id,
        $farmRoleId,
        $metricId,
        $dtLastPolled,
        $instancesNumber,
        $lastValue,
        $lastData;

    /**
     * Related ScalingMetric instance
     *
     * @var \Scalr\Model\Entity\ScalingMetric
     */
    protected $metric;

    protected $settingsRaw;

    protected $settings;

    protected $logger;

    function __construct($id = null)
    {
        parent::__construct($id);
        $this->logger = \Scalr::getContainer()->logger(__CLASS__);
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
        foreach ($settings as $k => $v)
            $this->setSetting($k, $v);
    }

    public function setSetting($name, $value)
    {
        $this->settings[$name] = $value;
        $this->settingsRaw = serialize($this->settings);
    }

    public function clearSettings()
    {
        $this->settings = [];
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
                if ($sensorValue === false) {
                    return Scalr_Scaling_Decision::NOOP;
                }
            } catch (Exception $e) {
                $this->logger->warn(new FarmLogMessage(
                    $dbFarmRole->FarmID,
                    sprintf("Unable to read Scaling Metric (%s) on farmrole %s value: %s",
                        $this->getMetric()->alias, $dbFarmRole->ID, $e->getMessage()
                    )
                ));

                return Scalr_Scaling_Decision::NOOP;
            }

            $this->logger->debug(sprintf(_("Raw sensor value (id: %s, metric_id: %s, metric name: %s): %s"),
                $this->id,
                $this->metricId,
                $this->getMetric()->name,
                json_encode($sensorValue)
            ));

            switch ($this->getMetric()->calcFunction) {
                case Entity\ScalingMetric::CALC_FUNCTION_AVERAGE:
                    $value = (is_array($sensorValue) && count($sensorValue) != 0) ? @array_sum($sensorValue) / count($sensorValue) : 0;
                    break;

                case Entity\ScalingMetric::CALC_FUNCTION_SUM:
                    $value = @array_sum($sensorValue);
                    break;

                case Entity\ScalingMetric::CALC_FUNCTION_MAXIMUM:
                    $value = @max($sensorValue);
                    break;

                case Entity\ScalingMetric::CALC_FUNCTION_MINIMUM:
                    $value = @min($sensorValue);
                    break;

                default:
                    $value = $sensorValue[0];
            }

            $this->lastValue = round($value, 5);
            // Sets the Server time to avoid differences between Database and Server time
            $this->dtLastPolled = time();
            $this->save(false, ['settings', 'metric_id']);

            $invert = $sensor instanceof Scalr_Scaling_Sensors_Custom ? $this->getMetric()->isInvert : $sensor->isInvert;
        } else {
            $invert = false;
        }

        if ($this->getMetric()->name == 'DateAndTime') {
            $decision = $algo->makeDecision($dbFarmRole, $this, $invert);
            $this->instancesNumber = $algo->instancesNumber;
            $this->lastValue = isset($algo->lastValue) ? $algo->lastValue : null;

            return $decision;
        } elseif ($this->getMetric()->name == 'FreeRam') {
            if ($this->lastValue == 0) {
                return Scalr_Scaling_Decision::NOOP;
            } else {
                return $algo->makeDecision($dbFarmRole, $this, $invert);
            }
        } else {
            return $algo->makeDecision($dbFarmRole, $this, $invert);
        }
    }

    /**
     * Gets related ScalingMetric entity
     *
     * @return \Scalr\Model\Entity\ScalingMetric
     */
    function getMetric()
    {
        if (!$this->metric) {
            $this->metric = Entity\ScalingMetric::findPk($this->metricId);
        }

        return $this->metric;
    }

    /**
     * Save current object to database
     *
     * @param bool  $forceInsert    optional Force insert. (false by default)
     * @param array $ignoredFields  optional Fields that are not updated
     * @return Scalr_Model Return current object
     *
     * @throws  Exception
     */
    public function save($forceInsert = false, array $ignoredFields = null)
    {
        foreach ($ignoredFields ?: [] as $ignoredField) {
            if (array_key_exists($ignoredField, $this->dbPropertyMap)) {
                if (!is_array($this->dbPropertyMap[$ignoredField])) {
                    $this->dbPropertyMap[$ignoredField] = ['property' => $this->dbPropertyMap[$ignoredField]];
                }
                $this->dbPropertyMap[$ignoredField]['update'] = false;
            }
        }

        return parent::save($forceInsert);
    }
}
