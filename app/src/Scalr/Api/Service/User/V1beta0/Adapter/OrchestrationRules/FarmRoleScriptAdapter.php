<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRuleAdapter;
use Scalr\Api\Service\User\V1beta0\Controller\FarmRoleScripts;
use Scalr\Exception\InvalidEntityConfigurationException;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmRoleScript;
use Scalr\Model\Entity\FarmRoleScriptingTarget;
use Scalr\Model\Entity\OrchestrationRule;

/**
 * Farm Role Script Adapter v1beta0
 *
 * @author N.V.
 *
 * @method  FarmRoleScript  toEntity($data) Converts data to entity
 *
 * @property    FarmRoleScripts $controller;
 */
class FarmRoleScriptAdapter extends OrchestrationRuleAdapter
{

    /**
     * {@inheritdoc}
     * @see OrchestrationRuleAdapter::$entityClass
     */
    protected $entityClass = 'Scalr\Model\Entity\FarmRoleScript';

    protected static $allowedTargets = [
        self::TARGET_VALUE_NULL,
        self::TARGET_VALUE_FARM,
        self::TARGET_VALUE_TRIGGERING_SERVER,
        self::TARGET_VALUE_TRIGGERING_FARM_ROLE,
        self::TARGET_VALUE_SPECIFIED_FARM_ROLE
    ];

    public static function targetToData(OrchestrationRule $entity, $object)
    {
        /* @var $entity FarmRoleScript */
        parent::targetToData($entity, $object);

        if ($entity->target == static::TARGET_VALUE_SPECIFIED_FARM_ROLE) {
            /* @var $entity FarmRoleScript */
            $farmRole = new FarmRole();
            $targets = new FarmRoleScriptingTarget();
            /* @var $target FarmRole */
            foreach(FarmRole::find([
                ['farmId' => $entity->farmId],
                AbstractEntity::STMT_FROM => "{$farmRole->table()} JOIN {$targets->table('t')} ON {$targets->columnTarget('t')} = {$farmRole->columnAlias()}",
                AbstractEntity::STMT_WHERE => "{$targets->columnFarmRoleScriptId('t')} = {$targets->qstr('farmRoleScriptId', $entity->id)} AND {$targets->columnTargetType('t')} = {$targets->qstr('targetType', OrchestrationRule::TARGET_ROLES)}"
            ]) as $target) {
                $object->target->roles[] = ['id' => $target->id];
            }
        }
    }

    public static function targetToEntity($object, OrchestrationRule $entity)
    {
        /* @var $entity FarmRoleScript */
        parent::targetToEntity($object, $entity);

        if ($entity->target == static::TARGET_VALUE_SPECIFIED_FARM_ROLE) {
            /* @var $entity FarmRoleScript */
            if (empty($object->target->roles) || !is_array($object->target->roles)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Property target.roles must be an array of FarmRoleForeignKeys");
            }

            $roles = $object->target->roles;

            $roleIds = [];

            foreach ($roles as $idx => $role) {
                if (empty($role)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid farm role identifier for target.roles[{$idx}]");
                } else if (is_object($role)) {
                    if (empty($role->id)) {
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property id for target.roles[{$idx}]");
                    }

                    $roleIds[] = $role->id;
                } else {
                    $roleIds[] = $role;
                }
            }

            try {
                $entity->targets = $roleIds;
            } catch (InvalidEntityConfigurationException $e) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, $e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        /* @var $entity FarmRoleScript */
        parent::validateEntity($entity);

        //Getting the role initiates check permissions
        $farmRole = $this->controller->getFarmRole($entity->farmRoleId);

        $this->checkScriptOs($entity, $farmRole->role->getOs()->family);

        static $agenLessTargets = [
            self::TARGET_VALUE_NULL                => self::TARGET_NAME_NULL,
            self::TARGET_VALUE_FARM                => self::TARGET_NAME_FARM,
            self::TARGET_VALUE_SPECIFIED_FARM_ROLE => self::TARGET_NAME_SPECIFIED_FARM_ROLE
        ];

        if (!($farmRole->role->isScalarized || isset($agenLessTargets[$entity->target]))) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Only targets ['" . implode("', '", $agenLessTargets) . "'] are allowed to agent-less roles");
        }

        if ($entity->target == static::TARGET_VALUE_SPECIFIED_FARM_ROLE) {
            foreach ($entity->targets as $farmRoleId => $target) {
                $farmRole = $this->controller->getFarmRole($farmRoleId);

                if ($farmRole->farmId != $entity->farmId) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid target. Farm Role '{$farmRole->id}' not found in Farm '{$entity->farmId}'");
                }

                if (!$farmRole->role->isScalarized) {
                    throw new ApiErrorException(409, ErrorMessage::ERR_INVALID_VALUE, "Invalid target. Only roles that use Scalr Agent are allowed as targets for " . static::TARGET_NAME_SPECIFIED_FARM_ROLE);
                }

                $this->checkScriptOs($entity, $farmRole->role->getOs()->family);
            }
        }
    }
}