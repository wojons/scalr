<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\ScalingRule;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Entity\FarmRoleScalingMetric;


/**
 * UrlResponseTimeScalingRuleAdapter v1beta0
 *
 * @author Andrii Penchuk  <a.penchuk@scalr.com>
 * @since  5.11.7 (25.01.2016)
 */
class UrlResponseTimeScalingRuleAdapter extends BasicScalingRuleAdapter
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
        self::RULE_TYPE_SETTINGS => [FarmRoleScalingMetric::URL],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE => ['scaleUp', 'scaleDown', 'url'],

        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * {@inheritdoc}
     * @see BasicScalingRuleAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        parent::validateEntity($entity);

        if (empty($entity->settings[FarmRoleScalingMetric::URL])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property url');
        }
        if (!filter_var($entity->settings[FarmRoleScalingMetric::URL], FILTER_VALIDATE_URL)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Property url is invalid');
        }
    }
}