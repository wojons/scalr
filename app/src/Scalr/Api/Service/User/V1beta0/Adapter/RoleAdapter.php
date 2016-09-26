<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use ROLE_BEHAVIORS;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\DataType\ScopeInterface;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Model\Entity;
use Scalr\Model\Entity\Role;

/**
 * RoleAdapter V1
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (05.03.2015)
 */
class RoleAdapter extends ApiEntityAdapter
{
    const BEHAVIOR_MYSQL    = 'mysql';
    const BEHAVIOR_APACHE   = 'apache';
    const BEHAVIOR_NGINX    = 'nginx';

    protected static $supportedBehaviors = [
        ROLE_BEHAVIORS::BASE        => ROLE_BEHAVIORS::BASE,
        ROLE_BEHAVIORS::CHEF        => ROLE_BEHAVIORS::CHEF,
        self::BEHAVIOR_MYSQL        => ROLE_BEHAVIORS::MYSQL2,
        ROLE_BEHAVIORS::PERCONA     => ROLE_BEHAVIORS::PERCONA,
        ROLE_BEHAVIORS::MARIADB     => ROLE_BEHAVIORS::MARIADB,
        ROLE_BEHAVIORS::POSTGRESQL  => ROLE_BEHAVIORS::POSTGRESQL,
        ROLE_BEHAVIORS::MONGODB     => ROLE_BEHAVIORS::MONGODB,
        ROLE_BEHAVIORS::REDIS       => ROLE_BEHAVIORS::REDIS,
        self::BEHAVIOR_APACHE       => ROLE_BEHAVIORS::APACHE,
        self::BEHAVIOR_NGINX        => ROLE_BEHAVIORS::NGINX,
        ROLE_BEHAVIORS::TOMCAT      => ROLE_BEHAVIORS::TOMCAT,
        ROLE_BEHAVIORS::HAPROXY     => ROLE_BEHAVIORS::HAPROXY,
        ROLE_BEHAVIORS::RABBITMQ    => ROLE_BEHAVIORS::RABBITMQ,
        ROLE_BEHAVIORS::MEMCACHED   => ROLE_BEHAVIORS::MEMCACHED
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
            'id', 'name', 'description', 'isQuickStart' => 'quickStart', 'isDeprecated' => 'deprecated',
            'isScalarized' => 'useScalrAgent',
            '_scope'             => 'scope',
            '_os'                => 'os',
            '_category'          => 'category',
            '_builtinAutomation' => 'builtinAutomation'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'os', 'category', 'quickStart', 'deprecated'],

        self::RULE_TYPE_FILTERABLE  => ['name', 'id', 'os', 'category', 'scope', 'quickStart', 'deprecated', 'useScalrAgent', 'builtinAutomation'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Model\Entity\Role';

    /**
     * Transforms internal names of given behaviors to use in API
     *
     * @param   array   $behaviors  Array of a Role behaviors
     *
     * @return  array   Returns an array of names of behaviors
     */
    public static function behaviorsToData(array $behaviors)
    {
        return array_merge(array_keys(array_intersect(static::$supportedBehaviors, $behaviors)), array_diff($behaviors, static::$supportedBehaviors));
    }

    /**
     * Transforms given names of behaviors used in API to internal representation
     *
     * @param   array   $behaviors               Array of a Role behaviors
     * @param   bool    $onlySupported  optional Converts only available to users behavior
     *
     * @return  array   Returns an array of names of behaviors
     *
     * @throws  ApiErrorException   If the behavior is not supported and $onlySupported is flagged
     */
    public static function behaviorsToEntity(array $behaviors, $onlySupported = true)
    {
        $unsupported = array_diff($behaviors, array_keys(static::$supportedBehaviors));

        if ($onlySupported && !empty($unsupported)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf("Unknown automations: ['%s']", implode("', '", $unsupported)));
        }

        $converted = array_values(array_intersect_key(static::$supportedBehaviors, array_flip($behaviors)));

        return $onlySupported ? $converted : array_merge($converted, $unsupported);
    }

    protected function _scope($from, $to, $action)
    {
        if ($action == self::ACT_CONVERT_TO_OBJECT) {
            $to->scope = $from->getScope();
        } else if ($action == self::ACT_CONVERT_TO_ENTITY) {
            if (empty ($from->scope)) {
                $from->scope = $this->controller->getScope();
            }

            switch ($from->scope) {
                case ScopeInterface::SCOPE_SCALR:
                    $to->accountId = null;
                    $to->envId = null;
                    $to->origin = Entity\Role::ORIGIN_SHARED;
                    break;

                case ScopeInterface::SCOPE_ACCOUNT:
                    $to->accountId = $this->controller->getUser()->accountId;
                    $to->envId = null;
                    $to->origin = Entity\Role::ORIGIN_CUSTOM;
                    break;

                case ScopeInterface::SCOPE_ENVIRONMENT:
                    $to->accountId = $this->controller->getUser()->accountId;
                    $to->envId = $this->controller->getEnvironment()->id;
                    $to->origin = Entity\Role::ORIGIN_CUSTOM;
                    break;

                default:
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
            }
        } else if ($action == self::ACT_GET_FILTER_CRITERIA) {
            if (empty($from->scope)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
            }

            if ($from->scope === ScopeInterface::SCOPE_SCALR) {
                return [['accountId' => null], ['envId' => null]];
            } else if ($from->scope === ScopeInterface::SCOPE_ACCOUNT) {
                return [['accountId' => $this->controller->getUser()->accountId], ['envId' => null]];
            } else if ($from->scope === ScopeInterface::SCOPE_ENVIRONMENT && $this->controller->getEnvironment() !== null) {
                return [['envId' => $this->controller->getEnvironment()->id]];
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
            }
        }
    }

