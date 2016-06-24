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
use Scalr\Api\Service\User\V1beta0\Adapter\GlobalVariableAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\LockedException;
use Scalr\Exception\Model\Entity\Farm\FarmInUseException;
use Scalr\Exception\ModelException;
use Scalr\Exception\ValidationErrorException;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\GlobalVariable;
use Scalr\Model\Entity\FarmTeam;
use Scalr\Model\Entity\Limit;
use FarmTerminatedEvent;
use Scalr_Scripting_GlobalVariables;

/**
 * User/Farms API Controller
 *
 * @author N.V.
 */
class Farms extends ApiController
{
    use GlobalVariableTrait;

    /**
     * Retrieves the list of the farms
     *
     * @return  ListResultEnvelope   Returns describe result
     * @throws  ApiInsufficientPermissionsException
     */
    public function describeAction()
    {
        if (!$this->hasPermissions(Acl::RESOURCE_FARMS) &&
            !$this->hasPermissions(Acl::RESOURCE_TEAM_FARMS) &&
            !$this->hasPermissions(Acl::RESOURCE_OWN_FARMS)) {
            throw new ApiInsufficientPermissionsException();
        }

        return $this->adapter('farm')->getDescribeResult($this->getDefaultCriteria(), [Farm::class, 'findWithTeams']);
    }

    /**
     * Gets default search criteria according environment scope
     *
     * @return array Returns array of the search criteria
     */
    private function getDefaultCriteria()
    {
        $environment = $this->getEnvironment();
        $criteria = [['envId' => $environment->id]];

        if (!$this->hasPermissions(Acl::RESOURCE_FARMS)) {
            $where = [];
            $farm = new Farm();
            $farmTeam = new FarmTeam();
            if ($this->hasPermissions(Acl::RESOURCE_OWN_FARMS)) {
                $where[] = "{$farm->columnOwnerId()} = " . $farm->qstr('ownerId', $this->getUser()->id);
            }

            if ($this->hasPermissions(Acl::RESOURCE_TEAM_FARMS)) {
                $join[] = "
                    LEFT JOIN {$farmTeam->table('ft')} ON {$farmTeam->columnFarmId('ft')} = {$farm->columnId()}
                    LEFT JOIN `account_team_users` `atu` ON `atu`.`team_id` = {$farmTeam->columnTeamId('ft')}
                    LEFT JOIN `account_team_envs` `ate` ON `ate`.`team_id` = {$farmTeam->columnTeamId('ft')} AND `ate`.`env_id` = {$farm->columnEnvId()}
                ";
                $where[] = "`atu`.`user_id` = " . $farmTeam->db()->qstr($this->getUser()->id) . " AND `ate`.`team_id` IS NOT NULL";
            }

            if (!empty($where)) {
                $criteria[Farm::STMT_WHERE] = '(' . join(' OR ', $where) . ')';
            }

            if (!empty($join)) {
                if (empty($criteria[Farm::STMT_FROM])) {
                    $criteria[Farm::STMT_FROM] = $farm->table();
                }

                $criteria[Farm::STMT_FROM] .= implode(' ', $join);
            }
        }

        return $criteria;
    }

    /**
     * Gets specified Farm taking into account both scope and authentication token
     *
     * @param   string      $farmId          Numeric identifier of the Farm
     * @param   bool|string $modify optional Permission identifier
     *
     * @return  Farm    Returns the Farm Entity on success
     * @throws  ApiErrorException
     */
    public function getFarm($farmId, $modify = false)
    {
        /* @var $farm Farm */
        $farm = Farm::findPk($farmId);

        if (!$farm) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Farm either does not exist or is not owned by your environment.");
        }

        if (!$this->hasPermissions($farm, $modify)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

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
        $this->checkPermissions(Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_CREATE);

        $object = $this->request->getJsonBody();

        /* @var $farmAdapter FarmAdapter */
        $farmAdapter = $this->adapter('farm');

        //Pre validates the request object
        $farmAdapter->validateObject($object, Request::METHOD_POST);

        $farm = $farmAdapter->toEntity($object);

        if (!empty($farm->ownerId)) {
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

        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_UPDATE);
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
        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_DELETE);

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
        $this->getFarm($farmId);

        $globalVar = $this->getVariableInstance();

