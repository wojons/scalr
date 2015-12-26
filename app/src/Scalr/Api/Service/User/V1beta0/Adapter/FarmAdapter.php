<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use DateTimeZone;
use InvalidArgumentException;
use Scalr\Acl\Acl;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Account\Team;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Farm;
use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmSetting;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Ec2\DataType\VpcData;
use SERVER_PLATFORMS;
use Scalr_Governance;

/**
 * FarmAdapter V1beta0
 *
 * @author N.V.
 *
 * @method  \Scalr\Model\Entity\Farm toEntity($data) Converts data to entity
 */
class FarmAdapter extends ApiEntityAdapter
{

    const LAUNCH_ORDER_SIMULATENOUS = 'simulatenous';
    const LAUNCH_ORDER_SEQUENTIAL = 'sequential';

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', 'name', 'comments' => 'description',
            '_owner' => 'owner', '_teamOwner' => 'teamOwner',
            '_project' => 'project', '_vpc' => 'vpc', '_timezone' => 'timezone',
            '_launchOrder' => 'launchOrder'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'owner', 'teamOwner', 'timezone', 'vpc', 'project', 'launchOrder'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'name', 'owner', 'teamOwner', 'vpc', 'project', 'launchOrder'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\Farm';

    public function _owner($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Farm */
                $to->owner = ['id' => $from->createdById];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Farm */
                $owner = ApiController::getBareId($from, 'owner');

                if (!isset($owner)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed owner.id property");
                }

                if (!empty($to->createdById) && $to->createdById != $owner) {
                    $this->controller->checkPermissions($to, Acl::PERM_FARMS_CHANGE_OWNERSHIP);

                    $user = User::findOne([['id' => $owner], ['accountId' => $this->controller->getUser()->getAccountId()]]);
                    /* @var $user User */
                    if (!$user) {
                        throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested User either does not exist or is not owned by current account");
                    }

                    $to->createdByEmail = $user->getEmail();

                    $history = unserialize($to->settings[FarmSetting::OWNER_HISTORY]);

                    if (!is_array($history)) {
                        $history = [];
                    }

                    $history[] = [
                        'newId'             => $owner,
                        'newEmail'          => $to->createdByEmail,
                        'changedById'       => $this->controller->getUser()->getId(),
                        'changedByEmail'    => $this->controller->getUser()->getEmail(),
                        'dt'                => date('Y-m-d H:i:s')
                    ];
                    // TODO: move to subclass \Farm\Setting\OwnerHistory
                    $to->settings[FarmSetting::OWNER_HISTORY] = serialize($history);
                }

                $to->createdById = $owner;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $owner = ApiController::getBareId($from, 'owner');

                return [['createdById' => $owner]];
                break;
        }
    }

    public function _teamOwner($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Farm */
                $to->teamOwner = ['id' => $from->teamId];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Farm */
                $teamOwner = ApiController::getBareId($from, 'teamOwner');

                $user = $this->controller->getUser();

                if (!empty($to->createdById) && $to->teamId != $teamOwner) {
                    $this->controller->checkPermissions($to, Acl::PERM_FARMS_CHANGE_OWNERSHIP);
                }

                if (!empty($teamOwner)) {
                    $team = Team::findOne([['id' => $teamOwner], ['accountId' => $user->getAccountId()]]);
                    /* @var $team Team */
                    if (!($team && ($user->inTeam($teamOwner) || $user->canManageAcl()))) {
                        throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Team either does not exist or you do not have access to it.");
                    }
                }

                $to->teamId = $teamOwner;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $teamOwner = ApiController::getBareId($from, 'teamOwner');

                return [['teamId' => $teamOwner]];
                break;
        }
    }

    public function _project($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Farm */
                if (\Scalr::config('scalr.analytics.enabled')) {
                    $to->project = [
                        'id' => $from->settings[FarmSetting::PROJECT_ID]
                    ];
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Farm */
                if (\Scalr::config('scalr.analytics.enabled')) {
                    $projectId = ApiController::getBareId($from, 'project');

                    if (!isset($projectId)) {
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed project.id property");
                    }

                    $to->settings[FarmSetting::PROJECT_ID] = $projectId;
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $farm = new Farm();
                $farmSetting = new FarmSetting();

                $projectId = ApiController::getBareId($from, 'project');

                return [
                    AbstractEntity::STMT_FROM => $farm->table() . " LEFT JOIN " . $farmSetting->table() . " ON {$farmSetting->columnFarmId} = {$farm->columnId}",
                    AbstractEntity::STMT_WHERE => "({$farmSetting->columnName} = '" . FarmSetting::PROJECT_ID . "' AND {$farmSetting->columnValue} = " . $farmSetting->qstr('value', $projectId) . ")"
                ];
                break;
        }
    }

    public function _vpc($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Farm */
                $vpc = [];

                if (!empty($from->settings[FarmSetting::EC2_VPC_ID])) {
                    $vpc['id'] = $from->settings[FarmSetting::EC2_VPC_ID];
                }

                if (!empty($from->settings[FarmSetting::EC2_VPC_REGION])) {
                    $vpc['region'] = $from->settings[FarmSetting::EC2_VPC_REGION];
                }

                if (!empty($vpc)) {
                    $to->vpc = (object) $vpc;
                }

                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Farm */
                $vpcId = ApiController::getBareId($from, 'vpc');

                if (!is_string($vpcId)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed vpc.id property");
                }

                if ($to->status || FarmRole::find([[ 'farmId' => $to->id ], [ 'platform' => \SERVER_PLATFORMS::EC2 ]], null, null, null, null, true)->totalNumber) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_OBJECT_IN_USE, "To change VPC settings you must first stop farm and remove all EC2 farm-roles");
                }

                $to->settings[FarmSetting::EC2_VPC_ID] = $vpcId;

                if (!empty($from->vpc->region)) {
                    $to->settings[FarmSetting::EC2_VPC_REGION] = $from->vpc->region;
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $farm = new Farm();
                $farmSetting = new FarmSetting();

                $vpcId = ApiController::getBareId($from, 'vpc');

                return [
                    AbstractEntity::STMT_FROM => $farm->table() . " LEFT JOIN " . $farmSetting->table() . " ON {$farmSetting->columnFarmId} = {$farm->columnId}",
                    AbstractEntity::STMT_WHERE => "({$farmSetting->columnName} = '" . FarmSetting::EC2_VPC_ID . "' AND {$farmSetting->columnValue} = " . $farmSetting->qstr('value', $vpcId) . ")"
                ];
        }
    }

    public function _timezone($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Farm */
                $to->timezone = $from->settings[FarmSetting::TIMEZONE];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Farm */
                if (!in_array($from->timezone, DateTimeZone::listIdentifiers())) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unknown timezone");
                }

                $to->settings[FarmSetting::TIMEZONE] = $from->timezone;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                $farm = new Farm();
                $farmSetting = new FarmSetting();

                return [
                    AbstractEntity::STMT_FROM => $farm->table() . " LEFT JOIN " . $farmSetting->table() . " ON {$farmSetting->columnFarmId} = {$farm->columnId}",
                    AbstractEntity::STMT_WHERE => "({$farmSetting->columnName} = '" . FarmSetting::TIMEZONE . "' AND {$farmSetting->columnValue} = " . $farmSetting->qstr('value', $from->timezone) . ")"
                ];
        }
    }

    public function _launchOrder($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Farm */
                $to->launchOrder = $from->launchOrder ? static::LAUNCH_ORDER_SEQUENTIAL : static::LAUNCH_ORDER_SIMULATENOUS;
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Farm */
                switch (strtolower($from->launchOrder)) {
                    case static::LAUNCH_ORDER_SIMULATENOUS:
                        $to->launchOrder = false;
                        break;

                    case static::LAUNCH_ORDER_SEQUENTIAL:
                        $to->launchOrder = true;
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected launchOrder value");
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                switch (strtolower($from->launchOrder)) {
                    case static::LAUNCH_ORDER_SIMULATENOUS:
                        $launchOrder = false;
                        break;

                    case static::LAUNCH_ORDER_SEQUENTIAL:
                        $launchOrder = true;
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
                }

                return [[ 'launchOrder' => $launchOrder ]];
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof Farm) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\Farm class"
            ));
        }

        if ($entity->id !== null) {
            if (!Farm::findPk($entity->id)) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find out the Farm with ID: %d", $entity->id
                ));
            }
        } else {
            if (empty($entity->name)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property name");
            }

            $criteria = $this->controller->getScopeCriteria();
            $criteria[] = ['name' => $entity->name];

            if (count(Farm::find($criteria))) {
                throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, "Farm with name '{$entity->name}' already exists");
            }
        }

        if (!empty($entity->settings[FarmSetting::EC2_VPC_REGION])) {
            $region = $entity->settings[FarmSetting::EC2_VPC_REGION];
            $vpcId =  $entity->settings[FarmSetting::EC2_VPC_ID];

            if (!in_array($region, Aws::getCloudLocations())) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unknown VPC region");
            }

            $gov = new Scalr_Governance($this->controller->getEnvironment()->id);
            $vpcGovernanceRegions = $gov->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_VPC, 'regions');
            if (isset($vpcGovernanceRegions)) {
                if (!array_key_exists($region, $vpcGovernanceRegions)) {
                    $regions = array_keys($vpcGovernanceRegions);
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                        "Only %s %s allowed according to governance settings",
                        ...(count($regions) > 1 ? [implode(', ', $regions), 'regions are'] : [array_shift($regions), 'region is'])
                    ));
                }

                $vpcGovernanceIds = $vpcGovernanceRegions[$region]['ids'];
                if (!empty($vpcGovernanceIds) && !in_array($vpcId, $vpcGovernanceIds)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, sprintf(
                        "Only %s %s allowed according to governance settings",
                        ...(count($vpcGovernanceIds) > 1 ? [implode(', ', $vpcGovernanceIds), 'vpcs are'] : [array_shift($vpcGovernanceIds), 'vpc is'])
                    ));
                }
            }
            $found = null;

            /* @var $vpc VpcData */
            //TODO rewrite aws service usage
            foreach ($this->controller->getContainer()->aws($region, $this->controller->getEnvironment())->ec2->vpc->describe() as $vpc) {
                if ($vpcId == $vpc->vpcId) {
                    $found = $vpc;
                }
            }

            if (empty($found)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Could not find out the VPC with ID '{$vpcId}' in region '{$region}'");
            }
        } else if (!empty($entity->settings[FarmSetting::EC2_VPC_ID])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property vpc.region");
        }

        if (\Scalr::config('scalr.analytics.enabled')) {
            if (isset($entity->settings[FarmSetting::PROJECT_ID])) {
                if (!$this->controller->getContainer()->analytics->projects->get($entity->settings[FarmSetting::PROJECT_ID])) {
                    throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "The project is not allowed for you");
                }
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property project");
            }
        }

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }
    }
}