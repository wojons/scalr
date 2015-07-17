<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Exception\NotYetImplementedException;
use Scalr\Model\AbstractEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;

/**
 * Cost-Center Adapter v1beta0
 *
 * @author N.V.
 *
 * @method  CostCentreEntity toEntity($data) Converts data to entity
 */
class CostCenterAdapter extends ApiEntityAdapter
{

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA         => [
            'ccId' => 'id', 'name', '_billingCode' => 'billingCode'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE       => ['name'],

        self::RULE_TYPE_FILTERABLE      => ['id', 'name', 'billingCode'],
        self::RULE_TYPE_SORTING         => [self::RULE_TYPE_PROP_DEFAULT => ['created' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Stats\CostAnalytics\Entity\CostCentreEntity';

    public function _billingCode($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from CostCentreEntity */
                $to->billingCode = $from->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /** @var $to CostCentreEntity */
                throw new NotYetImplementedException();
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $cc = new CostCentreEntity();
                $property = new CostCentrePropertyEntity();

                return [
                    AbstractEntity::STMT_FROM => $cc->table() . " LEFT JOIN " . $property->table() . " ON {$property->columnCcId} = {$cc->columnCcId}",
                    AbstractEntity::STMT_WHERE => "{$property->columnName} = '" . CostCentrePropertyEntity::NAME_BILLING_CODE . "' AND {$property->columnValue} = " . $property->qstr('value', $from->billingCode)
                ];
        }
    }
}