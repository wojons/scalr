<?php

namespace Scalr\Api\Service\Account\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\Account\V1beta0\Controller\Teams;
use Scalr\Model\Entity\Account\Team;
use Scalr\Model\Entity\AclRole;
use Scalr\Net\Ldap\Exception\LdapException;
use InvalidArgumentException;

/**
 * Team Adapter v1beta0
 *
 * @author N.V.
 *
 * @property    Teams $controller     Teams controller
 *
 * @method      Team   toEntity($data) Converts data to entity
 */
class TeamAdapter extends ApiEntityAdapter
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
            'id', 'name', 'description',
            '_defaultAclRole' => 'defaultAclRole'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE => ['name', 'description', 'defaultAclRole'],

        self::RULE_TYPE_FILTERABLE => ['id', 'name', 'defaultAclRole'],
        self::RULE_TYPE_SORTING => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\Account\Team';

    public function _defaultAclRole($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Team */
                $to->defaultAclRole = ['id' => $from->accountRoleId];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Team */
                $to->accountRoleId = ApiController::getBareId($from, 'defaultAclRole');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['accountRoleId' => ApiController::getBareId($from, 'defaultAclRole')]];
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof Team) {
            throw new InvalidArgumentException(sprintf("First argument must be instance of %s", Team::class));
        }

        if (empty($entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property name");
        }

        if (Team::findOne([['accountId' => $entity->accountId], ['name' => $entity->name], ['id' => ['$ne' => $entity->id]]])) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION,
                "Team with name '{$entity->name}' already exists in this account"
            );
        }

        $this->validateTeamName($entity->name);

        if (empty($entity->accountRoleId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property defaultAclRole");
        }

        if (!(is_string($entity->accountRoleId) && preg_match('/^' . AclRole::ACL_ROLE_ID_REGEXP . '$/', $entity->accountRoleId))) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the ACL Role");
        }

        if (empty(AclRole::findOne([['accountRoleId' => $entity->accountRoleId], ['accountId' => $entity->accountId]]))) {
            throw new ApiErrorException(404, ErrorMessage::ERR_INVALID_VALUE, "ACL Role with ID: '{$entity->accountRoleId}' is not found in this account.");
        }
    }

    /**
     * Validates team name if Ldap auth name should associates with Ldap group name
     *
     * @param string $name Team name
     *
     * @throws ApiErrorException
     */
    public function validateTeamName($name)
    {
        $container = $this->controller->getContainer();
        if ($container->config->get('scalr.auth_mode') == 'ldap' && $container->config->get('scalr.connections.ldap.user')) {
            try {
                $ldap = $container->ldap(null, null);
                if (empty($ldap->getGroupsDetails([$name]))) {
                    throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND,
                        "Team with name '{$name}' is not found on the directory server"
                    );
                }
            } catch (LdapException $e) {
                throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, $e->getMessage(), $e->getCode(), $e);
            }
        }
    }
}