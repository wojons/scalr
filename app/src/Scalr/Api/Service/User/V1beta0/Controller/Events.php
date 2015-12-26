<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;

/**
 * User/Version-1/Events API Controller
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (07.05.2015)
 */
class Events extends ApiController
{
    /**
     * Gets Default search criteria for the Account scope
     *
     * @return  array  Returns array of the default search criteria for the Account scope
     */
    private function getDefaultCriteria()
    {
        $parts = [
            ['$and' => [['envId' => null], ['accountId' => null]]],
            ['$and' => [['envId' => null], ['accountId' => $this->getUser()->accountId]]]
        ];

        if ($this->getScope() === ScopeInterface::SCOPE_ENVIRONMENT) {
            $parts[] = ['$and' => [['envId' => $this->getEnvironment()->id], ['accountId' => $this->getUser()->accountId]]];
        }

        return [[ '$or' => $parts ]];
    }

    /**
     * Gets current scope
     *
     * @return string
     */
    public function getScope()
    {
        return $this->getEnvironment() ? ScopeInterface::SCOPE_ENVIRONMENT : ScopeInterface::SCOPE_ACCOUNT;
    }

    /**
     * Gets event from database using User's Account
     *
     * @param    string     $eventId                     The identifier of the Event
     * @param    bool       $restrictToCurrentScope      optional Whether it should additionally check that event corresponds to current scope
     * @throws   ApiErrorException
     * @return   \Scalr\Model\Entity\EventDefinition|null Returns Event Definition entity on success or NULL otherwise
     */
    public function getEvent($eventId, $restrictToCurrentScope = false)
    {
        $criteria = $this->getDefaultCriteria();
        $criteria[] = ['name' => $eventId];

        /* @var $event Entity\EventDefinition */
        $event = Entity\EventDefinition::findOne($criteria);

        $scopeName = ucfirst($this->getScope());

        if (!$event) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf("The Event either does not exist or isn't in scope for the current %s.", $scopeName));
        }

        //To be over-suspicious check READ access to Event object
        $this->checkPermissions($event);

        if ($restrictToCurrentScope
            && ($event->getScope() !== $this->getScope()
                || $event->accountId !== $this->getUser()->accountId
                || ($this->getEnvironment() && $event->envId !== $this->getEnvironment()->id))
        ) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION,
                sprintf("The Event is not either from the %s scope or owned by your %s.", $scopeName, $scopeName)
            );
        }

        return $event;
    }

    /**
     * Gets Events list
     */
    public function describeAction()
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS);

        return $this->adapter('event')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Fetches detailed info about the Event
     *
     * @param    string $eventId Unique identifier of the Event
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function fetchAction($eventId)
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS);

        $event = $this->getEvent($eventId);

        return $this->result($this->adapter('event')->toData($event));
    }

    /**
     * Creates new event in current scope
     *
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     * @throws \Scalr\Exception\ModelException
     */
    public function createAction()
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS, Acl::PERM_GENERAL_CUSTOM_EVENTS_MANAGE);

        $object = $this->request->getJsonBody();

        $eventAdapter = $this->adapter('event');

        //Pre validates the request object
        $eventAdapter->validateObject($object, Request::METHOD_POST);

        if (empty($object->id)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Required field 'id' is missing.");
        }

        $object->scope = $this->getScope();

        $criteria = [[ 'name' => $object->id ]];

        switch ($this->getScope()) {
            case ScopeInterface::SCOPE_ACCOUNT:
                $criteria[] = [ '$or' => [[ '$and' => [['envId' => null], ['accountId' => null]] ], ['accountId' => $this->getUser()->getAccountId()]] ];
                break;

            case ScopeInterface::SCOPE_ENVIRONMENT:
                $criteria[] = ['$and' => [['envId' => $this->getEnvironment()->id], ['accountId' => $this->getUser()->getAccountId()]]];
                break;

            default:
                throw new ApiErrorException(500, ErrorMessage::ERR_NOT_IMPLEMENTED, sprintf("The Scope '%s' has not been implemented yet", $this->getScope()));
        }

        /* @var $oldEvent Entity\EventDefinition */
        $oldEvent = Entity\EventDefinition::findOne($criteria);

        if (!empty($oldEvent)) {
            if ($this->getScope() == ScopeInterface::SCOPE_ACCOUNT && $this->request->get('replace', false)) {
                $replacements = Entity\EventDefinition::find([
                    ['name'      => $object->id],
                    ['accountId' => $this->getUser()->getAccountId()],
                    ['envId'     => ['$ne' => null]]
                ]);

                if ($replacements->count()) {
                    foreach ($replacements as $lowerEvent) {
                        $lowerEvent->delete();
                    }
                } else {
                    throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf('Event with id %s already exists', $object->id));
                }
            } else {
                throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf('Event with id %s already exists', $object->id));
            }
        }

        /* @var $event Entity\EventDefinition */
        //Converts object into EventDefinition entity
        $event = $eventAdapter->toEntity($object);

        $event->id = null;

        $eventAdapter->validateEntity($event);

        //Saves entity
        $event->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($eventAdapter->toData($event));
    }

    /**
     * Modifies event from current scope
     *
     * @param string $eventId   Unique identifier of the Event
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     * @throws \Exception
     * @throws \Scalr\Exception\ModelException
     */
    public function modifyAction($eventId)
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS, Acl::PERM_GENERAL_CUSTOM_EVENTS_MANAGE);

        $object = $this->request->getJsonBody();

        $eventAdapter = $this->adapter('event');

        //Pre validates the request object
        $eventAdapter->validateObject($object, Request::METHOD_PATCH);

        $event = $this->getEvent($eventId, true);

        //Copies all alterable properties to fetched Role Entity
        $eventAdapter->copyAlterableProperties($object, $event);

        //Re-validates an Entity
        $eventAdapter->validateEntity($event);

        //Saves verified results
        $event->save();

        return $this->result($eventAdapter->toData($event));
    }

    /**
     * Deletes the event from the curent scope
     *
     * @param string  $eventId   Unique identifier of the Event
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     * @throws \Scalr\Exception\ModelException
     */
    public function deleteAction($eventId)
    {
        $this->checkPermissions(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS, Acl::PERM_GENERAL_CUSTOM_EVENTS_MANAGE);

        $event = $this->getEvent($eventId, true);

        $event->delete();

        return $this->result(null);
    }

}