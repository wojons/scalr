<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use EVENT_TYPE;
use InvalidArgumentException;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Entity\EventDefinition;
use Scalr\Model\Entity\OrchestrationRule;
use Scalr\Model\Entity\RoleScript;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;

/**
 * Orchestration Rule Adapter v1beta0
 *
 * @author N.V.
 *
 * @method  OrchestrationRule   toEntity($data) Converts data to entity
 */
class OrchestrationRuleAdapter extends ApiEntityAdapter
{
    /**
     * Actions types
     */
    const ACTION_SCRIPT = 'ScriptAction';
    const ACTION_URI    = 'UriAction';
    const ACTION_CHEF   = 'ChefAction';

    /**
     * Triggers types
     */
    const TRIGGER_ALL_EVENTS    = 'AllEventsTrigger';
    const TRIGGER_SINGLE_EVENT  = 'SpecificEventTrigger';

    /**
     * Targets names
     */
    const TARGET_NAME_NULL                  = 'NullTarget';
    const TARGET_NAME_FARM                  = 'FarmTarget';
    const TARGET_NAME_TRIGGERING_SERVER     = 'TriggeringServerTarget';
    const TARGET_NAME_TRIGGERING_FARM_ROLE  = 'TriggeringFarmRoleTarget';
    const TARGET_NAME_SPECIFIED_FARM_ROLE   = 'SelectedFarmRolesTarget';

    /**
     * Targets values
     */
    const TARGET_VALUE_NULL                 = '';
    const TARGET_VALUE_FARM                 = Script::TARGET_FARM;
    const TARGET_VALUE_TRIGGERING_SERVER    = Script::TARGET_INSTANCE;
    const TARGET_VALUE_TRIGGERING_FARM_ROLE = Script::TARGET_ROLE;
    const TARGET_VALUE_SPECIFIED_FARM_ROLE  = Script::TARGET_FARMROLES;

    protected static $targetMap = [
        self::TARGET_NAME_NULL                  => self::TARGET_VALUE_NULL,
        self::TARGET_NAME_FARM                  => self::TARGET_VALUE_FARM,
        self::TARGET_NAME_TRIGGERING_SERVER     => self::TARGET_VALUE_TRIGGERING_SERVER,
        self::TARGET_NAME_TRIGGERING_FARM_ROLE  => self::TARGET_VALUE_TRIGGERING_FARM_ROLE,
        self::TARGET_NAME_SPECIFIED_FARM_ROLE   => self::TARGET_VALUE_SPECIFIED_FARM_ROLE
    ];

