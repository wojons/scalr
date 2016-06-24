<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\DataType\ResultEnvelope;
use \Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\FarmRoleAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\GlobalVariableAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\ScalingMetricAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\ScalingRuleAdapter;
use Scalr\Api\Service\User\V1beta0\Adapter\ServerAdapter;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\InvalidEntityConfigurationException;
use Scalr\Exception\ModelException;
use Scalr\Exception\NotSupportedException;
use Scalr\Exception\ServerImportException;
use Scalr\Exception\ValidationErrorException;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\GlobalVariable;
use Scalr\Model\Entity\FarmRoleScalingMetric;
use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\Server;
use Exception;
use Scalr_Scripting_GlobalVariables;

/**
 * User/FarmRoles API Controller
 *
 * @author N.V.
 */
class FarmRoles extends ApiController
{
    use GlobalVariableTrait;

    const AWS_INSTANCE_CONFIGURATION = 'AwsInstanceConfiguration';

    const AWS_VPC_PLACEMENT_CONFIGURATION = 'AwsVpcPlacementConfiguration';
    const AWS_CLASSIC_PLACEMENT_CONFIGURATION = 'AwsClassicPlacementConfiguration';
    const OPEN_STACK_PLACEMENT_CONFIGURATION = 'OpenStackPlacementConfiguration';
    const CLOUD_STACK_PLACEMENT_CONFIGURATION = 'CloudStackPlacementConfiguration';
    const GCE_PLACEMENT_CONFIGURATION = 'GcePlacementConfiguration';

    private static $roleControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\Roles';
    private static $farmControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\Farms';

    /**
     * Namespace for scaling rule adapters
     *
     * @var string
     */
    protected static $scalingRuleNamespace = 'ScalingRule';

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

        return $this->farmController->getFarm($farmId, $modify);
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
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Farm Role either does not exist or is not owned by your environment.");
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
        $farmRole = $this->getFarmRole($farmRoleId, null, true);

        $farmRole->delete();

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
        $role = $this->getFarmRole($farmRoleId, null, true);

        FarmRoleAdapter::setupScalingConfiguration($role, $this->request->getJsonBody());

        $role->save();

