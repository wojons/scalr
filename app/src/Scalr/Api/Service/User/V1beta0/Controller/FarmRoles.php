<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\FarmRoleAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\FarmRoleGlobalVariableAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\InvalidEntityConfigurationException;
use Scalr\Exception\ModelException;
use Scalr\Exception\ValidationErrorException;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmRoleGlobalVariable;
use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Model\Entity\Role;
use Scalr_Role_Behavior_Router;

/**
 * User/Version-1/FarmRoles API Controller
 *
 * @author N.V.
 */
class FarmRoles extends ApiController
{

    const AWS_INSTANCE_CONFIGURATION = 'AwsInstanceConfiguration';

    const AWS_VPC_PLACEMENT_CONFIGURATION = 'AwsVpcPlacementConfiguration';
    const AWS_CLASSIC_PLACEMENT_CONFIGURATION = 'AwsClassicPlacementConfiguration';
    const OPEN_STACK_PLACEMENT_CONFIGURATION = 'OpenStackPlacementConfiguration';
    const CLOUD_STACK_PLACEMENT_CONFIGURATION = 'CloudStackPlacementConfiguration';
    const GCE_PLACEMENT_CONFIGURATION = 'GcePlacementConfiguration';

    private static $roleControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\Roles';
    private static $farmControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\Farms';

    /**
     * @var Roles
     */
    private $roleController;

    /**
     * @var Farms
     */
    private $farmController;

    /**
     * Gets role from database using User's Environment
     *
     * @param   int    $roleId   The identifier of the Role
     * @return  Role|null Returns role from database using User's Environment
     * @throws  ApiErrorException
     */
    public function getRole($roleId)
    {
        if (empty($this->roleController)) {
            $this->roleController = $this->getContainer()->api->controller(static::$roleControllerClass);
        }

        return $this->roleController->getRole($roleId);
    }

    /**
     * Gets farm from database using User's Environment
     *
     * @param   int     $farmId          The identifier of the Role
     * @param   bool    $modify optional Modification flag
     * @return  Farm|null Returns specified Farm
     * @throws  ApiErrorException
     */
    public function getFarm($farmId, $modify = false)
    {
        if (empty($this->farmController)) {
            $this->farmController = $this->getContainer()->api->controller(static::$farmControllerClass);
        }

        return $this->farmController->getFarm($farmId, $modify ? Acl::PERM_FARMS_MANAGE : null);
    }

    /**
     * Gets specified Farm Role taking into account both scope and authentication token
     *
     * @param   string  $farmRoleId          Numeric identifier of the Farm role
     * @param   int     $farmId     optional Identifier of the Farm containing Farm role
     * @param   bool    $modify     optional Flag checking write permissions
     *
     * @return  FarmRole    Returns the Script Entity on success
     * @throws  ApiErrorException
     */
    public function getFarmRole($farmRoleId, $farmId = null, $modify = false)
    {
        /* @var $role FarmRole */
        $role = FarmRole::findPk($farmRoleId);

        if (!$role) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Farm either does not exist or is not owned by your environment.");
        }