    protected static $allowedTargets = [
        self::TARGET_VALUE_NULL,
        self::TARGET_VALUE_FARM,
        self::TARGET_VALUE_TRIGGERING_SERVER,
        self::TARGET_VALUE_TRIGGERING_FARM_ROLE
    ];

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', '_trigger' => 'trigger', '_target' => 'target', '_action' => 'action', 'timeout', 'runAs',
            'orderIndex' => 'order', 'issync' => 'blocking',
            '_scope' => 'scope'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['trigger', 'target', 'action', 'runAs', 'order', 'blocking'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'runAs', 'order', 'blocking'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]]
    ];

    /**
     * {@inheritdoc}
     * @see BaseAdapter::$entityClass
     */
    protected $entityClass = 'Scalr\Model\Entity\OrchestrationRule';

    public static function targetToData(OrchestrationRule $entity, $object)
    {
        $rules = array_flip(static::$targetMap);

        $object->target = (object) [
            'targetType' => $rules[$entity->target]
        ];
    }

    public static function targetToEntity($object, OrchestrationRule $entity)
    {
        if (!isset(static::$targetMap[$object->target->targetType])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected target type!");
        }

        $entity->target = static::$targetMap[$object->target->targetType];

        if (!in_array($entity->target, static::$allowedTargets)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Target '{$object->target->targetType}' not allowed for this rule");
        }
    }

    public function _scope($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from OrchestrationRule */
                $to->scope = $from->getScope();
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ ]];
        }
    }

    public function _action($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from OrchestrationRule */
                switch ($from->scriptType) {
                    case OrchestrationRule::ORCHESTRATION_RULE_TYPE_SCALR:
                        $to->action = [
                            'actionType' => static::ACTION_SCRIPT,
                            'scriptVersion' => [
                                'script' => [
                                    'id' => $from->scriptId
                                ]
                            ]
                        ];

                        if(!empty($from->version)) {
                            $to->action['scriptVersion']['version'] = $from->version;
                        }
                        break;

                    case OrchestrationRule::ORCHESTRATION_RULE_TYPE_LOCAL:
                        $to->action = [
                            'actionType' => static::ACTION_URI,
                            'path' => $from->scriptPath
                        ];
                        break;

                    case OrchestrationRule::ORCHESTRATION_RULE_TYPE_CHEF:
                        $to->action = ['actionType' => static::ACTION_CHEF];
                        break;
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to OrchestrationRule */
                $action = $from->action;

                if (empty($action->actionType)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed action type");
                }

                switch ($action->actionType) {
                    case static::ACTION_SCRIPT:
                        $scriptId = ApiController::getBareId($from->action->scriptVersion, 'script');

                        if (empty($scriptId)){
                            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property script.id");
                        }

                        $to->scriptId = $scriptId;
                        $from->action->scriptVersion->version = $from->action->scriptVersion->version ?: ScriptVersion::LATEST_SCRIPT_VERSION;
                        $to->version = $from->action->scriptVersion->version;

                        $to->scriptType = OrchestrationRule::ORCHESTRATION_RULE_TYPE_SCALR;
                        break;

                    case static::ACTION_URI:
                        if (empty($action->path)) {
                            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property path");
                        }

                        $to->scriptPath = $action->path;

                        $to->scriptType = OrchestrationRule::ORCHESTRATION_RULE_TYPE_LOCAL;
                        break;

                    case static::ACTION_CHEF:
                        $to->scriptType = OrchestrationRule::ORCHESTRATION_RULE_TYPE_CHEF;
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected actionType");
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                break;
        }
    }

    public function _trigger($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from OrchestrationRule */
                switch ($from->eventName) {
                    case '*':
                        $to->trigger = [
                            'triggerType' => static::TRIGGER_ALL_EVENTS
                        ];
                        break;

                    default:
                        $to->trigger = [
                            'triggerType' => static::TRIGGER_SINGLE_EVENT,
                            'event' => [
                                'id' => $from->eventName
                            ]
                        ];
                        break;
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to OrchestrationRule */
                if (empty($from->trigger->triggerType)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed trigger type");
                }

                switch($from->trigger->triggerType) {
                    case static::TRIGGER_ALL_EVENTS:
                        $to->eventName = '*';
                        break;

                    case static::TRIGGER_SINGLE_EVENT:
                        if (!($event = ApiController::getBareId($from->trigger, 'event'))) {
                            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property trigger:id");
                        }

                        $to->eventName = $event;
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected trigger type");
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ 'eventName' => $from->trigger ]];
        }
    }

    public function _target($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from OrchestrationRule */
                static::targetToData($from, $to);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to OrchestrationRule */
                if (empty($from->target->targetType)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed target type");
                }

                static::targetToEntity($from, $to);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                if (empty($from->target->targetType)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed target type");
                }

                if (!isset(static::$targetMap[$from->target->targetType])) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected target type!");
                }

                return [[ 'target' => static::$targetMap[$from->target->targetType]]];
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        /* @var $entity OrchestrationRule */
        $entityClass = $this->entityClass;

        if (!$entity instanceof $entityClass) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of {$entityClass} class"
            ));
        }

        if ($entity->id !== null) {
            if (!$entityClass::findPk($entity->id)) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find out the rule with ID: %d", $entity->id
                ));
            }
        }

        if (!empty($entity->scriptId)) {
            if ($entity->version == ScriptVersion::LATEST_SCRIPT_VERSION) {
                $found = ScriptVersion::findOne([['scriptId' => $entity->scriptId]], null, ['version' => false]);
            } else {
                $found = ScriptVersion::findPk($entity->scriptId, $entity->version);
            }

            /* @var $found ScriptVersion */
            if (empty($found) || !$found->hasAccessPermissions($this->controller->getUser(), $this->controller->getEnvironment())) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find version %d of the script with ID: %d", $entity->version, $entity->scriptId
                ));
            }
        }

        if (empty($entity->eventName)) {
            $entity->eventName = '*';
        } else {
            if ($entity->eventName !== '*') {
                if (array_key_exists($entity->eventName, array_merge(
                        EVENT_TYPE::getScriptingEventsWithScope(),
                        EventDefinition::getList(
                            $this->controller->getUser()->id,
                            $this->controller->getEnvironment()->id
                        ))) === false) {
                    throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Could not find out the event '{$entity->eventName}'");
                }

                if ($entity->scriptType == OrchestrationRule::ORCHESTRATION_RULE_TYPE_CHEF && in_array($entity->eventName, EVENT_TYPE::getChefRestrictedEvents())) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Chef can't be used with {$entity->eventName}");
                }

                if ($entity->eventName == EVENT_TYPE::BEFORE_INSTANCE_LAUNCH && $entity->target == Script::TARGET_INSTANCE) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Event '{$entity->eventName}' will never be handled by the triggering server");
                }
            }
        }

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }
    }

    /**
     * Checks compliance OS of the script and the role
     *
     * @param   OrchestrationRule $entity Orchestration Rule entity
     * @param   string            $os     OS type
     *
     * @throws  ApiErrorException    If mismatch
     */
    public function checkScriptOs(OrchestrationRule $entity, $os)
    {
        if (!empty($entity->scriptId)) {
            if (Script::findPk($entity->scriptId)->os == 'windows' && $os != 'windows') {
                throw new ApiErrorException(409, ErrorMessage::ERR_OS_MISMATCH, "Script OS family does not match role OS family");
            }
        }
    }
}