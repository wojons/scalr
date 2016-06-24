<?php

namespace Scalr\Api\Service\Account\V1beta0\Adapter;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Entity\AclRole;

/**
 * AclRoleAdapter v1beta0
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.18 (05.03.2016)
 */
class AclRoleAdapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA => [
            'accountRoleId' => 'id',
            'name',
            '_baseRole' => 'baseRole',
        ],

        self::RULE_TYPE_FILTERABLE => ['id', 'name', 'baseRole'],
        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['name' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\AclRole';

    protected static $baseRoleMap = [
        Acl::ROLE_ID_FULL_ACCESS => 'full-access',
        Acl::ROLE_ID_READ_ONLY_ACCESS => 'read-only',
        Acl::ROLE_ID_EVERYTHING_FORBIDDEN => 'no-access',
    ];

    public function _baseRole($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from AclRole */
                $to->baseRole = static::$baseRoleMap[$from->baseRoleId];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to AclRole */
                break;

            case static::ACT_GET_FILTER_CRITERIA:

                $baseRoles = array_flip(static::$baseRoleMap);
                $baseRoleId = $from->baseRole;

                if (!isset($baseRoles[$baseRoleId])) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected baseRole value");
                }

                return [['baseRoleId' => $baseRoles[$baseRoleId]]];
        }
    }
}