<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\FarmAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\FarmGlobalVariableAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\LockedException;
use Scalr\Exception\Model\Entity\Farm\FarmInUseException;
use Scalr\Exception\ModelException;
use Scalr\Exception\ValidationErrorException;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmGlobalVariable;
use Scalr\Model\Entity\Limit;
use FarmTerminatedEvent;

/**
 * User/Version-1/Farms API Controller
 *
 * @author N.V.
 */
class Farms extends ApiController
{

    /**
     * Retrieves the list of the farms
     *
     * @return  ListResultEnvelope   Returns describe result
     */
    public function describeAction()
    {
        $this->checkPermissions();

        return $this->adapter('farm')->getDescribeResult($this->getDefaultCriteria());
    }

    /**
     * Gets default search criteria according environment scope
     *
     * @return array Returns array of the search criteria
     */
    private function getDefaultCriteria()
    {
        $environment = $this->getEnvironment();

        return [['envId' => $environment->id]];
    }

    /**
     * Gets specified Farm taking into account both scope and authentication token
     *
     * @param   string  $farmId              Numeric identifier of the Farm
     * @param   string  $permission optional Permission identifier
     *
     * @return  Farm    Returns the Farm Entity on success
     * @throws  ApiErrorException
     */
    public function getFarm($farmId, $permission = null)
    {
        /* @var $farm Farm */
        $farm = Farm::findPk($farmId);

        if (!$farm) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Farm either does not exist or is not owned by your environment.");
        }

        $this->checkPermissions($farm, $permission);

