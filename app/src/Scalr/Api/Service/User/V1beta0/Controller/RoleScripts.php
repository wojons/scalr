<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Model\Entity\Role;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Entity\RoleScript;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Exception\ModelException;
use Scalr\Api\Service\User\V1beta0\Adapter\OrchestrationRules\RoleScriptAdapter;
use Exception;

/**
 * User/Version-1beta0/RoleScripts API Controller
 *
 * @author N.V.
 */
class RoleScripts extends ApiController
{
    private static $roleControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\Roles';

    /**
     * @var Roles
     */
    private $roleController;

    /**
     * Gets role from database using User's Environment
     *
     * @param   int     $roleId          The identifier of the Role
     * @param   bool    $modify optional Flag checking write permissions
     *
     * @return  Role|null
     *
     * @throws  ApiErrorException
     */
    public function getRole($roleId, $modify = false)
    {
        if (empty($this->roleController)) {
            $this->roleController = $this->getContainer()->api->controller(static::$roleControllerClass);
        }

        return $this->roleController->getRole($roleId, $modify);
    }

    /**
     * Retrieves the list of orchestration rules of the role
     *
     * @param   int $roleId Numeric role id
     *
     * @return  array   Returns describe result
     *
     * @throws  ApiErrorException
     */
    public function describeAction($roleId)
    {
        //Getting the role initiates check permissions
        $this->getRole($roleId);

        return $this->adapter('OrchestrationRules\RoleScript')->getDescribeResult([[ 'roleId' => $roleId ]]);
    }

    /**
     * Gets specified orchestration rule
     *
     * @param   int     $ruleId          Numeric identifier of the rule
     * @param   bool    $modify optional Modifying flag
     *
     * @return  RoleScript  Returns the Script Entity on success
     *
     * @throws  ApiErrorException
     */
    public function getRule($ruleId, $modify = false)
    {
        $rule = RoleScript::findPk($ruleId);

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
     * @param   int $roleId Numeric identifier of the script
     * @param   int $ruleId Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function fetchAction($roleId, $ruleId)
    {
        $rule = $this->getRule($ruleId);

        if ($rule->roleId != $roleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the role");
        }

        return $this->result($this->adapter('OrchestrationRules\RoleScript')->toData($rule));
    }

    /**
     * Create a new Orchestration Rule for this Role
     *
     * @param   int $roleId Numeric identifier of the role
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function createAction($roleId)
    {
        $object = $this->request->getJsonBody();

        /* @var $ruleAdapter RoleScriptAdapter */
        $ruleAdapter = $this->adapter('OrchestrationRules\RoleScript');

        //Pre validates the request object
        $ruleAdapter->validateObject($object, Request::METHOD_POST);

        if (!isset($object->target)) {
            $object->target->type = RoleScriptAdapter::TARGET_NAME_NULL;
        }

        if (!isset($object->action)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed action");
        }

        $rule = $ruleAdapter->toEntity($object);

        $rule->id = null;
        $rule->roleId = $roleId;

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
     * @param   int $roleId Unique identifier of the role
     * @param   int $ruleId Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     * @throws  Exception
     */
    public function modifyAction($roleId, $ruleId)
    {
        $object = $this->request->getJsonBody();

        /* @var $ruleAdapter RoleScriptAdapter */
        $ruleAdapter = $this->adapter('OrchestrationRules\RoleScript');

        //Pre validates the request object
        $ruleAdapter->validateObject($object, Request::METHOD_PATCH);

        $rule = $this->getRule($ruleId, true);

        if ($rule->roleId != $roleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the role");
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
     * @param   int $roleId Numeric identifier of the role
     * @param   int $ruleId Numeric identifier of the rule
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     *
     */
    public function deleteAction($roleId, $ruleId)
    {
        $rule = $this->getRule($ruleId, true);

        if ($rule->roleId != $roleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the role");
        }

        $rule->delete();

        return $this->result(null);
    }
}