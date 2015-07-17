<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;

/**
 * RoleGlobalVariableAdapter V1
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (18.03.2015)
 */
class RoleGlobalVariableAdapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => ['name', 'value', 'category', 'description',
            'format'        => 'outputFormat',
            'validator'     => 'validationPattern',
            'flagRequired'  => 'requiredIn',
            'flagHidden'    => 'hidden',
            'flagFinal'     => 'locked',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['value', 'category', 'outputFormat', 'validationPattern', 'hidden', 'requiredIn', 'locked', 'description'],

        self::RULE_TYPE_FILTERABLE  => [],
        self::RULE_TYPE_SORTING     => [],
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Model\Entity\RoleGlobalVariable';

    /**
     * Convert variable model data to api response format
     *
     * @param array $variable
     * @return array
     */
    public function convertData(array $variable)
    {
        $item = [
            'declaredIn'        => !empty($variable['locked']['scope']) ? $variable['locked']['scope'] : reset($variable['scopes']),
            'hidden'            => !empty($variable['locked']['flagHidden']) ? true : false,
            'locked'            => !empty($variable['locked']['flagFinal']) ? true : false,
            'name'              => $variable['name'],
            'value'             => !empty($variable['current']['value']) ? $variable['current']['value'] : null
        ];

        if (!empty($variable['current']['format'])) {
            $item['outputFormat'] = $variable['current']['format'];
        } else if (!empty($variable['locked']['format'])) {
            $item['outputFormat'] = $variable['locked']['format'];
        } else {
            $item['outputFormat'] = null;
        }

        if (!empty($variable['current']['validator'])) {
            $item['validationPattern'] = $variable['current']['validator'];
        } else if (!empty($variable['locked']['validator'])) {
            $item['validationPattern'] = $variable['locked']['validator'];
        } else {
            $item['validationPattern'] = null;
        }

        if (!empty($variable['current']['value'])) {
            $item['computedValue'] = $variable['current']['value'];
        } else if (!empty($variable['locked']['value'])) {
            $item['computedValue'] = $variable['locked']['value'];
        } else {
            $item['computedValue'] = null;
        }

        if (!empty($variable['current']['category'])) {
            $item['computedCategory'] = $variable['current']['category'];
        } else if (!empty($variable['locked']['category'])) {
            $item['computedCategory'] = $variable['locked']['category'];
        } else {
            $item['computedCategory'] = null;
        }

        if (!empty($variable['current']['flagRequired'])) {
            $item['requiredIn'] = $variable['current']['flagRequired'];
        } else if (!empty($variable['locked']['flagRequired'])) {
            $item['requiredIn'] = $variable['locked']['flagRequired'];
        } else {
            $item['requiredIn'] = null;
        }

        if (!empty($variable['current']['description'])) {
            $item['description'] = $variable['current']['description'];
        } else if (!empty($variable['locked']['description'])) {
            $item['description'] = $variable['locked']['description'];
        } else {
            $item['description'] = null;
        }

        return $item;
    }

}