        $list = $globalVar->getValues(0, $farmId);
        $foundRows = count($list);

        /* @var  $adapter GlobalVariableAdapter */
        $adapter = $this->adapter('globalVariable');

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
        $this->getFarm($farmId);

        $globalVar = $this->getVariableInstance();

        $fetch = $this->getGlobalVariable($name, $globalVar, 0, $farmId);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        return $this->result($this->adapter('globalVariable')->convertData($fetch));
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
        $this->getFarm($farmId, Acl::PERM_FARMS_UPDATE);

        $object = $this->request->getJsonBody();

        /* @var  $adapter GlobalVariableAdapter */
        $adapter = $this->adapter('globalVariable');

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

        $checkVar = $this->getGlobalVariable($object->name, $globalVar, 0, $farmId);

        if (!empty($checkVar)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf('Variable with name %s already exists', $object->name));
        }

        try {
            $globalVar->setValues([$variable], 0, $farmId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($variable['name'], $globalVar, 0, $farmId);

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
        $this->getFarm($farmId, Acl::PERM_FARMS_UPDATE);

        $object = $this->request->getJsonBody();

        /* @var  $adapter GlobalVariableAdapter */
        $adapter = $this->adapter('globalVariable');

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $globalVar = $this->getVariableInstance();

        $variable = $this->getGlobalVariable($name, $globalVar, 0, $farmId);

        if (empty($variable)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        $entity = $this->makeGlobalVariableEntity($variable);

        $adapter->copyAlterableProperties($object, $entity, ScopeInterface::SCOPE_FARM);

        $this->updateGlobalVariable($globalVar, $variable, $object, $name, ScopeInterface::SCOPE_FARM, 0, $farmId);

        $data = $this->getGlobalVariable($name, $globalVar, 0, $farmId);

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
        $this->getFarm($farmId, Acl::PERM_FARMS_UPDATE);

        $fetch = $this->getGlobalVariable($name, $this->getVariableInstance(), 0, $farmId);

        $variable = GlobalVariable\FarmGlobalVariable::findPk($farmId, $name);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        if (empty($variable)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, "You can only delete Global Variables declared in Farm scope.");
        }

        $variable->delete();

        return $this->result(null);
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
     * Creates clone for the farm
     *
     * @param   int     $farmId Unique farm identifier
     *
     * @return  ResultEnvelope
     *
     * @throws  ApiErrorException
     */
    public function cloneAction($farmId)
    {
        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_CLONE);

        if (!$this->getUser()->getAccount()->checkLimit(Limit::ACCOUNT_FARMS, 1)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_LIMIT_EXCEEDED, "Farms limit for your account exceeded");
        }

        $object = $this->request->getJsonBody();
        if (empty($object->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property name");
        }

        $name = FarmAdapter::convertInputValue('string', $object->name, 'name');
        $criteria = $this->getScopeCriteria();
        $criteria[] = ['name' => $name];

        if (count(Farm::find($criteria))) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, "Farm with name '{$name}' already exists");
        }

        $clone = $farm->cloneFarm($name, $this->getUser());

        return $this->result($this->adapter('farm')->toData($clone));
    }

    /**
     * Terminates specified farm
     *
     * @param   int     $farmId Unique farm identifier
     *
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function terminateAction($farmId)
    {
        $farm = $this->getFarm($farmId, Acl::PERM_FARMS_LAUNCH_TERMINATE);

        $object = $this->request->getJsonBody();
        $force = isset($object->force) ? FarmAdapter::convertInputValue('boolean', $object->force) : false;

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
     * Gets global variable object
     *
     * @return Scalr_Scripting_GlobalVariables
     */
    public function getVariableInstance()
    {
        return new Scalr_Scripting_GlobalVariables(
            $this->getUser()->getAccountId(),
            $this->getEnvironment()->id,
            ScopeInterface::SCOPE_FARM
        );
    }

    /**
     * Gets list of farm's servers
     *
     * @param int $farmId       Identifier of the Farm
     * @return ListResultEnvelope
     * @throws ApiErrorException
     */
    public function describeServersAction($farmId)
    {
        $farm = $this->getFarm($farmId, true);
        /* @var $farm Farm */
        $this->checkPermissions($farm);

        return $this->adapter('server')->getDescribeResult([['farmId' => $farmId]]);
    }

}
