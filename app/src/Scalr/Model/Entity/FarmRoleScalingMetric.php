<?php

namespace Scalr\Model\Entity;

use DateTime;
use Scalr\Model\AbstractEntity;
use Scalr\Util\ObjectAccess;
use \Scalr\Exception\ModelException;

/**
 * FarmRoleScalingMetric entity
 *
 * @author   Andrii Penchuk  <a.penchuk@scalr.com>
 * @since    5.11.7 (25.01.2016)
 *
 * @property  ScalingMetric $metric ScalingMetric  entity
 *
 * @Entity
 * @Table(name="farm_role_scaling_metrics")
 */
class FarmRoleScalingMetric extends AbstractEntity
{
    /**
     * Custom
     */
    const MIN = 'min';
    const MAX = 'max';
    /**
     * LoadAverages
     */
    const PERIOD = 'period';
    /**
     * FreeRam
     */
    const USE_CACHED = 'use_cached';
    /**
     * SqsQueueSize
     */
    const QUEUE_NAME = 'queue_name';

    /**
     * SqsQueueSize
     */
    const URL = 'url';

    /**
     * BandWidth
     */
    const TYPE = 'type';
    const INBOUND = 'inbound';
    const OUTBOUND = 'outbound';

    /**
     * DateAndTime
     */
    const START_TIME = 'start_time';
    const END_TIME = 'end_time';
    const WEEK_DAYS = 'week_days';
    const INSTANCES_COUNT = 'instances_count';
    const SETTINGS_TIME_FORMAT = 'g:i A';
    const SCALING_TIME_FORMAT = 'Hi';

    /**
     * Identifier
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * FarmRole Id
     *
     * @Column(name="farm_roleid",type="integer",nullable=true)
     * @var int
     */
    public $farmRoleId;

    /**
     * ScalingMetric Id
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $metricId;

    /**
     * Time of last polled
     *
     * @Column(name="dtlastpolled",type="datetime",nullable=true)
     * @var DateTime
     */
    public $dateLastPolled;

    /**
     * last value
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $lastValue;

    /**
     * Settings
     *
     * @Column(type="serializeObject",nullable=true)
     * @var ObjectAccess
     */
    public $settings;

    /**
     * Last data
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $lastData;

    /**
     * ScalingMetric
     *
     * @var ScalingMetric
     */
    protected $_metric;

    public function __construct()
    {
        $this->settings = new ObjectAccess();
    }

    /**
     * FarmRole entity
     *
     * @var FarmRole
     */
    protected $_farmRole;

    /**
     * {@inheritdoc}
     * @see AbstractEntity::__get()
     */
    public function __get($prop)
    {
        switch ($prop) {
            case 'metric':
                return $this->_metric;

            default:
                return parent::__get($prop);
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::__set()
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'metric':
                $this->_metric = $value;
                break;

            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Get FarmRole entity
     *
     * @return FarmRole|null
     */
    public function getFarmRole()
    {
        if (empty($this->_farmRole) && !empty($this->farmRoleId)) {
            $this->_farmRole = FarmRole::findPk($this->farmRoleId);
        }

        return $this->_farmRole;
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        $this->setupScalingTimes();
        parent::save();
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::delete()
     */
    public function delete()
    {
        parent::delete();
        if(ScalingMetric::METRIC_DATE_AND_TIME_ID === $this->metricId) {
            FarmRoleScalingTime::deleteByFarmRoleId($this->farmRoleId);
        }
    }

    /**
     * Setup scaling times for DateAndTime scaling metrics
     *
     * @throws ModelException
     */
    public function setupScalingTimes()
    {
        if (ScalingMetric::METRIC_DATE_AND_TIME_ID === $this->metricId) {
            FarmRoleScalingTime::deleteByFarmRoleId($this->farmRoleId);
            foreach ($this->settings as $key => $setting) {
                if (is_array($setting[static::WEEK_DAYS])) {
                    $setting[static::WEEK_DAYS] = ucwords(implode(', ', $setting[static::WEEK_DAYS]));
                }
                $start = $setting[static::START_TIME];
                $end = $setting[static::END_TIME];
                //create farm role scaling time
                $scalingTime = new FarmRoleScalingTime();
                $scalingTime->farmRoleId = $this->farmRoleId;
                $scalingTime->startTime = (int)$this->convertTime($start, static::SCALING_TIME_FORMAT);
                $scalingTime->endTime = (int)$this->convertTime($end, static::SCALING_TIME_FORMAT);
                $scalingTime->daysOfWeek = $setting[static::WEEK_DAYS];
                $scalingTime->instancesCount = $setting[static::INSTANCES_COUNT];

                $setting[static::START_TIME] = $this->convertTime($start ,static::SETTINGS_TIME_FORMAT);
                $setting[static::END_TIME] = $this->convertTime($end ,static::SETTINGS_TIME_FORMAT);
                $setting['id'] = "{$scalingTime->startTime}:{$scalingTime->endTime}:{$scalingTime->daysOfWeek}:{$scalingTime->instancesCount}";
                $scalingTime->save();
                $this->settings->offsetSet($key, $setting);
            }
        }
    }

    /**
     * If $date instanceof DateTime function will convert time to format
     *
     * @param string|DateTime $date
     * @param string $format
     * @return string
     */
    protected function convertTime($date, $format)
    {
        return ($date instanceof DateTime) ? $date->format($format) : $date;
    }
}