<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\ScalingRule;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\ScalingMetricAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\ScalingRuleAdapter;
use Scalr\Model\Entity\FarmRoleScalingMetric;
use Scalr\Model\Entity\ScalingMetric;

/**
 * BasicScalingRuleAdapter v1beta0
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.7 (25.01.2016)
 */
class BasicScalingRuleAdapter extends ScalingRuleAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data restul object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA => [
            '_name' => 'name', '_ruleType' => 'ruleType',
            '_scaleUp' => 'scaleUp', '_scaleDown' => 'scaleDown'
        ],

        self::RULE_TYPE_FILTERABLE => ['name', 'ruleType'],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE => ['scaleUp', 'scaleDown'],

        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    public function _scaleUp($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRoleScalingMetric */
                $to->scaleUp = $from->metric->isInvert ? $from->settings[FarmRoleScalingMetric::MIN] : $from->settings[FarmRoleScalingMetric::MAX];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRoleScalingMetric */
                if ($to->metric->isInvert) {
                    $to->settings[FarmRoleScalingMetric::MIN] = $from->scaleUp;
                } else {
                    $to->settings[FarmRoleScalingMetric::MAX] = $from->scaleUp;
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[]];
        }
    }

    public function _scaleDown($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRoleScalingMetric */
                $to->scaleDown = $from->metric->isInvert ? $from->settings[FarmRoleScalingMetric::MAX] : $from->settings[FarmRoleScalingMetric::MIN];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRoleScalingMetric */
                if ($to->metric->isInvert) {
                    $to->settings[FarmRoleScalingMetric::MAX] = $from->scaleDown;
                } else {
                    $to->settings[FarmRoleScalingMetric::MIN] = $from->scaleDown;
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[]];
        }
    }

    /**
     * {@inheritdoc}
     * @see ScalingRuleAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        parent::validateEntity($entity);

        if (!$entity->getFarmRole()->getRole()->isScalarized) {
            throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, sprintf(
                'Can not add %s Scaling metric to the agentless Role',
                 ScalingMetricAdapter::metricNameToData($entity->metric->name)
            ));
        }

        $criteria[] = ['farmRoleId' => $entity->farmRoleId];
        $criteria[] = ['metricId' => ScalingMetric::METRIC_DATE_AND_TIME_ID];
        if (!empty(FarmRoleScalingMetric::findOne($criteria))) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, 'DateAndTime metric cannot be used with others');
        }

        $settings = $entity->settings;
        if ($entity->metric->isInvert) {
            $this->validateNumericSetting($settings, FarmRoleScalingMetric::MIN, 'scaleUp');
            $this->validateNumericSetting($settings, FarmRoleScalingMetric::MAX, 'scaleDown');
        } else {
            $this->validateNumericSetting($settings, FarmRoleScalingMetric::MIN, 'scaleDown');
            $this->validateNumericSetting($settings, FarmRoleScalingMetric::MAX, 'scaleUp');
        }

        if ($entity->metricId != ScalingMetric::METRIC_SQS_QUEUE_SIZE_ID &&
            $entity->settings[FarmRoleScalingMetric::MIN] >= $entity->settings[FarmRoleScalingMetric::MAX]) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                'Scale up value must be %s than Scale down value', $entity->metric->isInvert ? 'less' : 'greater'
            ));
        }
    }
}