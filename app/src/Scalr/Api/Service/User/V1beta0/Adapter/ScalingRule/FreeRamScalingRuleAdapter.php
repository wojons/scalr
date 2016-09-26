<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\ScalingRule;

use Scalr\Model\Entity\FarmRoleScalingMetric;


/**
 * FreeRamScalingRuleAdapter v1beta0
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.7 (25.01.2016)
 */
class FreeRamScalingRuleAdapter extends BasicScalingRuleAdapter
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
            '_name' => 'name', '_ruleType' => 'ruleType', '_useCachedRam' => 'useCachedRam',
            '_scaleUp' => 'scaleUp', '_scaleDown' => 'scaleDown'
        ],

        self::RULE_TYPE_FILTERABLE => ['name', 'ruleType'],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE => ['scaleUp', 'scaleDown', 'useCachedRam'],

        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    public function _useCachedRam($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRoleScalingMetric */
                $to->useCachedRam = $from->settings[FarmRoleScalingMetric::USE_CACHED];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRoleScalingMetric */
                $to->settings[FarmRoleScalingMetric::USE_CACHED] =  static::convertInputValue('boolean', $from->useCachedRam, 'useCachedRam');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[]];
        }
    }

    /**
     * {@inheritdoc}
     * @see BasicScalingRuleAdapter::toEntity()
     */
    public function toEntity($data)
    {
        /* @var $entity FarmRoleScalingMetric */
        $entity = parent::toEntity($data);

        if (!isset($entity->settings[FarmRoleScalingMetric::USE_CACHED])) {
            $entity->settings[FarmRoleScalingMetric::USE_CACHED] = false;
        }

        return $entity;
    }
}