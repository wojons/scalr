<?php

namespace Scalr\Api\Service\Account\V1beta0\Controller;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\Account\V1beta0\Adapter\TeamAdapter;
use Scalr\Model\Entity\Account\Team;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Exception\ModelException;

/**
 * Account/Teams API Controller
 *
 * @author N.V.
 */
class Teams extends ApiController
{

    /**
     * Gets default search criteria according request scope
     *
     * @return  array   Returns array of the search criteria
     */
    public function getDefaultCriteria()
    {
        return [['accountId' => $this->getUser()->getAccountId()]];
    }

    /**
     * Retrieves the list of the Account Teams
     *
     * @return ListResultEnvelope Returns describe result
     * @throws ApiErrorException
     */
    public function describeAction()
    {
        //We do not check Acl in this action because we have to allow Users select any Team from the account
        return $this->adapter('team')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Gets Team entity
     *
     * @param string $teamId Unique identifier of the Team
     *
     * @return Team
     * @throws  ApiErrorException
     */
    public function getTeam($teamId)
    {
        /* @var $team Team */
        $team = Team::findOne(array_merge($this->getDefaultCriteria(), [['id' => $teamId]]));

        if (empty($team)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Team either does not exist or is not owned by your account.");
        }

        return $team;
    }

    /**
     * Fetches detailed info about one Account Team
     *
     * @param int $teamId Identifier of the Team
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function fetchAction($teamId)
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        return $this->result($this->adapter('team')->toData($this->getTeam($teamId)));
    }

    /**
     * Creates a new Account Teams
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function createAction()
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        $object = $this->request->getJsonBody();

        /* @var $teamAdapter TeamAdapter */
        $teamAdapter = $this->adapter('team');

        //Pre validates the request object
        $teamAdapter->validateObject($object, Request::METHOD_POST);

        $team = $teamAdapter->toEntity($object);
        $team->id = null;
        $team->accountId = $this->getUser()->getAccountId();

        $teamAdapter->validateEntity($team);

        //Saves entity
        $team->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($teamAdapter->toData($team));
    }

    /**
     * Change Account Team attributes.
     *
     * @param int $teamId Identifier of the Team
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function modifyAction($teamId)
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        $object = $this->request->getJsonBody();

        /* @var $teamAdapter TeamAdapter */
        $teamAdapter = $this->adapter('team');

        //Pre validates the request object
        $teamAdapter->validateObject($object, Request::METHOD_PATCH);

        $team = $this->getTeam($teamId);

        //Copies all alterable properties to fetched Role Entity
        $teamAdapter->copyAlterableProperties($object, $team);

        //Re-validates an Entity
        $teamAdapter->validateEntity($team);

        //Saves verified results
        $team->save();

        return $this->result($teamAdapter->toData($team));
    }

    /**
     * Delete an Account Team
     *
     * @param  int $teamId Identifier of the Team
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function deleteAction($teamId)
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        $this->getTeam($teamId)->delete();

        return $this->result(null);
    }
}