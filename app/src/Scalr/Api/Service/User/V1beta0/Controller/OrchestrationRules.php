<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Exception;
use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\RoleScriptAdapter;
use Scalr\Exception\ModelException;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\RoleScript;

/**
 * User/Version-1beta0/Images API Controller
 *
 * @author N.V.
 */
class OrchestrationRules extends ApiController
{

    private static $roleControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\Roles';

    /**
     * @var Roles
     */
    private $roleController;

    /**
     * Gets role from database using User's Environment
     *
     * @param   int $roleId The identifier of the Role
     *
     * @return  Role|null
     *
     * @throws ApiErrorException
     */
    public function getRole($roleId)
    {
        if (empty($this->roleController)) {
            $this->roleController = $this->getContainer()->api->controller(static::$roleControllerClass);
        }

        return $this->roleController->getRole($roleId);
    }

    /**
     * Retrieves the list of orchestration rules of the role
     *
     * @param   int $roleId Numeric role id
     *
     * @return array Returns describe result
     *
     * @throws ApiErrorException
     */
    public function describeAction($roleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ADMINISTRATION_ORCHESTRATION);

        //Getting the role initiates check permissions
        $this->getRole($roleId);

        return $this->adapter('roleScript')->getDescribeResult([[ 'roleId' => $roleId ]]);
    }

    /**
     * Gets specified orchestration rule
     *
     * @param   int     $ruleId Numeric identifier of the rule
     * @param   bool    $modify Modifying flag
     *
     * @return RoleScript Returns the Script Entity on success
     *
     * @throws ApiErrorException
     */
    public function getRule($ruleId, $modify = null)
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
     * @param   int $roleId   Numeric identifier of the script
     * @param   int $ruleId     Numeric identifier of the rule
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     */
    public function fetchAction($roleId, $ruleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ADMINISTRATION_ORCHESTRATION);

        $rule = $this->getRule($ruleId);

        if ($rule->roleId != $roleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the role");
        }

        return $this->result($this->adapter('roleScript')->toData($rule));
    }

    /**
     * Create a new Orchestration Rule for this Role
     *
     * @param int $roleId Numeric identifier of the role
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function createAction($roleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ADMINISTRATION_ORCHESTRATION);

        $object = $this->request->getJsonBody();

        /* @var $ruleAdapter RoleScriptAdapter */
        $ruleAdapter = $this->adapter('roleScript');

        //Pre validates the request object
        $ruleAdapter->validateObject($object, Request::METHOD_POST);

        if (!isset($object->target)) {
            $object->target->type = RoleScriptAdapter::TARGET_NULL;
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
     * @param  int  $roleId Unique identifier of the role
     * @param  int  $ruleId Numeric identifier of the rule
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws ModelException
     * @throws Exception
     */
    public function modifyAction($roleId, $ruleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ADMINISTRATION_ORCHESTRATION);

        $object = $this->request->getJsonBody();

        /* @var $ruleAdapter RoleScriptAdapter */
        $ruleAdapter = $this->adapter('roleScript');

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
     * @param int $roleId Numeric identifier of the role
     * @param int $ruleId Numeric identifier og the rule
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws ModelException
     *
     */
    public function deleteAction($roleId, $ruleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ADMINISTRATION_ORCHESTRATION);

        $rule = $this->getRule($ruleId, true);

        if ($rule->roleId != $roleId) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the role");
        }

        $rule->delete();

        return $this->result(null);
    }
}