        return $farm;
    }

    /**
     * Fetches detailed info about one farm
     *
     * @param   string $farmId Numeric identifier of the farm
     *
     * @return  ResultEnvelope
     */
    public function fetchAction($farmId)
    {
        return $this->result($this->adapter('farm')->toData($this->getFarm($farmId)));
    }

    /**
     * Create a new Farm in this Environment
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws ApiInsufficientPermissionsException
     */
    public function createAction()
    {
        $this->checkPermissions(null, Acl::PERM_FARMS_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var $farmAdapter FarmAdapter */
        $farmAdapter = $this->adapter('farm');

        //Pre validates the request object
        $farmAdapter->validateObject($object, Request::METHOD_POST);

        $farm = $farmAdapter->toEntity($object);

        if (!empty($farm->createdById)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Farm owner should not be set on farm creation");
        }

        $farm->id = null;
        $farm->envId = $this->getEnvironment()->id;

        $user = $this->getUser();

        $farm->accountId = $user->getAccountId();
        $farm->createdByEmail = $user->getEmail();
        $farm->changedById = $user->getId();

        $farmAdapter->validateEntity($farm);

        if (!$this->getUser()->getAccount()->checkLimit(Limit::ACCOUNT_FARMS, 1)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_LIMIT_EXCEEDED, "Farms limit for your account exceeded");
        }

        //Saves entity
        $farm->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($farmAdapter->toData($farm));
    }

    /**
     * Change farm attributes.
     *
     * @param   int $farmId Unique identifier of the script
     *
     * @return  ResultEnvelope
     */
    public function modifyAction($farmId)
    {
        $object = $this->request->getJsonBody();
        /* @var $farmAdapter FarmAdapter */
        $farmAdapter = $this->adapter('farm');
        //Pre validates the request object
        $farmAdapter->validateObject($object, Request::METHOD_PATCH);

        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_MANAGE);
        //Copies all alterable properties to fetched Role Entity
        $farmAdapter->copyAlterableProperties($object, $farm);

        $farm->changedById = $this->getUser()->getId();
        //Re-validates an Entity
        $farmAdapter->validateEntity($farm);
        //Saves verified results
        $farm->save();

        return $this->result($farmAdapter->toData($farm));
    }

    /**
     * Delete an Farm from this Environment
     *
     * @param   string $farmId Unique identifier of the script
     *
     * @return ResultEnvelope
     *
     * @throws ApiErrorException
     * @throws ApiInsufficientPermissionsException
     */
    public function deleteAction($farmId)
    {
        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_MANAGE);

        try {
            $farm->delete();
        } catch (FarmInUseException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_OBJECT_IN_USE, $e->getMessage());
        }

        return $this->result(null);
    }

    /**
     * Gets the list of the available Global Variables of the farm
     *
     * @param   int $farmId Numeric identifier of the Farm
     *
     * @return  ListResultEnvelope
     */
    public function describeVariablesAction($farmId)
    {
        parent::checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT);

        $this->getFarm($farmId);

        $globalVar = $this->getVariableInstance();

        $list = $globalVar->getValues(0, $farmId);
        $foundRows = count($list);

        /* @var  $adapter FarmGlobalVariableAdapter */
        $adapter = $this->adapter('farmGlobalVariable');

        $data = [];

        $list = array_slice($list, $this->getPageOffset(), $this->getMaxResults());

        foreach ($list as $var) {
            $item = $adapter->convertData($var);
            $data[] = $item;
        }

        return $this->resultList($data, $foundRows);
    }

    /**
     * Gets specific global var of the farm
     *
     * @param   int     $farmId Numeric identifier of the Farm
     * @param   string  $name   Name of variable
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function fetchVariableAction($farmId, $name)
    {
        parent::checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT);

        $this->getFarm($farmId);

        $globalVar = $this->getVariableInstance();

        $fetch = $this->getGlobalVariable($farmId, $name, $globalVar);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        return $this->result($this->adapter('farmGlobalVariable')->convertData($fetch));
    }

    /**
     * Creates farm's global var
     *
     * @param   int $farmId Numeric identifier of the Farm
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function createVariableAction($farmId)
    {
        parent::checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT, Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE);

        $this->getFarm($farmId, Acl::PERM_FARMS_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var  $adapter FarmGlobalVariableAdapter */
        $adapter = $this->adapter('farmGlobalVariable');

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
                'scope'         => ScopeInterface::SCOPE_FARM,
            ],
            'flagDelete' => '',
            'scopes'     => [ScopeInterface::SCOPE_FARM]
        ];

        $checkVar = $this->getGlobalVariable($farmId, $object->name, $globalVar);

        if (!empty($checkVar)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf('Variable with name %s already exists', $object->name));
        }

        try {
            $globalVar->setValues([$variable], 0, $farmId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($farmId, $variable['name'], $globalVar);

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($adapter->convertData($data));
    }

    /**
     * Modifies farm's global variable
     *
     * @param   int     $farmId Numeric identifier of the Farm
     * @param   string  $name   Name of variable
     *
     * @return  ResultEnvelope
     * @throws  ApiErrorException
     */
    public function modifyVariableAction($farmId, $name)
    {
        parent::checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT, Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE);

        $this->getFarm($farmId, Acl::PERM_FARMS_MANAGE);

        $object = $this->request->getJsonBody();

        /* @var  $adapter FarmGlobalVariableAdapter */
        $adapter = $this->adapter('farmGlobalVariable');

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $globalVar = $this->getVariableInstance();

        $entity = new FarmGlobalVariable();

        $adapter->copyAlterableProperties($object, $entity);

        $variable = $this->getGlobalVariable($farmId, $name, $globalVar);

        if (empty($variable)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        if (!empty($variable['locked']) && (!isset($object->value) || count(get_object_vars($object)) > 1)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf("This variable was declared in the %s Scope, you can only modify its 'value' field in the Farm Scope", ucfirst($variable['locked']['scope'])));
        }

        $variable['flagDelete'] = '';

        if (!empty($variable['locked'])) {
            $variable['current']['name'] = $name;
            $variable['current']['value'] = $object->value;
            $variable['current']['scope'] = ScopeInterface::SCOPE_FARM;
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
                'scope'         => ScopeInterface::SCOPE_FARM,
            ];
        }

        try {
            $globalVar->setValues([$variable], 0, $farmId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($farmId, $name, $globalVar);

        return $this->result($adapter->convertData($data));
    }

    /**
     * Deletes farm's global variable
     *
     * @param   int     $farmId Numeric identifier of the Farm
     * @param   string  $name   Name of variable
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     * @throws  ModelException
     */
    public function deleteVariableAction($farmId, $name)
    {
        parent::checkPermissions(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT, Acl::PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE);

        $this->getFarm($farmId, Acl::PERM_FARMS_MANAGE);

        $fetch = $this->getGlobalVariable($farmId, $name, $this->getVariableInstance());

        $variable = FarmGlobalVariable::findPk($farmId, $name);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        } else if (empty($variable)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, "You can only delete Global Variables declared in Farm scope.");
        }

        $variable->delete();

        return $this->result(null);
    }

    /**
     * Gets a specific global variable data
     *
     * @param   int                                 $farmId     Numeric identifier of the Farm
     * @param   string                              $name       Variable name
     * @param   \Scalr_Scripting_GlobalVariables    $globalVar  Instance of Global variable handler
     *
     * @return  mixed
     * @throws  ApiErrorException
     */
    private function getGlobalVariable($farmId, $name, \Scalr_Scripting_GlobalVariables $globalVar)
    {
        $list = $globalVar->getValues(0, $farmId);
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
            ScopeInterface::SCOPE_FARM
        );
    }

    /**
     * Launch specified farm
     *
     * @param   int $farmId Unique farm identifier
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function launchAction($farmId)
    {
        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_LAUNCH_TERMINATE);

        try {
            $farm->launch($this->getUser());
        } catch (LockedException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_LOCKED, $e->getMessage() . ", please unlock it first", $e->getCode(), $e);
        }

        $farmAdapter = $this->adapter('farm');

        return $this->result($farmAdapter->toData($farm));
    }

    /**
     * Terminates specified farm
     *
     * @param   int     $farmId Unique farm identifier
     * @param   bool    $force  If true skip all shutdown routines (do not process the BeforeHostTerminate event)
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function terminateAction($farmId, $force = false)
    {
        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_LAUNCH_TERMINATE);

        try {
            $farm->checkLocked();

            \Scalr::FireEvent($farm->id, new FarmTerminatedEvent(
                false,
                false,
                false,
                false,
                $force,
                $this->getUser()->id
            ));
        } catch (LockedException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_LOCKED, $e->getMessage() . ", please unlock it first", $e->getCode(), $e);
        }

        $farmAdapter = $this->adapter('farm');

        return $this->result($farmAdapter->toData($farm));
    }

    /**
     * Throws an exception if user does not have sufficient permission
     *
     * @param   Farm   $farm       optional The Farm
     * @param   string $permission optional Permission identifier
     * @param   string $message    optional Api error message
     * @throws  ApiInsufficientPermissionsException
     */
    public function checkPermissions(...$args)
    {
        @list($farm, $permission, $message) = $args;
        $acl = \Scalr::getContainer()->acl;
        $user = $this->getUser();
        $environment = $this->getEnvironment();

        if ($farm === null) {
            $result = $acl->isUserAllowedByEnvironment($user, $environment, Acl::RESOURCE_FARMS, $permission) ||
                      $acl->isUserAllowedByEnvironment($user, $environment, Acl::RESOURCE_TEAM_FARMS, $permission) ||
                      $acl->isUserAllowedByEnvironment($user, $environment, Acl::RESOURCE_OWN_FARMS, $permission);
        } else {
            $result = $acl->isUserAllowedByEnvironment($user, $environment, Acl::RESOURCE_FARMS, $permission) ||
                      ($farm->teamId      && $user->inTeam($farm->teamId)  && $acl->isUserAllowedByEnvironment($user, $environment, Acl::RESOURCE_TEAM_FARMS, $permission)) ||
                      ($farm->createdById && $user->id == $farm->createdById && $acl->isUserAllowedByEnvironment($user, $environment, Acl::RESOURCE_OWN_FARMS, $permission));
        }

        if (!$result) {
            throw new ApiInsufficientPermissionsException($message);
        }
    }
}