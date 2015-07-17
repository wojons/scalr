<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;

/**
 * RoleCategoryAdapter V1
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (18.03.2015)
 */
class RoleCategoryAdapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data restul object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', 'name', '_scope' => 'scope'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => [],

        self::RULE_TYPE_FILTERABLE  => ['id', 'name', 'scope'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Model\Entity\RoleCategory';

    protected function _scope($from, $to, $action)
    {
        if ($action == self::ACT_CONVERT_TO_OBJECT) {
            $to->scope = $from->getScope();
        } else if ($action == self::ACT_CONVERT_TO_ENTITY) {
            throw new ApiNotImplementedErrorException();
        } else if ($action == self::ACT_GET_FILTER_CRITERIA) {
            if (empty($from->scope)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
            }

            if ($from->scope === ScopeInterface::SCOPE_SCALR) {
                return [['envId' => null]];
            } else if ($from->scope === ScopeInterface::SCOPE_ENVIRONMENT) {
                return [['envId' => $this->controller->getEnvironment()->id]];
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
            }
        }
    }
}