<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\ScalingRule;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\ScalingRuleAdapter;
use Scalr\Model\Entity\FarmRoleScalingMetric;
use Scalr\Model\Entity;
use Scalr\Util\ObjectAccess;
use stdClass;
use DateTime;

/**
 * DateAndTimeScalingRuleAdapter v1beta0
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.7 (25.01.2016)
 */
class DateAndTimeScalingRuleAdapter extends ScalingRuleAdapter
{
    protected $rules = [
        //Allows all entity properties to be converted from entity into data restul object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            '_name' => 'name', '_ruleType' => 'ruleType', '_schedule' => 'schedule'
        ],

        self::RULE_TYPE_FILTERABLE  => ['name', 'ruleType'],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   =>  ['schedule'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]]
    ];

    /**
     * Allowed time interval
     */
    const TIME_INTERVAL = 15;

    /**
     * List of weak days value
     *
     * @var array
     */
    protected static $listOfWeakDays = [
        'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'
    ];

    /**
     * Schedule properties mapping
     *
     * @var array
     */
    protected static $dateTimeSettingsMap = [
        FarmRoleScalingMetric::START_TIME      => 'start',
        FarmRoleScalingMetric::END_TIME        => 'end',
        FarmRoleScalingMetric::WEEK_DAYS       => 'daysOfWeek',
        FarmRoleScalingMetric::INSTANCES_COUNT => 'instanceCount'
    ];

    public function _schedule($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\FarmRoleScalingMetric */
                $this->scheduleToData($from, $to);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\FarmRoleScalingMetric */
                $this->scheduleToEntity($from, $to);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [];
        }
    }

    /**
     * Converts schedule settings entity to data object
     *
     * @param FarmRoleScalingMetric $entity   Object entity
     * @param stdClass              $object  The data to convert into entity
     */
    protected function scheduleToData(FarmRoleScalingMetric $entity, $object)
    {
        $results = [];
        foreach ($entity->settings as $setting) {
            $result = new $this->dataClass;
            $converterRules = static::$dateTimeSettingsMap;
            foreach ($converterRules as $key => $property) {
                if ($key === FarmRoleScalingMetric::WEEK_DAYS) {
                    $result->$property = preg_split('/\s*,\s*/', strtolower($setting[$key]));
                } else {
                    $result->$property = $setting[$key];
                }
            }
            $results[] = $result;
        }
        $object->schedule = $results;
    }

    /**
     * Converts schedule data to entity
     *
     * @param stdClass              $object
     * @param FarmRoleScalingMetric $entity Object entity
     * @throws ApiErrorException
     */
    protected function scheduleToEntity($object, FarmRoleScalingMetric $entity)
    {
        if (empty($object->schedule)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property schedule');
        }

        if (!is_array($object->schedule)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Property schedule must be an array");
        }

        $schedulePeriods = [];
        $entity->settings = new ObjectAccess();
        foreach ($object->schedule as $collection) {
            $schedule = [];
            foreach (static::$dateTimeSettingsMap as $key => $property) {
                if (!isset($collection->{$property})) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, sprintf('Missed property schedule.%s', $property));
                }
                if($key == FarmRoleScalingMetric::START_TIME || $key == FarmRoleScalingMetric::END_TIME) {
                    $schedule[$key] = static::convertInputValue('datetime', $collection->$property, $property);
                    $this->validateTimeInterval($schedule[$key]);
                } else {
                    $schedule[$key] = $collection->{$property};
                }
            }

            if (!is_array($schedule[FarmRoleScalingMetric::WEEK_DAYS])) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Property daysOfWeek must be an array");
            }

            $diffDays = array_diff(array_map('ucfirst', $schedule[FarmRoleScalingMetric::WEEK_DAYS]), static::$listOfWeakDays);
            if (!empty($diffDays)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                        'The %s %s of property daysOfWeek %s not valid',
                        ...((count($diffDays) > 1) ? ['values', implode(', ', $diffDays), 'are'] : ['value', array_shift($diffDays) , 'is'])
                    )
                );
            }

            $this->validateNumericSetting($schedule, FarmRoleScalingMetric::INSTANCES_COUNT, static::$dateTimeSettingsMap[FarmRoleScalingMetric::INSTANCES_COUNT]);

            /* @var  $start DateTime */
            $start = $schedule[FarmRoleScalingMetric::START_TIME];
            /* @var  $end DateTime */
            $end = $schedule[FarmRoleScalingMetric::END_TIME];
            $intStart = (int)$start->format(FarmRoleScalingMetric::SCALING_TIME_FORMAT);
            $intEnd =   (int)$end->format(FarmRoleScalingMetric::SCALING_TIME_FORMAT);
            if($intEnd <= $intStart) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                    'End time value %s must be greater than Start time value %s',
                    $end->format(FarmRoleScalingMetric::SETTINGS_TIME_FORMAT),
                    $start->format(FarmRoleScalingMetric::SETTINGS_TIME_FORMAT)
                ));
            }

            $currentPeriod = [
                FarmRoleScalingMetric::START_TIME => $intStart,
                FarmRoleScalingMetric::END_TIME   =>  $intEnd,
                FarmRoleScalingMetric::WEEK_DAYS  => $schedule[FarmRoleScalingMetric::WEEK_DAYS],
            ];

            foreach ($schedulePeriods as $key => $schedulePeriod) {
                if(
                    $currentPeriod[FarmRoleScalingMetric::START_TIME] <= $schedulePeriod[FarmRoleScalingMetric::END_TIME] &&
                    $currentPeriod[FarmRoleScalingMetric::END_TIME]  >= $schedulePeriod[FarmRoleScalingMetric::START_TIME] &&
                    !empty(array_intersect($schedulePeriod[FarmRoleScalingMetric::WEEK_DAYS], $currentPeriod[FarmRoleScalingMetric::WEEK_DAYS]))
                ) {
                    $ovSchedule = $entity->settings[$key];
                    throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf(
                        'Period %s %s - %s overlaps with period %s %s - %s',
                        implode(', ', $schedule[FarmRoleScalingMetric::WEEK_DAYS]),
                        $start->format(FarmRoleScalingMetric::SETTINGS_TIME_FORMAT),
                        $end->format(FarmRoleScalingMetric::SETTINGS_TIME_FORMAT),
                        implode(', ', $ovSchedule[FarmRoleScalingMetric::WEEK_DAYS]),
                        $ovSchedule[FarmRoleScalingMetric::START_TIME]->format(FarmRoleScalingMetric::SETTINGS_TIME_FORMAT),
                        $ovSchedule[FarmRoleScalingMetric::END_TIME]->format(FarmRoleScalingMetric::SETTINGS_TIME_FORMAT)
                    ));
                }
            }

            $schedulePeriods[] = $currentPeriod;
            $entity->settings[] = $schedule;
        }
    }

    /**
     * {@inheritdoc}
     * @see BasicScalingRuleAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        parent::validateEntity($entity);

        if (!empty(Entity\FarmRoleScalingMetric::findOne([['farmRoleId' => $entity->farmRoleId], ['id' => ['$ne' => $entity->id]]]))) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, 'DateAndTime metric cannot be used with others');
        }

        if ($entity->settings->count() === 0) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property schedule');
        }
    }

    /**
     * Validate schedule start and end time interval
     *
     * @param DateTime $time
     * @throws ApiErrorException
     */
    public function validateTimeInterval(DateTime $time)
    {
        $intTime = (int)$time->format('i');
        if (($intTime % static::TIME_INTERVAL) > 0) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                'Time %s is invalid. Minutes values should be at intervals of %d', $time->format(FarmRoleScalingMetric::SETTINGS_TIME_FORMAT), static::TIME_INTERVAL
            ));
        }
    }
}