    protected function _os($from, $to, $action)
    {
        if ($action == self::ACT_CONVERT_TO_OBJECT) {
            $to->os = !empty($from->osId) ? ['id' => $from->osId] : null;
        } else if ($action == self::ACT_CONVERT_TO_ENTITY) {
            $osId = ApiController::getBareId($from, 'os');
            if (empty($osId)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'os.id'");
            } else if (!(is_string($osId) && preg_match('/^' . Entity\Os::ID_REGEXP . '$/', $osId))) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the OS");
            }
            $to->osId = $osId;
        } else if ($action == self::ACT_GET_FILTER_CRITERIA) {
            $osId = ApiController::getBareId($from, 'os');
            if (!(is_string($osId) && preg_match('/^' . Entity\Os::ID_REGEXP . '$/', $osId))) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the OS");
            }
            return [['osId' => $osId]];
        }
    }

    protected function _category($from, $to, $action)
    {
        if ($action == self::ACT_CONVERT_TO_OBJECT) {
            $to->category = !empty($from->catId) ? ['id' => $from->catId] : null;
        } else if ($action == self::ACT_CONVERT_TO_ENTITY) {
            $categoryId = ApiController::getBareId($from, 'category');

            if (!empty($categoryId)) {
                if (!is_numeric($categoryId)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the category");
                }
                $to->catId = $categoryId;
            } else {
                $to->catId = null;
            }
        } else if ($action == self::ACT_GET_FILTER_CRITERIA) {
            if (empty($from->category)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the category");
            }

            return [['catId' => ApiController::getBareId($from, 'category')]];
        }
    }

    protected function _builtinAutomation($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Role */
                if ($from->isScalarized) {
                    $to->builtinAutomation = static::behaviorsToData($from->getBehaviors());
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Role */
                if (!is_array($from->builtinAutomation)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Property builtinAutomation must be an array");
                }

                $behaviors = array_unique($from->builtinAutomation);

                if (count($behaviors) != count($from->builtinAutomation)) {
                    throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, "Builtin automation list contains duplicates");
                }

                $behaviors = static::behaviorsToEntity($behaviors);
                $conflicts = Role::getBehaviorsConflicts($behaviors);

                if (!empty($conflicts)) {
                    throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf("The following behaviors can not be combined: [%s]", implode(', ', static::behaviorsToData($conflicts))));
                }

                $to->setBehaviors($behaviors);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['behaviors' => ['$regex' => implode('|', static::behaviorsToEntity((array) $from->builtinAutomation, false))]]];
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!($entity instanceof Entity\Role)) {
            throw new \InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\Role class"
            ));
        }

        if ($entity->id !== null) {
            if (!is_integer($entity->id)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid value of the identifier");
            }

            //Checks if the role does exist
            if (!Entity\Role::findPk($entity->id)) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find out the Role with ID: %d", $entity->id
                ));
            }
        }

        //Is this a new Role
        if (!$entity->id) {
            $entity->addedByEmail = $this->controller->getUser()->email;
            $entity->addedByUserId = $this->controller->getUser()->id;
        }

        if (!Role::isValidName($entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid name of the Role");
        }

        $envId = $this->controller->getScope() === ScopeInterface::SCOPE_ENVIRONMENT ? $this->controller->getEnvironment()->id : null;
        if (Role::isNameUsed($entity->name, $this->controller->getUser()->accountId, $envId, $entity->id)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, 'Selected role name is already used. Please select another one.');
        }

        $entity->description = $entity->description ?: '';
        $this->validateString($entity->description, 'Invalid description');

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        //We only allow to either create or modify Environment Scope Roles
        if ($entity->getScope() !== $this->controller->getScope()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf(
                "Invalid scope"
            ));
        }

        //Checks the Role Category
        if (!empty($entity->catId)) {
            //Tries to find out the specified Role category
            $category = Entity\RoleCategory::findPk($entity->catId);
            if ($category instanceof Entity\RoleCategory) {
                //Checks if the specified RoleCategory either shared or belongs to User's scope.
                if ($category->getScope() !== ScopeInterface::SCOPE_SCALR &&
                    $category->envId !== $this->controller->getEnvironment()->id) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE,
                        "The specified category isn't owned by your environment."
                    );
                }
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "The Role category does not exist");
            }
        } else {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE,
                "Role category should be provided with the request."
            );
        }

        if (empty($entity->osId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'os.id'");
        }

        //Tries to find out the specified OS
        if (empty(Entity\Os::findPk($entity->osId))) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "OS with ID: '{$entity->osId}' is not found.");
        }

        if (empty($entity->isScalarized)) {
            if (!empty($entity->behaviors)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'builtinAutomation property requires Scalr agent to be set');
            }
        } else if (empty($entity->behaviors)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property builtinAutomation");
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateObject()
     */
    public function validateObject($object, $method = null)
    {
        parent::validateObject($object, $method);

        if (isset($object->scope) && $object->scope !== $this->controller->getScope()) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope");
        }
    }
}