        if (isset($farmId)) {
            if ($role->farmId != $farmId) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the farm");
            }
        } else {
            $farmId = $role->farmId;
        }

        $this->getFarm($farmId, $modify);

        if (!$this->hasPermissions($role, $modify)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $role;
    }

    /**
     * Retrieves the list of the farm roles
     *
     * @param   int $farmId         Identifier of the Farm containing Farm role
     *
     * @return  ListResultEnvelope  Returns describe result
     */
    public function describeAction($farmId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        return $this->adapter('farmRole')->getDescribeResult([[ 'farmId' => $this->getFarm($farmId)->id ]]);
    }

    /**
     * Fetches detailed info about one farm role
     *
     * @param   string  $farmRoleId Numeric identifier of the farm role
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function fetchAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        return $this->result($this->adapter('farmRole')->toData($this->getFarmRole($farmRoleId)));
    }

    /**
     * Create a new Farm role in this Environment
     *
     * @param   int     $farmId     Identifier of the Farm, for which Farm role creates
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function createAction($farmId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $farmRoleAdapter FarmRoleAdapter */
        $farmRoleAdapter = $this->adapter('farmRole');

        $this->getFarm($farmId, true);

        //Pre validates the request object
        $farmRoleAdapter->validateObject($object, Request::METHOD_POST);

        $role = $farmRoleAdapter->toEntity($object);

        $role->id = null;
        $role->farmId = $farmId;

        if (empty($object->scaling)) {
            $role->settings[FarmRoleSetting::SCALING_ENABLED] = true;
            $role->settings[FarmRoleSetting::SCALING_MIN_INSTANCES] = 1;
            $role->settings[FarmRoleSetting::SCALING_MAX_INSTANCES] = 2;
        } else if (empty($object->scaling->enabled) && !(empty($object->scaling->minInstances) || empty($object->scaling->maxInstances))) {
            $role->settings[FarmRoleSetting::SCALING_ENABLED] = true;
        }

        $farmRoleAdapter->validateEntity($role);

        //Saves entity
        try {
            $role->save();
        } catch (InvalidEntityConfigurationException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage(), $e->getCode(), $e);
        }

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($farmRoleAdapter->toData($role));
    }

    /**
     * Change farm role attributes.
     *
     * @param   int     $farmRoleId Unique identifier of the farm role
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function modifyAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $farmRole FarmRoleAdapter */
        $farmRole = $this->adapter('farmRole');

        //Pre validates the request object
        $farmRole->validateObject($object, Request::METHOD_PATCH);

        $role = $this->getFarmRole($farmRoleId, null, true);

        //Copies all alterable properties to fetched Role Entity
        $farmRole->copyAlterableProperties($object, $role);

        //Re-validates an Entity
        $farmRole->validateEntity($role);

        //Saves verified results
        try {
            $role->save();
        } catch (InvalidEntityConfigurationException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage(), $e->getCode(), $e);
        }


        return $this->result($farmRole->toData($role));
    }

    /**
     * Delete an Farm role
     *
     * @param   string  $farmRoleId Unique identifier of the script
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function deleteAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);

        $role = $this->getFarmRole($farmRoleId, null, true);

        $role->delete();

        return $this->result(null);
    }

    /**
     * Describes placement configuration
     *
     * @param   int $farmRoleId Unique farm-role identifier
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function describePlacementAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        $role = $this->getFarmRole($farmRoleId);

        return $this->result(FarmRoleAdapter::getPlacementConfiguration($role));
    }

    /**
     * Change placement configuration
     *
     * @param   int $farmRoleId Unique farm-role identifier
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function modifyPlacementAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        $role = $this->getFarmRole($farmRoleId, null, true);

        FarmRoleAdapter::setupPlacementConfiguration($role, $this->request->getJsonBody());

        /* @var $farmRoleAdapter FarmRoleAdapter */
        $farmRoleAdapter = $this->adapter('farmRole');

        $farmRoleAdapter->validateEntity($role);

        $role->save();

        return $this->result(FarmRoleAdapter::getPlacementConfiguration($role));
    }

    /**
     * Describes instance configuration
     *
     * @param   int $farmRoleId Unique farm-role identifier
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function describeInstanceAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        $role = $this->getFarmRole($farmRoleId);

        return $this->result(FarmRoleAdapter::getInstanceConfiguration($role));
    }

    /**
     * Change instance configuration
     *
     * @param   int $farmRoleId Farm role unique identifier
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function modifyInstanceAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);

        $role = $this->getFarmRole($farmRoleId, null, true);

        FarmRoleAdapter::setupInstanceConfiguration($role, $this->request->getJsonBody());

        /* @var $farmRoleAdapter FarmRoleAdapter */
        $farmRoleAdapter = $this->adapter('farmRole');

        $farmRoleAdapter->validateEntity($role);

        $role->save();

        return $this->result(FarmRoleAdapter::getInstanceConfiguration($role));
    }

    /**
     * Describes placement configuration
     *
     * @param   int $farmRoleId Unique farm-role identifier
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function describeScalingAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT);

        $role = $this->getFarmRole($farmRoleId);

        return $this->result(FarmRoleAdapter::getScalingConfiguration($role));
    }

    /**
     * Change scaling configuration of farm-role
     *
     * @param   int $farmRoleId Unique farm-role identifier
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function modifyScalingAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);

        $role = $this->getFarmRole($farmRoleId, null, true);

        FarmRoleAdapter::setupScalingConfiguration($role, $this->request->getJsonBody());

        /* @var $farmRoleAdapter FarmRoleAdapter */
        $farmRoleAdapter = $this->adapter('farmRole');

        $farmRoleAdapter->validateEntity($role);

        $role->save();

        return $this->result(FarmRoleAdapter::getScalingConfiguration($role));
    }

    /**
     * List Global Variables associated with this Farm role
     *
     * @param   int $farmRoleId Unique farm-role identifier
     *
     * @return  ListResultEnvelope
     */
    public function describeVariablesAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT);

        $farmRole = $this->getFarmRole($farmRoleId);

        $globalVar = $this->getVariableInstance();

        $list = $globalVar->getValues($farmRole->roleId, $farmRole->farmId, $farmRoleId);
        $foundRows = count($list);

        /* @var  $adapter FarmRoleGlobalVariableAdapter */
        $adapter = $this->adapter('farmRoleGlobalVariable');

        $data = [];

        $list = array_slice($list, $this->getPageOffset(), $this->getMaxResults());

        foreach ($list as $var) {
            $item = $adapter->convertData($var);
            $data[] = $item;
        }

        return $this->resultList($data, $foundRows);
    }

    /**
     * Gets specific global var of the farm role
     *
     * @param   int     $farmRoleId Numeric identifier of the Farm Role
     * @param   string  $name       Name of variable
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function fetchVariableAction($farmRoleId, $name)
    {
        $this->checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT);

        $farmRole = $this->getFarmRole($farmRoleId);

        $globalVar = $this->getVariableInstance();

        $fetch = $this->getGlobalVariable($farmRole->roleId, $farmRole->farmId, $farmRoleId, $name, $globalVar);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        return $this->result($this->adapter('farmRoleGlobalVariable')->convertData($fetch));
    }

    /**
     * Creates farm role's global var
     *
     * @param   int $farmRoleId Numeric identifier of the Farm Role
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function createVariableAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT, Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE);

        $farmRole = $this->getFarmRole($farmRoleId, null, true);

        $object = $this->request->getJsonBody();

        /* @var  $adapter FarmRoleGlobalVariableAdapter */
        $adapter = $this->adapter('farmRoleGlobalVariable');

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $globalVar = $this->getVariableInstance();

        $variable = [
            'name'      => $object->name,
            'default'   => '',
            'locked'    => '',
            'current'   => [
                'name'          => $object->name,
                'value'         => !empty($object->value) ? $object->value : '',
                'category'      => !empty($object->category) ? strtolower($object->category) : '',
                'flagFinal'     => !empty($object->locked) ? 1 : 0,
                'flagRequired'  => !empty($object->requiredIn) ? $object->requiredIn : '',
                'flagHidden'    => !empty($object->hidden) ? 1 : 0,
                'format'        => !empty($object->outputFormat) ? $object->outputFormat : '',
                'validator'     => !empty($object->validationPattern) ? $object->validationPattern : '',
                'description'   => !empty($object->description) ? $object->description : '',
                'scope'         => ScopeInterface::SCOPE_FARMROLE,
            ],
            'flagDelete' => '',
            'scopes'     => [ScopeInterface::SCOPE_FARMROLE]
        ];

        $checkVar = $this->getGlobalVariable($farmRole->roleId, $farmRole->farmId, $farmRoleId, $object->name, $globalVar);

        if (!empty($checkVar)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf('Variable with name %s already exists', $object->name));
        }

        try {
            $globalVar->setValues([$variable], $farmRole->roleId, $farmRole->farmId, $farmRoleId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($farmRole->roleId, $farmRole->farmId, $farmRoleId, $variable['name'], $globalVar);

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($adapter->convertData($data));
    }

    /**
     * Modifies farm role's global variable
     *
     * @param   int     $farmRoleId Numeric identifier of the Farm Role
     * @param   string  $name       Name of variable
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function modifyVariableAction($farmRoleId, $name)
    {
        $this->checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT, Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE);

        $farmRole = $this->getFarmRole($farmRoleId, null, true);

        $object = $this->request->getJsonBody();

        /* @var  $adapter FarmRoleGlobalVariableAdapter */
        $adapter = $this->adapter('farmRoleGlobalVariable');

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $globalVar = $this->getVariableInstance();

        $entity = new FarmRoleGlobalVariable();

        $adapter->copyAlterableProperties($object, $entity);

        $variable = $this->getGlobalVariable($farmRole->roleId, $farmRole->farmId, $farmRoleId, $name, $globalVar);

        if (empty($variable)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        if (!empty($variable['locked']) && (!isset($object->value) || count(get_object_vars($object)) > 1)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf("This variable was declared in the %s Scope, you can only modify its 'value' field in the Farm Role Scope", ucfirst($variable['locked']['scope'])));
        }

        $variable['flagDelete'] = '';

        if (!empty($variable['locked'])) {
            $variable['current']['name'] = $name;
            $variable['current']['value'] = $object->value;
            $variable['current']['scope'] = ScopeInterface::SCOPE_FARMROLE;
        } else {
            $variable['current'] = [
                'name'          => $name,
                'value'         => !empty($object->value) ? $object->value : '',
                'category'      => !empty($object->category) ? strtolower($object->category) : '',
                'flagFinal'     => !empty($object->locked) ? 1 : $variable['current']['flagFinal'],
                'flagRequired'  => !empty($object->requiredIn) ? $object->requiredIn : $variable['current']['flagRequired'],
                'flagHidden'    => !empty($object->hidden) ? 1 : $variable['current']['flagHidden'],
                'format'        => !empty($object->outputFormat) ? $object->outputFormat : $variable['current']['format'],
                'validator'     => !empty($object->validationPattern) ? $object->validationPattern : $variable['current']['validator'],
                'description'   => !empty($object->description) ? $object->description : '',
                'scope'         => ScopeInterface::SCOPE_FARMROLE,
            ];
        }

        try {
            $globalVar->setValues([$variable], $farmRole->roleId, $farmRole->farmId, $farmRoleId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($farmRole->roleId, $farmRole->farmId, $farmRoleId, $name, $globalVar);

        return $this->result($adapter->convertData($data));
    }

    /**
     * Deletes farm role's global variable
     *
     * @param   int     $farmRoleId Numeric identifier of the Farm Role
     * @param   string  $name       Name of variable
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function deleteVariableAction($farmRoleId, $name)
    {
        $this->checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT, Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE);

        $farmRole = $this->getFarmRole($farmRoleId, null, true);

        $fetch = $this->getGlobalVariable($farmRole->roleId, $farmRole->farmId, $farmRoleId, $name, $this->getVariableInstance());

        $variable = FarmRoleGlobalVariable::findPk($farmRoleId, $name);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        } else if (empty($variable)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, "You can only delete Global Variables declared in Farm Role scope.");
        }

        $variable->delete();

        return $this->result(null);
    }

    /**
     * Gets a specific global variable data
     *
     * @param   int                                 $roleId         Numeric identifier of the Role
     * @param   int                                 $farmId         Numeric identifier of the Farm
     * @param   int                                 $farmRoleId     Numeric identifier of the Farm Role
     * @param   string                              $name           Variable name
     * @param   \Scalr_Scripting_GlobalVariables    $globalVar      Instance of Global variable handler
     *
     * @return  mixed
     * @throws  ApiErrorException
     */
    private function getGlobalVariable($roleId, $farmId, $farmRoleId, $name, \Scalr_Scripting_GlobalVariables $globalVar)
    {
        $list = $globalVar->getValues($roleId, $farmId, $farmRoleId);
        $fetch = [];

        foreach ($list as $var) {
            if ((!empty($var['current']['name']) && $var['current']['name'] == $name)
                || (!empty($var['default']['name']) && $var['default']['name'] == $name)) {

                $fetch = $var;
                break;
            }
        }

        return $fetch;
    }

    /**
     * Gets global variable object
     *
     * @return  \Scalr_Scripting_GlobalVariables
     */
    private function getVariableInstance()
    {
        return new \Scalr_Scripting_GlobalVariables(
            $this->getUser()->getAccountId(),
            $this->getEnvironment()->id,
            ScopeInterface::SCOPE_FARMROLE
        );
    }

}