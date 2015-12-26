<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules;

use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRuleAdapter;
use Scalr\Api\Service\User\V1beta0\Controller\AccountScripts;
use Scalr\Model\Entity\AccountScript;

/**
 * Account Script Adapter v1beta0
 *
 * @author N.V.
 *
 * @method  AccountScript   toEntity($data) Converts data to entity
 *
 * @property    AccountScripts  $controller;
 */
class AccountScriptAdapter extends OrchestrationRuleAdapter
{

    /**
     * {@inheritdoc}
     * @see OrchestrationRuleAdapter::$entityClass
     */
    protected $entityClass = 'Scalr\Model\Entity\AccountScript';

    public static $allowedTargets = [
        self::TARGET_VALUE_NULL,
        self::TARGET_VALUE_TRIGGERING_SERVER
    ];
}