<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use InvalidArgumentException;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;
use Scalr\Api\Service\User\V1beta0\Controller\FarmRoles;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Model\Entity\FarmSetting;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\FarmRoleScalingMetric;
use Scalr\Model\Entity\RoleProperty;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData;
use Scalr_Environment;
use Scalr_Role_Behavior_Router;
use SERVER_PLATFORMS;
use Scalr_Governance;
use ROLE_BEHAVIORS;
use Scalr_Role_Behavior_Chef;
use Scalr\Model\Collections\EntityIterator;

/**
 * FarmRoleAdapter V1
 *
 * @author N.V.
 *
 * @method  FarmRole toEntity($data) Converts data to entity
 *
 * @property FarmRoles $controller
 */
class FarmRoleAdapter extends ApiEntityAdapter
{

    /**
     * @var FarmRoles
     */
    protected $controller;

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', 'alias', 'platform',
            '_farm' => 'farm', '_role' => 'role', '_scaling' => 'scaling',
            '_placement' => 'placement', '_instance' => 'instance'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['alias', 'role', 'platform', 'scaling', 'placement', 'instance'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'farm', 'role', 'platform'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * List of behaviors disabled for scaling
     */
    public static $disableScalingBehaviors = [
        ROLE_BEHAVIORS::CF_HEALTH_MANAGER,
        ROLE_BEHAVIORS::CF_CLOUD_CONTROLLER,
        ROLE_BEHAVIORS::MONGODB,
        ROLE_BEHAVIORS::RABBITMQ
    ];

    /**
     * List of unique behaviors
     * Farm can include only one farm-role with following behaviors
     *
     * @var array
     */
    protected static $uniqueFarmBehaviors = [
        [ROLE_BEHAVIORS::POSTGRESQL],
        [ROLE_BEHAVIORS::REDIS],
        [ROLE_BEHAVIORS::MONGODB],
        ROLE_BEHAVIORS::MYSQL => [ROLE_BEHAVIORS::MYSQL, ROLE_BEHAVIORS::MYSQL2, ROLE_BEHAVIORS::PERCONA]
    ];

    /**
     * List of farm role supported behaviors
     *
     * @var array
     */
    protected static $farmRoleSupportedBehaviors = [
        ROLE_BEHAVIORS::BASE,
        ROLE_BEHAVIORS::CHEF
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\FarmRole';

    public function _farm($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRole */
                $to->farm = [ 'id' => $from->farmId ];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRole */
                $to->farmId = ApiController::getBareId($from, 'farm');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ 'farmId' => ApiController::getBareId($from, 'farm') ]];
        }
    }

    public function _role($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRole */
                $to->role = [ 'id' => $from->roleId ];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRole */
                $roleId = ApiController::getBareId($from, 'role');
                $role = $this->controller->getRole($roleId);

                if ($to->roleId != $roleId) {
                    $envs = $role->getAllowedEnvironments();
                    if (!empty($envs) && !in_array($this->controller->getEnvironment()->id, $envs)) {
                        throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                            "Could not find out the Role with ID: %d", $role->id
                        ));
                    }

                    if ($role->isDeprecated) {
                        throw new ApiErrorException(409, ErrorMessage::ERR_DEPRECATED, "Role '{$roleId}' is deprecated");
                    }

                    $destinationBehaviors = $role->getBehaviors();
                    if ($role->isScalarized) {
                        $unsupportedBehaviors = array_diff($destinationBehaviors, static::$farmRoleSupportedBehaviors);
                        if (!empty($unsupportedBehaviors)) {
                            throw new ApiNotImplementedErrorException(sprintf(
                                'For now Scalr API supports only following built-in automation types: %s.',
                                implode(', ', RoleAdapter::behaviorsToData(static::$farmRoleSupportedBehaviors))
                            ));
                        }
                    }

                    /* @var $sourceRole Role */
                    $sourceRole = $to->getRole();
                    if (!empty($sourceRole)) {
                        if ($sourceRole->getOs()->family !== $role->getOs()->family) {
                            throw new ApiErrorException(409, ErrorMessage::ERR_OS_MISMATCH, sprintf(
                                'Operating System of the Role "%s" must match to the OS of the replacement Role "%s"',
                                $sourceRole->getOs()->family,
                                $role->getOs()->family
                            ));
                        }

                        if ($sourceRole->isScalarized) {
                            if (!$role->isScalarized) {
                                throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH,
                                    'Can not replace the Role that has Scalr Agent to the agentless Role'
                                );
                            }

                            $sourceBehaviors = $sourceRole->getBehaviors();
                            if ($sourceRole->hasBehavior(ROLE_BEHAVIORS::CHEF) &&
                                empty($to->settings[Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP]) &&
                                empty(RoleProperty::findOne([
                                    ['name' => Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP],
                                    ['roleId' => $sourceRole->id],
                                    ['value' => 1]
                                ]))) {
                                $sourceBehaviors = array_diff($sourceBehaviors, [ROLE_BEHAVIORS::CHEF]);
                            }

                            $compareBehaviors = array_diff($sourceBehaviors, $destinationBehaviors);
                            if (!empty($compareBehaviors)) {
                                throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, sprintf(
                                    'The replacement role does not include the necessary builtinAutomation %s',
                                    implode(', ', RoleAdapter::behaviorsToData($compareBehaviors))
                                ));
                            }
                        }
                    }
                }

                $to->roleId = $roleId;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ 'roleId' => ApiController::getBareId($from, 'role') ]];
        }
    }

    public function _scaling($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRole */
                $to->scaling = static::getScalingConfiguration($from);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRole */
                static::setupScalingConfiguration($to, $from->scaling);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[  ]];
        }
    }

    public function _placement($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRole */
                $to->placement = static::getPlacementConfiguration($from);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRole */
                static::setupPlacementConfiguration($to, $from->placement);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[  ]];
        }
    }

    public function _instance($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from FarmRole */
                $to->instance = static::getInstanceConfiguration($from);
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to FarmRole */
                static::setupInstanceConfiguration($to, $from->instance);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[  ]];
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof FarmRole) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\FarmRole class"
            ));
        }

        if ($entity->id !== null) {
            if (!FarmRole::findPk($entity->id)) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find out the Farm with ID: %d", $entity->id
                ));
            }
        }

        if (empty($entity->farmId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property farm.id");
        } else {
            /* @var  $farm Farm */
            $farm = $this->controller->getFarm($entity->farmId, true);
        }

        if (empty($entity->alias)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property alias");
        }

        if (!preg_match("/^[[:alnum:]](?:-*[[:alnum:]])*$/", $entity->alias)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Alias should start and end with letter or number and contain only letters, numbers and dashes.");
        }

        if (empty($entity->roleId)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property role.id");
        }
        $roleBehaviors = $entity->getRole()->getBehaviors();
        $uniqueBehaviors = array_intersect($roleBehaviors, array_merge(...array_values(static::$uniqueFarmBehaviors)));
        if (!empty($uniqueBehaviors)) {
            //farm can include only one mysql or percona role
            if (array_intersect($uniqueBehaviors, static::$uniqueFarmBehaviors[ROLE_BEHAVIORS::MYSQL])) {
                $uniqueBehaviors = array_merge($uniqueBehaviors, array_diff(static::$uniqueFarmBehaviors[ROLE_BEHAVIORS::MYSQL], $uniqueBehaviors));
            }

            $farmRoleEntity = new FarmRole();
            $roleEntity = new Role();

            /* @var $conflicts EntityIterator */
            $conflicts = Role::find([
                AbstractEntity::STMT_FROM => "{$roleEntity->table()} JOIN {$farmRoleEntity->table('fr')} ON {$farmRoleEntity->columnRoleId('fr')} = {$roleEntity->columnId()}",
                AbstractEntity::STMT_WHERE => "{$farmRoleEntity->columnFarmId('fr')} = {$farmRoleEntity->qstr('farmId', $entity->farmId)} " .
                    (empty($entity->id) ? '' : " AND {$farmRoleEntity->columnId('fr')} <> {$farmRoleEntity->qstr('id', $entity->id)}"),
                ['behaviors' => ['$regex' => implode('|', $uniqueBehaviors)]],
            ]);


            if ($conflicts->count() > 0) {
                $conflictedBehaviors = [];
                /* @var  $role Role */
                foreach ($conflicts as $role) {
                    $conflictedBehaviors = array_merge($conflictedBehaviors, array_intersect($uniqueBehaviors, $role->getBehaviors()));
                }

                if (!empty(array_intersect($conflictedBehaviors, static::$uniqueFarmBehaviors[ROLE_BEHAVIORS::MYSQL]))) {
                    $conflictedBehaviors = array_diff($conflictedBehaviors, static::$uniqueFarmBehaviors[ROLE_BEHAVIORS::MYSQL]);
                    $conflictedBehaviors[] = 'mysql/percona';
                }

                $conflictedBehaviors = RoleAdapter::behaviorsToData($conflictedBehaviors);

                throw new ApiErrorException(
                    409,
                    ErrorMessage::ERR_UNICITY_VIOLATION,
                    sprintf('Only one [%s] role can be added to farm', implode(', ', $conflictedBehaviors))
                );
            }
        }

        if (empty($entity->platform)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property platform");
        }

        switch ($entity->platform) {
            case SERVER_PLATFORMS::EC2:
                if (empty($entity->settings[FarmRoleSetting::INSTANCE_TYPE])) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Missed property instance.type");
                }

                /* @var $platform Ec2PlatformModule */
                $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);

                if (!in_array($entity->settings[FarmRoleSetting::INSTANCE_TYPE], $platform->getInstanceTypes())) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Wrong instance type");
                }

                $gov = new Scalr_Governance($this->controller->getEnvironment()->id);
                $allowGovernanceIns = $gov->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::INSTANCE_TYPE);
                if (isset($allowGovernanceIns) && !in_array($entity->settings[FarmRoleSetting::INSTANCE_TYPE], $allowGovernanceIns)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                        "Only %s %s allowed according to governance settings",
                        ...(count($allowGovernanceIns) > 1 ? [implode(', ', $allowGovernanceIns), 'instances are'] : [array_shift($allowGovernanceIns), 'instance is'])
                    ));
                }

                if (!in_array($entity->cloudLocation, Aws::getCloudLocations())) {
                    throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Unknown region");
                }

                $vpcGovernanceRegions = $gov->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, 'regions');
                if (isset($vpcGovernanceRegions) && !array_key_exists($entity->cloudLocation, $vpcGovernanceRegions)) {
                    $regions = array_keys($vpcGovernanceRegions);
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                        "Only %s %s allowed according to governance settings",
                        ...(count($regions) > 1 ? [implode(', ', $regions), 'regions are'] : [array_shift($regions), 'region is'])
                    ));
                }

                $env = Scalr_Environment::init()->loadById($this->controller->getEnvironment()->id);
                $aws = $this->controller->getContainer()->aws($entity->cloudLocation, $env);

                if (!empty($entity->settings[FarmRoleSetting::AWS_AVAIL_ZONE]) && $entity->settings[FarmRoleSetting::AWS_AVAIL_ZONE] !== 'x-scalr-diff') {

                    $availZones = explode(":", str_replace("x-scalr-custom=", '', $entity->settings[FarmRoleSetting::AWS_AVAIL_ZONE]));
                    $ec2availabilityZones = [];
                    foreach ($aws->ec2->availabilityZone->describe() as $zone) {
                        /* @var $zone AvailabilityZoneData */
                        if (stristr($zone->zoneState, 'available')) {
                            $ec2availabilityZones[] = $zone->zoneName;
                        }
                    }
                    $diffZones = array_diff($availZones, $ec2availabilityZones);
                    if (!empty($diffZones)) {
                        throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                            '%s %s available. Available zones are %s',
                            ...(count($diffZones) > 1) ? [implode(', ', $diffZones), 'zones are not', implode(', ', $ec2availabilityZones)] : [array_shift($diffZones), 'zone is not', implode(', ', $ec2availabilityZones)]
                        ));
                    }
                }

                if (!empty($farm->settings[FarmSetting::EC2_VPC_ID])) {
                    if (empty($entity->settings[FarmRoleSetting::AWS_VPC_SUBNET_ID])) {
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "VPC Subnet(s) should be described");
                    }

                    $vpcId = $farm->settings[FarmSetting::EC2_VPC_ID];
                    $subnets = $platform->listSubnets($env, $entity->cloudLocation, $vpcId, true);
                    $vpcGovernanceIds = $gov->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, 'ids');
                    $subnetType = null;

                    foreach (json_decode($entity->settings[FarmRoleSetting::AWS_VPC_SUBNET_ID]) as $subnetId) {
                        $found = false;

                        foreach ($subnets as $subnet) {
                            if ($subnet['id'] == $subnetId) {
                                if ($subnetType == null) {
                                    $subnetType = $subnet['type'];
                                } else if ($subnet['type'] != $subnetType) {
                                    throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, "All subnets must be a same type");
                                }
                                //check governance subnet settings
                                if (isset($vpcGovernanceIds[$vpcId])) {
                                    if (!empty($vpcGovernanceIds[$vpcId]) && is_array($vpcGovernanceIds[$vpcId]) && !in_array($subnetId, $vpcGovernanceIds[$vpcId])) {
                                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                                            "Only %s %s allowed by governance settings",
                                            ...(count($vpcGovernanceIds[$vpcId]) > 1 ? [implode(', ', $vpcGovernanceIds[$vpcId]), 'subnets are'] : [array_shift($vpcGovernanceIds[$vpcId]), 'subnet is'])
                                        ));
                                    } else if ($vpcGovernanceIds[$vpcId] == "outbound-only" && $subnetType != 'private' ) {
                                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Only private subnets allowed by governance settings");
                                    } else if ($vpcGovernanceIds[$vpcId] == "full" && $subnetType != 'public' ) {
                                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Only public subnets allowed by governance settings");
                                    }
                                }
                                $found = true;
                            }
                        }

                        if (!$found) {
                            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Subnet with id '{$subnetId}' not found");
                        }
                    }

                    if (!empty($entity->settings[Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID])) {
                        $router = $this->controller->getFarmRole($entity->settings[Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID]);

                        if (empty($router->settings[Scalr_Role_Behavior_Router::ROLE_VPC_NID])) {
                            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Farm-role with id '{$router->id}' is not a valid router");
                        }
                    } else if (\Scalr::config('scalr.instances_connection_policy') != 'local' && $subnetType == 'private') {
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "You must describe a VPC Router");
                    }
                }
                break;

            default:
                if (isset(SERVER_PLATFORMS::GetList()[$entity->platform])) {
                    throw new ApiErrorException(501, ErrorMessage::ERR_NOT_IMPLEMENTED, "Platform '{$entity->platform}' is not supported yet");
                } else {
                    throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Unknown platform '{$entity->platform}'");
                }
        }

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }
    }

    /**
     * Gets instance configuration data
     *
     * @param   FarmRole    $role FarmRole entity
     *
     * @return array InstanceConfiguration representation
     *
     * @see <need link to public API documentation>
     */
    public static function getInstanceConfiguration(FarmRole $role)
    {
        $configuration = [];

        switch ($role->platform) {
            case SERVER_PLATFORMS::EC2:
                $configuration['instanceConfigurationType'] = FarmRoles::AWS_INSTANCE_CONFIGURATION;

                if (!empty($role->settings[FarmRoleSetting::AWS_EBS_OPTIMIZED])) {
                    $configuration['ebsOptimized'] = $role->settings[FarmRoleSetting::AWS_EBS_OPTIMIZED];
                }

                if (!empty($role->settings[FarmRoleSetting::INSTANCE_TYPE])) {
                    $configuration['instanceType']['id'] = $role->settings[FarmRoleSetting::INSTANCE_TYPE];
                }
                break;
        }

        return $configuration;
    }

    /**
     * Gets placement configuration data
     *
     * @param   FarmRole    $role FarmRole entity
     *
     * @return array PlacementConfiguration representation
     *
     * @see <need link to public API documentation>
     */
    public static function getPlacementConfiguration(FarmRole $role)
    {
        $configuration = [];

        switch ($role->platform) {
            case SERVER_PLATFORMS::EC2:
                $configuration['region'] = $role->cloudLocation;

                if (!empty($role->settings[FarmRoleSetting::AWS_VPC_SUBNET_ID])) {
                    $configuration['placementConfigurationType'] = FarmRoles::AWS_VPC_PLACEMENT_CONFIGURATION;

                    foreach (json_decode($role->settings[FarmRoleSetting::AWS_VPC_SUBNET_ID]) as $subnet) {
                        $configuration['subnets'][] = [
                            'id' => $subnet
                        ];
                    }

                    if (!empty($role->settings[Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID])) {
                        $configuration['router'] = [
                            'id' => $role->settings[Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID]
                        ];
                    }
                //TODO: improve that condition check
                } else if (!empty($role->settings[FarmRoleSetting::AWS_AVAIL_ZONE])) {
                    $configuration['placementConfigurationType'] = FarmRoles::AWS_CLASSIC_PLACEMENT_CONFIGURATION;
                    $farmRoleAvailabilityZones = $role->settings[FarmRoleSetting::AWS_AVAIL_ZONE];
                    if ($farmRoleAvailabilityZones === 'x-scalr-diff') {
                        $configuration['availabilityZones'] = [];
                    } else {
                        foreach (explode(":", str_replace("x-scalr-custom=", '', $farmRoleAvailabilityZones)) as $zoneName) {
                            $configuration['availabilityZones'][] = $zoneName;
                        }
                    }
                } else {
                    $configuration['placementConfigurationType'] = FarmRoles::AWS_CLASSIC_PLACEMENT_CONFIGURATION;
                    $configuration['availabilityZones'] = null;
                }
                break;

            default:
                break;
        }

        return $configuration;
    }

    /**
     * Gets scaling configuration data
     *
     * @param   FarmRole    $role FarmRole entity
     *
     * @return array ScalingConfiguration representation
     *
     * @see <need link to public API documentation>
     */
    public static function getScalingConfiguration(FarmRole $role)
    {
        $configuration = [];

        if (!empty($role->settings[FarmRoleSetting::SCALING_ENABLED])) {
            $configuration['enabled'] = !!$role->settings[FarmRoleSetting::SCALING_ENABLED];
        }

        if (!empty($role->settings[FarmRoleSetting::SCALING_MIN_INSTANCES])) {
            $configuration['minInstances'] = $role->settings[FarmRoleSetting::SCALING_MIN_INSTANCES];
        }

        if (!empty($role->settings[FarmRoleSetting::SCALING_MAX_INSTANCES])) {
            $configuration['maxInstances'] = $role->settings[FarmRoleSetting::SCALING_MAX_INSTANCES];
        }

        $configuration['rules'] = ScalingMetricAdapter::metricNameToData(FarmRole::listFarmRoleMetric($role->id));

        return $configuration;
    }

    /**
     * Setups given placement configuration to specified farm role
     *
     * @param   FarmRole    $role       Configurable farm role
     * @param   object      $placement  Placement configuration
     *
     * @throws ApiErrorException
     */
    public static function setupPlacementConfiguration(FarmRole $role, $placement)
    {
        if (empty($placement->placementConfigurationType)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property placement.placementConfigurationType');
        }

        if (empty($placement->region)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property region");
        }

        $role->cloudLocation = $placement->region;

        switch ($placement->placementConfigurationType) {
            case FarmRoles::AWS_VPC_PLACEMENT_CONFIGURATION:
                if (empty($placement->subnets)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property subnets");
                }

                if (!is_array($placement->subnets)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Property subnets must be array");
                }

                $subnets = [];

                foreach ($placement->subnets as $subnet) {
                    $subnets[] = $subnet->id;
                }

                $role->settings[FarmRoleSetting::AWS_VPC_SUBNET_ID] = json_encode($subnets);

                if (isset($placement->router)) {
                    $role->settings[Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID] = ApiController::getBareId($placement, 'router');
                }
                break;

            case FarmRoles::AWS_CLASSIC_PLACEMENT_CONFIGURATION:
                if (isset($placement->availabilityZones) && is_array($placement->availabilityZones)) {
                    if (empty($placement->availabilityZones)) {
                        $role->settings[FarmRoleSetting::AWS_AVAIL_ZONE] = "x-scalr-diff";
                    } else {
                        $role->settings[FarmRoleSetting::AWS_AVAIL_ZONE] = count($placement->availabilityZones) > 1 ? "x-scalr-custom=" . implode(':', array_unique($placement->availabilityZones)) : array_shift($placement->availabilityZones);
                    }
                } else if (empty($placement->availabilityZones)) {
                    $role->settings[FarmRoleSetting::AWS_AVAIL_ZONE] = '';
                } else {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Property availabilityZones must be array or NULL");
                }
                break;

            case FarmRoles::OPEN_STACK_PLACEMENT_CONFIGURATION:
            case FarmRoles::CLOUD_STACK_PLACEMENT_CONFIGURATION:
            case FarmRoles::GCE_PLACEMENT_CONFIGURATION:
                throw new ApiErrorException(501, ErrorMessage::ERR_NOT_IMPLEMENTED, 'Unsupported placementConfigurationType');

            default:
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unknown placementConfigurationType');
        }
    }

    /**
     * Setups given scaling configuration to specified farm role
     *
     * @param   FarmRole    $farmRole       Configurable farm role
     * @param   object      $scaling    Scaling configuration
     *
     * @throws ApiErrorException
     */
    public static function setupScalingConfiguration(FarmRole $farmRole, $scaling)
    {
        $disabledScalingBehaviors = array_intersect(static::$disableScalingBehaviors, $farmRole->getRole()->getBehaviors());
        if (!empty($disabledScalingBehaviors)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_CONFIGURATION_MISMATCH, sprintf(
                'Can not add scaling configuration to the Role with the following built-in automation types: %s.',
                implode(', ', RoleAdapter::behaviorsToData($disabledScalingBehaviors))
            ));
        }

        if (isset($scaling->enabled)) {
            $farmRole->settings[FarmRoleSetting::SCALING_ENABLED] = intval($scaling->enabled);
        }

        if (isset($scaling->minInstances)) {
            if ($scaling->minInstances < 0) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Property scaling.minInstances must be greater than or equal to 0");
            }

            $farmRole->settings[FarmRoleSetting::SCALING_MIN_INSTANCES] = intval($scaling->minInstances);
        }

        if (isset($scaling->maxInstances)) {
            if ($scaling->maxInstances > 400) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Property scaling.maxInstances must be less than or equal to 400");
            }

            $farmRole->settings[FarmRoleSetting::SCALING_MAX_INSTANCES] = intval($scaling->maxInstances);
        }

        if ($farmRole->settings[FarmRoleSetting::SCALING_MAX_INSTANCES] < $farmRole->settings[FarmRoleSetting::SCALING_MIN_INSTANCES]) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Property scaling.maxInstances must be greater than or equal to scaling.minInstances");
        }
    }

    /**
     * Setups given instance configuration to specified farm role
     *
     * @param   FarmRole    $role       Configurable farm role
     * @param   object      $instance   Instance configuration
     *
     * @throws ApiErrorException
     */
    public static function setupInstanceConfiguration(FarmRole $role, $instance)
    {
        if (empty($instance->instanceConfigurationType)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Missed property instance.instanceConfigurationType');
        }

        switch ($instance->instanceConfigurationType) {
            case FarmRoles::AWS_INSTANCE_CONFIGURATION:
                if (isset($instance->instanceType)) {
                    $type = ApiController::getBareId($instance, 'instanceType');

                    $role->settings[FarmRoleSetting::INSTANCE_TYPE] = $type;
                }

                if (isset($instance->ebsOptimized)) {
                    $role->settings[FarmRoleSetting::AWS_EBS_OPTIMIZED] = $instance->ebsOptimized;
                }
                break;

            default:
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Unknown instanceConfigurationType');
        }
    }
}