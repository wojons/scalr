<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity;
use InvalidArgumentException;

/**
 * ScalingRuleAdapter v1beta0
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.7 (25.01.2016)
 */
class ScalingRuleAdapter extends ApiEntityAdapter
{
    /**
     * Metric's alias
     */
    const METRIC_BASIC             = 'custom';
    const METRIC_FREE_RAM          = 'ram';
    const METRIC_BANDWIDTH         = 'bw';
    const METRIC_LOAD_AVERAGES     = 'la';
    const METRIC_DATE_AND_TIME     = 'time';
    const METRIC_SQS_QUEUE_SIZE    = 'sqs';
    const METRIC_URL_RESPONSE_TIME = 'http';

    /**
     * Scaling rule names
     */
    const BASIC_SCALING_RULE             = 'BasicScalingRule';
    const FREE_RAM_SCALING_RULE          = 'FreeRamScalingRule';
    const BANDWIDTH_SCALING_RULE         = 'BandWidthScalingRule';
    const LOAD_AVERAGES_SCALING_RULE     = 'LoadAveragesScalingRule';
    const DATE_AND_TIME_SCALING_RULE     = 'DateAndTimeScalingRule';
    const SQS_QUEUE_SIZE_SCALING_RULE    = 'SqsQueueSizeScalingRule';
    const URL_RESPONSE_TIME_SCALING_RULE = 'UrlResponseTimeScalingRule';

    /**
     * Rule type mapping
     *
     * @var array
     */
    public static $ruleTypeMap = [
        self::METRIC_BASIC             => self::BASIC_SCALING_RULE,
        self::METRIC_FREE_RAM          => self::FREE_RAM_SCALING_RULE,
        self::METRIC_BANDWIDTH         => self::BANDWIDTH_SCALING_RULE,
        self::METRIC_LOAD_AVERAGES     => self::LOAD_AVERAGES_SCALING_RULE,
        self::METRIC_DATE_AND_TIME     => self::DATE_AND_TIME_SCALING_RULE,
        self::METRIC_SQS_QUEUE_SIZE    => self::SQS_QUEUE_SIZE_SCALING_RULE,
        self::METRIC_URL_RESPONSE_TIME => self::URL_RESPONSE_TIME_SCALING_RULE
    ];

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data restul object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
             '_name' => 'name', '_ruleType' => 'ruleType'
        ],

        self::RULE_TYPE_FILTERABLE => ['name', 'ruleType'],

        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Model\Entity\FarmRoleScalingMetric';

    public function _name($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\FarmRoleScalingMetric */
                $to->name = ScalingMetricAdapter::metricNameToData($from->metric->name);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                $this->validateString($from->name, 'Property name contains invalid characters');
                /* @var $metric Entity\ScalingMetric */
                $metric = Entity\ScalingMetric::findOne([['name' => ScalingMetricAdapter::metricNameToEntity($from->name)]]);
                if (empty($metric)) {
                    throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf('Scaling metric with name %s does not exist', $from->name));
                }
                /* @var $to Entity\FarmRoleScalingMetric */
                $to->metricId = $metric->id;
                $to->metric = $metric;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ ]];
        }
    }

    public function _ruleType($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\FarmRoleScalingMetric */
                $to->ruleType = static::getRuleType($from->metric->alias);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ ]];
        }
    }

    /**
     * Get metric rule type
     *
     * @param string $name metric's name
     * @return string
     * @throws ApiErrorException
     */
    public static function getRuleType($name)
    {
        if (!isset(self::$ruleTypeMap[$name])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected ruleType value');
        }

        return self::$ruleTypeMap[$name];
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateObject()
     */
    public function validateObject($object, $method = null)
    {
        parent::validateObject($object, $method);

        if ($method === Request::METHOD_POST) {
            if (empty($object->name)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property name');
            }

            if ($object->ruleType === static::BASIC_SCALING_RULE xor !isset(ScalingMetricAdapter::$nameMap[$object->name])) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected ruleType value');
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     * @param Entity\FarmRoleScalingMetric $entity scaling metric entity
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof Entity\FarmRoleScalingMetric) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\FarmRoleScalingMetric class"
            ));
        }

        $farmRole = $entity->getFarmRole();
        if (empty($farmRole)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                "Could not find out the Farm role with ID: %d", $entity->farmRoleId
            ));
        }

        $disabledScalingBehaviors = array_intersect(FarmRoleAdapter::$disableScalingBehaviors, $farmRole->getRole()->getBehaviors());
        if (!empty($disabledScalingBehaviors)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, sprintf(
                'Can not add Scaling Metric to the Role with the following built-in automation types: %s.',
                implode(', ', RoleAdapter::behaviorsToData($disabledScalingBehaviors))
            ));
        }

        if (empty($entity->id)) {
            if (empty($entity->metricId)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property name");
            }

            $criteria = [
                ['farmRoleId' => $entity->farmRoleId],
                ['metricId'   => $entity->metricId],
            ];

            if (!empty(Entity\FarmRoleScalingMetric::findOne($criteria))) {
                throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, 'This Scaling metric already added to the current Farm role.');
            }
        }
    }

    /**
     * Validate Integer Scaling rule settings
     *
     * @param array  $settings          entity settings
     * @param string $name              entity property name
     * @param string $property optional property name
     * @param int    $minValue optional min property value
     * @throws ApiErrorException
     */
    public function validateNumericSetting($settings, $name, $property = null, $minValue = 0)
    {
        if (is_null($property)) {
            $property = $this->getSettingsRules()[$name];
        }

        if (!isset($settings[$name])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, sprintf('Missed property %s', $property));
        }

        if (!is_numeric($settings[$name])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf('Unexpected %s value', $property));
        }

        if ($settings[$name] < $minValue) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE,
                sprintf('Property %s must be greater than or equal to %u', $property, $minValue)
            );
        }
    }
}