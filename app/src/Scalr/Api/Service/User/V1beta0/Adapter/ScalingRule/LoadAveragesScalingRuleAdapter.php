<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\ScalingRule;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Entity\FarmRoleScalingMetric;

/**
 * LoadAverageScalingRuleAdapter v1beta0
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.7 (25.01.2016)
 */
class LoadAveragesScalingRuleAdapter extends BasicScalingRuleAdapter
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

        self::RULE_TYPE_SETTINGS_PROPERTY => 'settings',
        self::RULE_TYPE_SETTINGS => [
            FarmRoleScalingMetric::PERIOD => 'calculatingPeriod',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE => ['scaleUp', 'scaleDown', 'calculatingPeriod'],

        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * Allowed calculating period by interface
     *
     * @var array
     */
    protected static $calculatingPeriod = [1, 5, 15];

    /**
     * {@inheritdoc}
     * @see BasicScalingRuleAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        parent::validateEntity($entity);
        $this->validateNumericSetting($entity->settings, FarmRoleScalingMetric::PERIOD);

        if (!in_array($entity->settings[FarmRoleScalingMetric::PERIOD], static::$calculatingPeriod)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                'Property calculatingPeriod is invalid. Allowed periods are %s', implode(', ', static::$calculatingPeriod)
            ));
        }
    }
}