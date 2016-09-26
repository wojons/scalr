<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Model\Entity;

/**
 * EventAdapter V1
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (07.05.2015)
 */
class EventAdapter extends ApiEntityAdapter
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
            'description', 'name' => 'id', '_scope' => 'scope'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['description'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'scope'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['created' => true]],
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Model\Entity\EventDefinition';

    protected function _scope($from, $to, $action)
    {
        if ($action == self::ACT_CONVERT_TO_OBJECT) {
            $to->scope = $from->getScope();
        } else if ($action == self::ACT_CONVERT_TO_ENTITY) {
            if (empty($from->scope) || $from->scope === ScopeInterface::SCOPE_ACCOUNT) {
                //Default is account scope
                $to->accountId = $this->controller->getUser()->accountId;
                $to->envId = null;
            } else if ($from->scope === ScopeInterface::SCOPE_SCALR) {
                $to->accountId = null;
                $to->envId = null;
            } else if ($from->scope === ScopeInterface::SCOPE_ENVIRONMENT) {
                $to->accountId = $this->controller->getUser()->accountId;
                $to->envId = $this->controller->getEnvironment()->id;
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
            }
        } else if ($action == self::ACT_GET_FILTER_CRITERIA) {
            if (empty($from->scope)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
            }

            if ($from->scope === ScopeInterface::SCOPE_SCALR) {
                return [['accountId' => null], ['envId' => null]];
            } else if ($from->scope === ScopeInterface::SCOPE_ACCOUNT) {
                return [['accountId' => $this->controller->getUser()->accountId], ['envId' => null]];
            } else if ($from->scope === ScopeInterface::SCOPE_ENVIRONMENT) {
                return [['accountId' => $this->controller->getUser()->accountId], ['envId' => $this->controller->getEnvironment()->id]];
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!($entity instanceof Entity\EventDefinition)) {
            throw new \InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\EventDefinition class"
            ));
        }

        if ($entity->id !== null) {
            //Checks if the event does exist
            if (!Entity\EventDefinition::findPk($entity->id)) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find out the Event with ID: %d", $entity->name
                ));
            }
        }

        if (!preg_match('/^' . Entity\EventDefinition::NAME_REGEXP . '$/', $entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid id of the Event");
        }

        $entity->description = $entity->description ?: '';
        $this->validateString($entity->description, 'Invalid description');

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        //We only allow to either create or modify Account or Environment Scope Events
        if ($entity->getScope() !== $this->controller->getScope()) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf(
                "Invalid scope"
            ));
        }
    }

}