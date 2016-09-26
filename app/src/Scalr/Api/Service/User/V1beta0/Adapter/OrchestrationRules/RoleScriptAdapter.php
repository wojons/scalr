<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRuleAdapter;
use Scalr\Api\Service\User\V1beta0\Controller\RoleScripts;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\OrchestrationRule;
use Scalr\Model\Entity\RoleScript;

/**
 * Role Script Adapter v1beta0
 *
 * @author N.V.
 *
 * @method  RoleScript  toEntity($data) Converts data to entity
 *
 * @property    RoleScripts $controller;
 */
class RoleScriptAdapter extends OrchestrationRuleAdapter
{

    /**
     * {@inheritdoc}
     * @see OrchestrationRuleAdapter::$entityClass
     */
    protected $entityClass = 'Scalr\Model\Entity\RoleScript';

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        /* @var $entity RoleScript */
        parent::validateEntity($entity);

        //Getting the role initiates check permissions
        $role = $this->controller->getRole($entity->roleId);

        static $agenLessTargets = [
            self::TARGET_VALUE_NULL => self::TARGET_NAME_NULL,
            self::TARGET_VALUE_FARM => self::TARGET_NAME_FARM
        ];

        if (!($role->isScalarized || isset($agenLessTargets[$entity->target]))) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Only targets ['" . implode("', '", $agenLessTargets) . "'] allowed to agent-less roles");
        }

        $this->checkScriptOs($entity, $role->getOs()->family);
    }
}