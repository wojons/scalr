<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Exception;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules\AccountScriptAdapter;
use Scalr\Exception\ModelException;
use Scalr\Model\Entity\AccountScript;

/**
 * Account/Version-1beta0/AccountScripts API Controller
 *
 * @author N.V.
 */
class AccountScripts extends ApiController
{
    /**
     * Retrieves the list of account orchestration rules
     *
     * @return  array   Returns describe result
     *
     * @throws  ApiErrorException
     */
    public function describeAction()
    {
        return $this->adapter('OrchestrationRules\AccountScript')->getDescribeResult([[ 'accountId' => $this->getUser()->getAccountId() ]]);
    }

    /**
     * Gets specified orchestration rule
     *
     * @param   int     $ruleId          Numeric identifier of the rule
     * @param   bool    $modify optional Modifying flag
     *
     * @return  AccountScript  Returns the Script Entity on success
     *
     * @throws  ApiErrorException
     */
    public function getRule($ruleId, $modify = false)
    {
        $rule = AccountScript::findPk($ruleId);

        if (!$rule) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Rule either does not exist or is not owned by your account.");
        }

        if (!$this->hasPermissions($rule, $modify)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $rule;
    }

    /**
     * Fetches detailed info about specified rule
     *
     * @param   int $ruleId Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function fetchAction($ruleId)
    {
        return $this->result($this->adapter('OrchestrationRules\AccountScript')->toData($this->getRule($ruleId)));
    }

    /**
     * Create a new Orchestration Rule for an Account
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function createAction()
    {
        $object = $this->request->getJsonBody();

        /* @var $ruleAdapter AccountScriptAdapter */
        $ruleAdapter = $this->adapter('OrchestrationRules\AccountScript');

        //Pre validates the request object
        $ruleAdapter->validateObject($object, Request::METHOD_POST);

        if (!isset($object->target)) {
            $object->target->type = AccountScriptAdapter::TARGET_NAME_NULL;
        }

        if (!isset($object->action)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed action");
        }

        $rule = $ruleAdapter->toEntity($object);

        $rule->id = null;
        $rule->accountId = $this->getUser()->getAccountId();

        $ruleAdapter->validateEntity($rule);

        //Saves entity
        $rule->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($ruleAdapter->toData($rule));
    }

    /**
     * Change rule attributes.
     *
     * @param   int $ruleId Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     * @throws  Exception
     */
    public function modifyAction($ruleId)
    {
        $object = $this->request->getJsonBody();

        /* @var $ruleAdapter AccountScriptAdapter */
        $ruleAdapter = $this->adapter('OrchestrationRules\AccountScript');

        //Pre validates the request object
        $ruleAdapter->validateObject($object, Request::METHOD_PATCH);

        $rule = $this->getRule($ruleId, true);

        //Copies all alterable properties to fetched Role Entity
        $ruleAdapter->copyAlterableProperties($object, $rule);

        //Re-validates an Entity
        $ruleAdapter->validateEntity($rule);

        //Saves verified results
        $rule->save();

        return $this->result($ruleAdapter->toData($rule));
    }

    /**
     * Delete rule from account
     *
     * @param   int $ruleId Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     *
     */
    public function deleteAction($ruleId)
    {
        $rule = $this->getRule($ruleId, true);

        $rule->delete();

        return $this->result(null);
    }
}