        return $this->result(FarmRoleAdapter::getScalingConfiguration($role));
    }

    /**
     * Add new scaling metric configuration for farm-role
     *
     * @param int $farmRoleId  Unique farm-role identifier
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function createScalingRuleAction($farmRoleId)
    {
        $farmRole = $this->getFarmRole($farmRoleId, null, true);

        $object = $this->request->getJsonBody();
        if (!is_object($object)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Invalid body");
        }

        /* @var $scalingRuleAdapter ScalingRuleAdapter */
        $scalingRuleAdapter = $this->adapter($object);

        //Pre validates the request object
        $scalingRuleAdapter->validateObject($object, Request::METHOD_POST);

        /* @var $scalingRule FarmRoleScalingMetric */
        $scalingRule = $scalingRuleAdapter->toEntity($object);
        $scalingRule->farmRoleId = $farmRoleId;
        $scalingRuleAdapter->validateEntity($scalingRule);
        $scalingRule->save();

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result(FarmRoleAdapter::getScalingConfiguration($farmRole));
    }

    /**
     * Gets specified farm role scaling metric entity
     *
     * @param int    $farmRoleId      Unique farm-role identifier
     * @param string $scalingRuleName Scaling metric's name.
     * @param bool   $modify          optional Flag checking write permissions
     * @return FarmRoleScalingMetric
     * @throws ApiErrorException
     */
    public function getScalingRule($farmRoleId, $scalingRuleName, $modify = false)
    {
        $farmRole = $this->getFarmRole($farmRoleId, null, $modify);

        $scalingRuleName = ScalingMetricAdapter::metricNameToEntity($scalingRuleName);

        if (empty($farmRole->farmRoleMetrics[$scalingRuleName])) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, 'Requested Scaling Metric does not exist.');
        }

        return $farmRole->farmRoleMetrics[$scalingRuleName];
    }

    /**
     * Gets specific scaling metric of the farm role
     *
     * @param int    $farmRoleId      Unique farm-role identifier
     * @param string $scalingRuleName Scaling metric's name.
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function fetchScalingRuleAction($farmRoleId, $scalingRuleName)
    {
        $scalingRule = $this->getScalingRule($farmRoleId, $scalingRuleName);

        return $this->result($this->adapter($scalingRule)->toData($scalingRule));
    }

    /**
     * Change farm role scaling metric attributes.
     *
     * @param int    $farmRoleId      Unique farm-role identifier
     * @param string $scalingRuleName Scaling metric's name.
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function modifyScalingRuleAction($farmRoleId, $scalingRuleName)
    {
        $object = $this->request->getJsonBody();

        $scalingRule = $this->getScalingRule($farmRoleId, $scalingRuleName, true);

        /* @var $scalingRuleAdapter ScalingRuleAdapter */
        $scalingRuleAdapter = $this->adapter($scalingRule);

        //Pre validates the request object
        $scalingRuleAdapter->validateObject($object, Request::METHOD_PATCH);

        //Copies all alterable properties to fetched Role Entity
        $scalingRuleAdapter->copyAlterableProperties($object, $scalingRule);

        //Re-validates an Entity
        $scalingRuleAdapter->validateEntity($scalingRule);

        //Saves verified results
        $scalingRule->save();

        return $this->result($scalingRuleAdapter->toData($scalingRule));
    }

    /**
     * Delete farm role scaling metric
     *
     * @param int    $farmRoleId      Unique farm-role identifier
     * @param string $scalingRuleName Scaling metric's name.
     * @return ResultEnvelope
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function deleteScalingRuleAction($farmRoleId, $scalingRuleName)
    {
        $scalingRule = $this->getScalingRule($farmRoleId, $scalingRuleName, true);

        $scalingRule->delete();

        return $this->result(null);
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
        $farmRole = $this->getFarmRole($farmRoleId);

        $globalVar = $this->getVariableInstance();

        $list = $globalVar->getValues($farmRole->roleId, $farmRole->farmId, $farmRoleId);
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
        $farmRole = $this->getFarmRole($farmRoleId);

        $globalVar = $this->getVariableInstance();

        $fetch = $this->getGlobalVariable($name, $globalVar, $farmRole->roleId, $farmRole->farmId, $farmRoleId);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        return $this->result($this->adapter('globalVariable')->convertData($fetch));
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
        $farmRole = $this->getFarmRole($farmRoleId, null, true);

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
                'scope'         => ScopeInterface::SCOPE_FARMROLE,
            ],
            'flagDelete' => '',
            'scopes'     => [ScopeInterface::SCOPE_FARMROLE]
        ];

        $checkVar = $this->getGlobalVariable($object->name, $globalVar, $farmRole->roleId, $farmRole->farmId, $farmRoleId);

        if (!empty($checkVar)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf('Variable with name %s already exists', $object->name));
        }

        try {
            $globalVar->setValues([$variable], $farmRole->roleId, $farmRole->farmId, $farmRoleId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }

        $data = $this->getGlobalVariable($variable['name'], $globalVar, $farmRole->roleId, $farmRole->farmId, $farmRoleId);

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
        $farmRole = $this->getFarmRole($farmRoleId, null, true);

        $object = $this->request->getJsonBody();

        /* @var  $adapter GlobalVariableAdapter */
        $adapter = $this->adapter('globalVariable');

        //Pre validates the request object
        $adapter->validateObject($object, Request::METHOD_POST);

        $globalVar = $this->getVariableInstance();

        $variable = $this->getGlobalVariable($name, $globalVar, $farmRole->roleId, $farmRole->farmId, $farmRoleId);

        if (empty($variable)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        $entity = $this->makeGlobalVariableEntity($variable);

        $adapter->copyAlterableProperties($object, $entity, ScopeInterface::SCOPE_FARMROLE);

        $this->updateGlobalVariable(
            $globalVar,
            $variable,
            $object,
            $name,
            ScopeInterface::SCOPE_FARMROLE,
            $farmRole->roleId,
            $farmRole->farmId,
            $farmRoleId
        );

        $data = $this->getGlobalVariable($name, $globalVar, $farmRole->roleId, $farmRole->farmId, $farmRoleId);

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
        $farmRole = $this->getFarmRole($farmRoleId, null, true);

        $fetch = $this->getGlobalVariable(
            $name,
            $this->getVariableInstance(),
            $farmRole->roleId,
            $farmRole->farmId,
            $farmRoleId
        );

        $variable = GlobalVariable\FarmRoleGlobalVariable::findPk($farmRoleId, $name);

        if (empty($fetch)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Global Variable does not exist.");
        }

        if (empty($variable)) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, "You can only delete Global Variables declared in Farm Role scope.");
        }

        $variable->delete();

        return $this->result(null);
    }

    /**
     * Gets list of farm role's servers
     *
     * @param int $farmRoleId       Identifier of the Farm Role
     * @return ListResultEnvelope
     * @throws ApiErrorException
     */
    public function describeServersAction($farmRoleId)
    {
        $farmRole = $this->getFarmRole($farmRoleId, null, true);
        /* @var $farmRole FarmRole */
        $this->farmController->checkPermissions($farmRole->getFarm());

        return $this->adapter('server')->getDescribeResult([['farmRoleId' => $farmRoleId]]);
    }

    /**
     * Import non-scalarizr server to the Farm Role
     *
     * @param int $farmRoleId
     * @return ResultEnvelope
     * @throws ApiErrorException
     */
    public function importServerAction($farmRoleId)
    {
        $this->checkPermissions(Acl::RESOURCE_DISCOVERY_SERVERS, Acl::PERM_DISCOVERY_SERVERS_IMPORT);

        /* @var  $farmRole FarmRole */
        $farmRole = $this->getFarmRole($farmRoleId, null, true);
        $this->farmController->checkPermissions($farmRole->getFarm(), ACL::PERM_FARMS_SERVERS);

        if (!$this->getEnvironment()->keychain($farmRole->platform)->isEnabled()) {
            throw new ApiErrorException(409, ErrorMessage::ERR_NOT_ENABLED_PLATFORM,
                sprintf("Platform '%s' is not enabled", $farmRole->platform)
            );
        }

        $object = $this->request->getJsonBody();

        if (empty($object->cloudServerId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property cloudServerId");
        }

        $cloudServerId = ServerAdapter::convertInputValue('string', $object->cloudServerId, 'cloudServerId');

        //TODO the loader of the list of the Tags should be moved into separate class/method
        $serverTags = [];
        if (!empty($object->tags)) {
            if (!is_array($object->tags)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Property tags must be array");
            }
            foreach ($object->tags as $tag) {
                if (!isset($tag->key)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property tag.key");
                }
                $serverTags[ServerAdapter::convertInputValue('string', $tag->key, 'tag.key')] =
                    isset($tag->value) ? ServerAdapter::convertInputValue('string', $tag->value, 'tag.value') : null;
            }
        }

        try {
            /* @var $server Server */
            $server = $farmRole->getServerImport($this->getUser())->import($cloudServerId, $serverTags);
        } catch (NotSupportedException $e) {
            throw new ApiNotImplementedErrorException(sprintf("Platform '%s' is not supported yet", $farmRole->platform));
        } catch (ValidationErrorException $e) {
            if (strpos($e->getMessage(), 'Instance was not found') !== false) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, $e->getMessage(), $e->getCode(), $e);
            } else {
                throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, $e->getMessage(), $e->getCode(), $e);
            }
        } catch (ServerImportException $e) {
            throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, $e->getMessage(), $e->getCode(), $e);
        } catch (Exception $e) {
            throw new ApiErrorException(503, ErrorMessage::ERR_SERVICE_UNAVAILABLE, $e->getMessage(), $e->getCode(), $e);
        }

        $this->response->setStatus(201);

        return $this->result($this->adapter('server')->toData($server));
    }

    /**
     * Gets a new Instance of the adapter
     *
     * @param   string|FarmRoleScalingMetric|object $name                The name of the adapter or FarmRoleScalingMetric entity or farm role scaling metric data
     * @param   string                              $scope      optional The scope of the adapter
     * @param   string                              $version    optional The version of the adapter
     *
     * @return ApiEntityAdapter
     *
     * @throws ApiErrorException
     */
    public function adapter($name, $scope = null, $version = null)
    {
        if (is_object($name)) {
            $object = $name;
            if ($object instanceof FarmRoleScalingMetric) {
                $name = ScalingRuleAdapter::$ruleTypeMap[$object->metric->alias];
            } else {
                $name = $this->getBareId($object, 'ruleType');
                if (!$name) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property ruleType');
                }
                if (!in_array($name, ScalingRuleAdapter::$ruleTypeMap)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected ruleType value');
                }
            }
            $name = static::$scalingRuleNamespace . "\\" . $name;
        }

        return parent::adapter($name, $scope, $version);
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
            ScopeInterface::SCOPE_FARMROLE
        );
    }
}
