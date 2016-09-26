<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Exception;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Exception\ModelException;
use Scalr\Model\Entity\FarmRole;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules\FarmRoleScriptAdapter;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity\FarmRoleScript;
use Scalr\Api\DataType\ErrorMessage;

/**
 * User/FarmRoleScripts API Controller
 *
 * @author N.V.
 */
class FarmRoleScripts extends ApiController
{
    private static $farmRoleControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\FarmRoles';

    /**
     * @var FarmRoles
     */
    private $farmRoleController;

    /**
     * Gets farm role from database using User's Environment
     *
     * @param   int     $farmRoleId          The identifier of the Farm Role
     * @param   bool    $modify     optional Flag checking write permissions
     *
     * @return  FarmRole|null
     *
     * @throws  ApiErrorException
     */
    public function getFarmRole($farmRoleId, $modify = false)
    {
        if (empty($this->farmRoleController)) {
            $this->farmRoleController = $this->getContainer()->api->controller(static::$farmRoleControllerClass);
        }

        return $this->farmRoleController->getFarmRole($farmRoleId, null, $modify);
    }

    /**
     * Retrieves the list of orchestration rules of the role
     *
     * @param   int $farmRoleId Numeric role id
     *
     * @return  array   Returns describe result
     *
     * @throws  ApiErrorException
     */
    public function describeAction($farmRoleId)
    {
        //Getting the role initiates check permissions
        $this->getFarmRole($farmRoleId);

        return $this->adapter('OrchestrationRules\FarmRoleScript')->getDescribeResult([[ 'farmRoleId' => $farmRoleId ]]);
    }

    /**
     * Gets specified orchestration rule
     *
     * @param   int     $ruleId          Numeric identifier of the rule
     * @param   bool    $modify optional Modifying flag
     *
     * @return  FarmRoleScript  Returns the Script Entity on success
     *
     * @throws  ApiErrorException
     */
    public function getRule($ruleId, $modify = false)
    {
        $rule = FarmRoleScript::findPk($ruleId);

        if (!$rule) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Rule either does not exist or is not owned by your environment.");
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
     * @param   int $farmRoleId Unique identifier of a Farm Role
     * @param   int $ruleId     Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function fetchAction($farmRoleId, $ruleId)
    {
        $rule = $this->getRule($ruleId);

        if ($rule->farmRoleId != $farmRoleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the farm role");
        }

        return $this->result($this->adapter('OrchestrationRules\FarmRoleScript')->toData($rule));
    }

    /**
     * Create a new Orchestration Rule for this Role
     *
     * @param   int $farmRoleId Unique identifier of a Farm Role
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function createAction($farmRoleId)
    {
        $object = $this->request->getJsonBody();

        $farmRole = $this->getFarmRole($farmRoleId, true);

        /* @var $ruleAdapter FarmRoleScriptAdapter */
        $ruleAdapter = $this->adapter('OrchestrationRules\FarmRoleScript');

        //Pre validates the request object
        $ruleAdapter->validateObject($object, Request::METHOD_POST);

        if (!isset($object->target)) {
            $object->target->type = FarmRoleScriptAdapter::TARGET_NAME_NULL;
        }

        if (!isset($object->action)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed action");
        }

        $rule = $ruleAdapter->toEntity($object);

        $rule->id = null;
        $rule->farmId = $farmRole->farmId;
        $rule->farmRoleId = $farmRole->id;

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
     * @param   int $farmRoleId Unique identifier of a Farm Role
     * @param   int $ruleId     Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     * @throws  Exception
     */
    public function modifyAction($farmRoleId, $ruleId)
    {
        $object = $this->request->getJsonBody();

        /* @var $ruleAdapter FarmRoleScriptAdapter */
        $ruleAdapter = $this->adapter('OrchestrationRules\FarmRoleScript');

        //Pre validates the request object
        $ruleAdapter->validateObject($object, Request::METHOD_PATCH);

        $rule = $this->getRule($ruleId, true);

        if ($rule->farmRoleId != $farmRoleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the farm role");
        }

        //Copies all alterable properties to fetched Role Entity
        $ruleAdapter->copyAlterableProperties($object, $rule);

        //Re-validates an Entity
        $ruleAdapter->validateEntity($rule);

        //Saves verified results
        $rule->save();

        return $this->result($ruleAdapter->toData($rule));
    }

    /**
     * Delete rule from specified role
     *
     * @param   int $farmRoleId Unique identifier of a Farm Role
     * @param   int $ruleId     Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     *
     */
    public function deleteAction($farmRoleId, $ruleId)
    {
        $rule = $this->getRule($ruleId, true);

        if ($rule->farmRoleId != $farmRoleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the farm role");
        }

        $rule->delete();

        return $this->result(null);
    }
}