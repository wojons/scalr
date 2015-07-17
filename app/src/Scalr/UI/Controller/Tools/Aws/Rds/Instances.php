<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws\Rds\DataType\CreateDBInstanceReadReplicaData;
use Scalr\Service\Aws\Rds\DataType\CreateDBInstanceRequestData;
use Scalr\Service\Aws\Rds\DataType\CreateDBSubnetGroupRequestData;
use Scalr\Service\Aws\Rds\DataType\DBParameterGroupData;
use Scalr\Service\Aws\Rds\DataType\DescribeDBEngineVersionsData;
use Scalr\Service\Aws\Rds\DataType\DescribeOrderableDBInstanceOptionsData;
use Scalr\Service\Aws\Rds\DataType\ModifyDBInstanceRequestData;
use Scalr\Service\Aws\Rds\DataType\OptionGroupData;
use Scalr\Service\Aws\Rds\DataType\RestoreDBInstanceFromDBSnapshotRequestData;
use Scalr\Service\Aws\Rds\DataType\OrderableDBInstanceOptionsData;
use Scalr\Service\Aws\Rds\DataType\TagsList;
use Scalr\UI\Request\JsonData;
use Scalr\Model\Entity\CloudResource;

class Scalr_UI_Controller_Tools_Aws_Rds_Instances extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'instanceId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_RDS);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/rds/instances/view.js', []);
    }

    public function view2Action()
    {
        $this->response->page('ui/tools/aws/rds/instances/view2.js', []);
    }

    public function createAction()
    {
        $this->response->page(['ui/tools/aws/rds/instances/create.js', 'ui/security/groups/sgeditor.js'], array(
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'accountId'     => $this->environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID),
            'remoteAddress' => $this->request->getRemoteAddr(),
            'farms'         => self::loadController('Farms')->getList()
        ), ['ui/tools/aws/rds/rds.js']);
    }

    public function editAction($cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $dbinstance = $aws->rds->dbInstance->describe($this->getParam(self::CALL_PARAM_NAME))->get(0)->toArray(true);

        $vpcSglist = $aws->ec2->securityGroup->describe();

        foreach ($dbinstance['VpcSecurityGroups'] as &$vpcSg) {
            foreach ($vpcSglist as $vpcSqData) {
                /* @var $vpcSqData \Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
                $vpcSecurityGroupName = null;

                if ($vpcSqData->groupId == $vpcSg['VpcSecurityGroupId']) {
                    $vpcSecurityGroupName = $vpcSqData->groupName;
                    $vpcId = $vpcSqData->vpcId;
                    break;
                }
            }

            $vpcSg = [
                'vpcSecurityGroupId'   => $vpcSg['VpcSecurityGroupId'],
                'vpcSecurityGroupName' => $vpcSecurityGroupName
            ];
        }

        $dbinstance['DBSubnetGroupName'] = isset($dbinstance['DBSubnetGroup']['DBSubnetGroupName']) ? $dbinstance['DBSubnetGroup']['DBSubnetGroupName'] : null;

        foreach ($dbinstance['DBSecurityGroups'] as &$dbSg) {
            $dbSg = $dbSg['DBSecurityGroupName'];
        }

        foreach ($dbinstance['OptionGroupMembership'] as &$member) {
            $dbinstance['OptionGroupName'] = $member['OptionGroupName'];
            break;
        }

        foreach ($dbinstance['DBParameterGroups'] as &$param) {
            $dbinstance['DBParameterGroup'] = $param['DBParameterGroupName'];
            break;
        }

        if ($dbinstance['engine'] == 'mysql') {
            $dbinstance['engine'] = 'MySql';
        }

        $dbinstance['Port'] = $dbinstance['Endpoint']['Port'];

        $dbinstance ['VpcId'] = !empty($vpcId) ? $vpcId : null;

        $cloudResource = CloudResource::findPk(
            $dbinstance['DBInstanceIdentifier'],
            $this->getEnvironmentId(),
            \SERVER_PLATFORMS::EC2,
            $cloudLocation
        );
        if ($cloudResource) {
            try {
                $dbFarm = DBFarm::LoadByID($cloudResource->farmId);
                $dbinstance['farmName'] = $dbFarm->Name;
            } catch (Exception $e){}
        }

        $this->response->page([ 'ui/tools/aws/rds/instances/edit.js', 'ui/security/groups/sgeditor.js' ], array(
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'instance'      => $dbinstance,
            'accountId'     => $this->environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_ID),
            'remoteAddress' => $this->request->getRemoteAddr()
        ), ['ui/tools/aws/rds/rds.js']);
    }

    /**
     * @param string $cloudLocation
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Scalr_UI_Exception_NotFound
     */
    public function createReadReplicaAction($cloudLocation)
    {
        $dbInstance = $this->getEnvironment()->aws($cloudLocation)->rds->dbInstance->describe($this->getParam(self::CALL_PARAM_NAME))->get(0)->toArray(true);

        foreach ($dbInstance['OptionGroupMembership'] as &$member) {
            $member['optionGroupName'] = $member['OptionGroupName'];
            $dbInstance['OptionGroupName'] = $member['OptionGroupName'];
            break;
        }

        unset($dbInstance['DBInstanceIdentifier']);

        $dbInstance['Port'] = $dbInstance['Endpoint']['Port'];

        $dbInstance['DBSubnetGroupName'] = isset($dbInstance['DBSubnetGroup']['DBSubnetGroupName']) ? $dbInstance['DBSubnetGroup']['DBSubnetGroupName'] : null;

        foreach ($dbInstance['DBParameterGroups'] as &$param) {
            $dbInstance['DBParameterGroup'] = $param['DBParameterGroupName'];
            break;
        }

        $this->response->page('ui/tools/aws/rds/instances/createReadReplica.js', array(
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'instance'      => $dbInstance
        ));
    }

    public function promoteReadReplicaAction()
    {
        $this->response->page('ui/tools/aws/rds/instances/promoteReadReplica.js');
    }

    /**
     * xSaveReadReplicaAction
     *
     * @param string $cloudLocation
     * @param string $DBInstanceIdentifier
     * @param string $SourceDBInstanceIdentifier
     */
    public function xSaveReadReplicaAction($cloudLocation, $DBInstanceIdentifier, $SourceDBInstanceIdentifier)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $request = new CreateDBInstanceReadReplicaData($DBInstanceIdentifier, $SourceDBInstanceIdentifier);
        $request->autoMinorVersionUpgrade = $this->getParam('AutoMinorVersionUpgrade') == 'false' ? false : true;
        $request->availabilityZone = $this->getParam('AvailabilityZone') ?: null;
        $request->dBInstanceClass = $this->getParam('DBInstanceClass') ?: null;
        $request->dBSubnetGroupName = $this->getParam('DBSubnetGroupName') ?: null;
        $request->iops = $this->getParam('Iops') ?: null;
        $request->port = $this->getParam('Port') ?: null;
        $request->publiclyAccessible = $this->getParam('PubliclyAccessible') ?: null;
        $request->sourceDBInstanceIdentifier = $this->getParam('SourceDBInstanceIdentifier') ?: null;
        $request->storageType = $this->getParam('StorageType') ?: null;

        $optionList = $aws->rds->optionGroup->describe();

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $this->getParam('OptionGroupName')) {
                $optionGroup = $option;
                break;
            }
        }

        if (!empty($optionGroup)) {
            $request->optionGroupName = $optionGroup->optionGroupName;
        }

        $aws->rds->dbInstance->createReplica($request);
        $this->response->success("DB Instance Read Replica has been successfully created");
    }

    /**
     * xPromoteReadReplicaAction
     *
     * @param string     $cloudLocation
     * @param string     $DBInstanceIdentifier
     * @param int        $BackupRetentionPeriod  optional
     * @param string     $PreferredBackupWindow  optional
     */
    public function xPromoteReadReplicaAction($cloudLocation, $DBInstanceIdentifier, $BackupRetentionPeriod = null, $PreferredBackupWindow = null)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $aws->rds->dbInstance->promoteReplica($DBInstanceIdentifier, $BackupRetentionPeriod, $PreferredBackupWindow);

        $this->response->success("DB Instance Read Replica has been successfully promoted");
    }

    /**
     * @param JsonData $VpcSecurityGroups
     * @param JsonData $DBSecurityGroups
     */
    public function xModifyInstanceAction(JsonData $VpcSecurityGroups = null, JsonData $DBSecurityGroups = null)
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $request = new ModifyDBInstanceRequestData($this->getParam('DBInstanceIdentifier'));

        $paramName = $this->getParam('DBParameterGroup');

        if (!empty($paramName)) {
            $paramGroups = $aws->rds->dbParameterGroup->describe();

            foreach ($paramGroups as $param) {
                /* @var $param DBParameterGroupData */
                if ($param->dBParameterGroupName == $paramName) {
                    $paramGroup = $param;
                    break;
                }
            }
        }

        if (!empty($paramGroup)) {
            $request->dBParameterGroupName = $paramGroup->dBParameterGroupName;
        }

        $optionList = $aws->rds->optionGroup->describe();

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $this->getParam('OptionGroupName')) {
                $optionGroup = $option;
                break;
            }
        }

        if (!empty($optionGroup)) {
            $request->optionGroupName = $optionGroup->optionGroupName;
        }

        $dbSgIds = null;

        foreach ($DBSecurityGroups as $DBSecurityGroup) {
            $dbSgIds[] = $DBSecurityGroup;
        }

        $request->dBSecurityGroups = $dbSgIds;
        $request->autoMinorVersionUpgrade = $this->getParam('AutoMinorVersionUpgrade') == 'false' ? false : true;
        $request->preferredMaintenanceWindow = $this->getParam('PreferredMaintenanceWindow') ?: null;
        $request->masterUserPassword = $this->getParam('MasterUserPassword') != '' ? $this->getParam('MasterUserPassword') : null;
        $request->allocatedStorage = $this->getParam('AllocatedStorage');
        $request->dBInstanceClass = $this->getParam('DBInstanceClass');
        $request->backupRetentionPeriod = $this->getParam('BackupRetentionPeriod') ?: null;
        $request->preferredBackupWindow = $this->getParam('PreferredBackupWindow') ?: null;
        $request->applyImmediately = $this->getParam('ApplyImmediately') == 'false' ? false : true;

        $multiAz = $this->getParam('MultiAZ');

        if (!empty($multiAz)) {
            $request->multiAZ = $this->getParam('MultiAZ') == 'false' ? false : true;
        }

        $request->storageType = $this->getParam('StorageType');
        $request->licenseModel = $this->getParam('LicenseModel');
        $request->allowMajorVersionUpgrade = $this->getParam('AllowMajorVersionUpgrade') == 'false' ? false : true;

        $vpcSgIds = null;

        foreach ($VpcSecurityGroups as $VpcSecurityGroup) {
            $vpcSgIds[] = $VpcSecurityGroup['id'];
        }

        $request->vpcSecurityGroupIds = $vpcSgIds;
        $request->engineVersion = $this->getParam('EngineVersion') ?: null;
        $request->iops = $this->getParam('Iops') ?: null;

        $ignoreGovernance = $this->getParam('ignoreGovernance');

        if (!$ignoreGovernance) {
            $errorMessage = $this->checkPolicy($VpcSecurityGroups);
        }

        if (empty($errorMessage)) {
            $aws->rds->dbInstance->modify($request);
            $this->response->success("DB Instance successfully modified");
        } else {
            $this->response->failure($errorMessage);
        }
    }

    /**
     * @param JsonData $VpcSecurityGroups
     * @param JsonData $DBSecurityGroups
     * @param JsonData $SubnetIds
     */
    public function xLaunchInstanceAction(JsonData $VpcSecurityGroups = null, JsonData $DBSecurityGroups = null, JsonData $SubnetIds = null)
    {
        $cloudLocation = $this->getParam('cloudLocation');

        $aws = $this->getEnvironment()->aws($cloudLocation);

        $engine = $this->getParam('Engine');

        if ($engine == 'mysql') {
            $engine = 'MySQL';
        }

        $request = new CreateDBInstanceRequestData(
            $this->getParam('DBInstanceIdentifier'),
            $this->getParam('AllocatedStorage'),
            $this->getParam('DBInstanceClass'),
            $engine,
            $this->getParam('MasterUsername'),
            $this->getParam('MasterUserPassword')
        );

        $request->port = $this->getParam('Port') ?: null;
        $request->dBName = $this->getParam('DBName') ?: null;
        $request->characterSetName = $this->getParam('CharacterSetName') ?: null;

        $paramName = $this->getParam('DBParameterGroup');

        if (!empty($paramName)) {
            $paramGroups = $aws->rds->dbParameterGroup->describe();

            foreach ($paramGroups as $param) {
                /* @var $param DBParameterGroupData */
                if ($param->dBParameterGroupName == $paramName) {
                    $paramGroup = $param;
                    break;
                }
            }
        }

        if (!empty($paramGroup)) {
            $request->dBParameterGroupName = $paramGroup->dBParameterGroupName;
        }

        $optionList = $aws->rds->optionGroup->describe($engine);

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $this->getParam('OptionGroupName')) {
                $optionGroup = $option;
                break;
            }
        }

        if (!empty($optionGroup)) {
            $request->optionGroupName = $optionGroup->optionGroupName;
        }

        $dbSgIds = null;

        foreach ($DBSecurityGroups as $DBSecurityGroup) {
            $dbSgIds[] = $DBSecurityGroup;
        }

        $request->dBSecurityGroups = $dbSgIds;
        $request->autoMinorVersionUpgrade = $this->getParam('AutoMinorVersionUpgrade') == 'false' ? false : true;
        $request->availabilityZone = $this->getParam('AvailabilityZone') ?: null;
        $request->backupRetentionPeriod = $this->getParam('BackupRetentionPeriod') ?: null;
        $request->preferredBackupWindow = $this->getParam('PreferredBackupWindow') ?: null;
        $request->preferredMaintenanceWindow = $this->getParam('PreferredMaintenanceWindow') ?: null;

        $multiAz = $this->getParam('MultiAZ');

        if (!empty($multiAz)) {
            $request->multiAZ = $this->getParam('MultiAZ') == 'false' ? false : true;
        }

        $request->storageType = $this->getParam('StorageType');
        $request->dBSubnetGroupName = $this->getParam('DBSubnetGroupName') ?: null;
        $request->licenseModel = $this->getParam('LicenseModel');

        $vpcSgIds = null;

        foreach ($VpcSecurityGroups as $VpcSecurityGroup) {
            $vpcSgIds[] = $VpcSecurityGroup['id'];
        }

        $request->vpcSecurityGroupIds = $vpcSgIds;
        $request->engineVersion = $this->getParam('EngineVersion') ?: null;
        $request->iops = $this->getParam('Iops') ?: null;
        $vpcId = $this->getParam('VpcId');

        $tagsObject = ($this->getParam('farmId')) ? DBFarm::LoadByID($this->getParam('farmId')) : $this->environment;

        $tags = [
            ['key' => \Scalr_Governance::SCALR_META_TAG_NAME, 'value' => $tagsObject->applyGlobalVarsToValue(\Scalr_Governance::SCALR_META_TAG_VALUE)]
        ];

        $governance = new \Scalr_Governance($this->environment->id);
        $gTags = (array) $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_TAGS);

        if (count($gTags) > 0) {
            foreach ($gTags as $tKey => $tValue)
                $tags[] = array('key' => $tKey, 'value' => $tagsObject->applyGlobalVarsToValue($tValue));
        }
        $request->tags = new TagsList($tags);

        $errorMessage = $this->checkPolicy($VpcSecurityGroups, $vpcId, $SubnetIds, $cloudLocation);

        if (empty($errorMessage)) {
            $aws->rds->dbInstance->create($request);

            if ($this->getParam('farmId')) {
                $cloudResource = new CloudResource();
                $cloudResource->id = $request->dBInstanceIdentifier;
                $cloudResource->type = CloudResource::TYPE_AWS_RDS;
                $cloudResource->platform = \SERVER_PLATFORMS::EC2;
                $cloudResource->cloudLocation = $cloudLocation;
                $cloudResource->envId = $this->getEnvironmentId();
                $cloudResource->farmId = $this->getParam('farmId');
                $cloudResource->save();
            }

            $this->response->success("DB Instance successfully created");
        } else {
            $this->response->failure($errorMessage);
        }
    }

    /**
     * @param JsonData $dbInstancesIds
     * @param string $cloudLocation
     */
    public function xRebootAction(JsonData $dbInstancesIds, $cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        foreach ($dbInstancesIds as $dbInstancesId) {
            $aws->rds->dbInstance->reboot($dbInstancesId);
        }

        $this->response->success();
    }

    /**
     * @param JsonData $dbInstancesIds
     * @param string $cloudLocation
     */
    public function xTerminateAction(JsonData $dbInstancesIds, $cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        foreach ($dbInstancesIds as $dbInstancesId) {
            $aws->rds->dbInstance->delete($dbInstancesId, true);
        }

        $cloudResource = CloudResource::findPk(
            $this->getParam('instanceId'),
            $this->getEnvironmentId(),
            \SERVER_PLATFORMS::EC2,
            $this->getParam('cloudLocation')
        );

        if ($cloudResource) {
            $cloudResource->delete();
        }

        $this->response->success();
    }

    public function xGetParametersAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $sgroups = $aws->rds->dbSecurityGroup->describe();
        $azlist = $aws->ec2->availabilityZone->describe();

        $zones = [];

        foreach ($azlist as $az) {
            /* @var $az \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
            if (stristr($az->zoneState, 'available')) {
                $zones[] = array(
                    'id'   => $az->zoneName,
                    'name' => $az->zoneName,
                );
            }
        }

        $sgroup = [];

        foreach ($sgroups as $group) {
            /* @var $group \Scalr\Service\Aws\Rds\DataType\DBSecurityGroupData */
            if (stristr($group->dBSecurityGroupName, 'default')) {
                $sgroup[] = $group->toArray();
                break;
            }
        }

        $this->response->data(array(
            'sgroups' => $sgroup,
            'zones'   => $zones,
        ));
    }

    /**
     * xListInstancesAction
     *
     * @param string $cloudLocation  DB Instance region
     * @throws Scalr_Exception_Core
     */
    public function xListInstancesAction($cloudLocation)
    {
        $this->request->defineParams([
            'sort' => ['type' => 'json', 'default' => ['property' => 'id', 'direction' => 'ASC']]
        ]);

        $aws = $this->getEnvironment()->aws($cloudLocation);

        $rows = $aws->rds->dbInstance->describe();

        $data = [];

        foreach ($rows as $dbInstance) {
            /* @var $dbInstance \Scalr\Service\Aws\Rds\DataType\DBInstanceData */
            $data[] = array(
                'engine'        => (string)$dbInstance->engine,
                'status'        => (string)$dbInstance->dBInstanceStatus,
                'hostname'      => (isset($dbInstance->endpoint) ? (string)$dbInstance->endpoint->address : ''),
                'port'          => (isset($dbInstance->endpoint) ? (string)$dbInstance->endpoint->port : ''),
                'name'          => (string)$dbInstance->dBInstanceIdentifier,
                'username'      => (string)$dbInstance->masterUsername,
                'type'          => (string)$dbInstance->dBInstanceClass,
                'storage'       => (string)$dbInstance->allocatedStorage,
                'dtadded'       => $dbInstance->instanceCreateTime,
                'avail_zone'    => (string)$dbInstance->availabilityZone,
                'engineVersion' => $dbInstance->engineVersion,
                'multiAz'       => $dbInstance->multiAZ,
                'isReplica'     => !empty($dbInstance->readReplicaSourceDBInstanceIdentifier) ? 1 : 0
            );
        }

        $response = $this->buildResponseFromData($data, ['name']);

        foreach ($response['data'] as &$row) {
            $row['dtadded'] = $row['dtadded'] ? Scalr_Util_DateTime::convertTz($row['dtadded']) : '';

            $cloudResource = CloudResource::findPk(
                $row['name'],
                $this->getEnvironmentId(),
                \SERVER_PLATFORMS::EC2,
                $cloudLocation
            );
            if ($cloudResource) {
                $row['farmId'] = $cloudResource->farmId;
                $row['farmName'] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", [$cloudResource->farmId]);
            }

        }
        $this->response->data($response);
    }

    public function restoreAction($snapshot, $cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $azlist = $aws->ec2->availabilityZone->describe();
        $zones = array();
        /* @var $az \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
        foreach ($azlist as $az) {
            if (stristr($az->zoneState, 'available')) {
                $zones[] = array(
                    'id'   => $az->zoneName,
                    'name' => $az->zoneName,
                );
            }
        }

        $dbSnapshot = $aws->rds->dbSnapshot->describe(null, $snapshot)->get(0)->toArray(true);

        unset($dbSnapshot['DBInstanceIdentifier']);

        $this->response->page('ui/tools/aws/rds/instances/restore.js', [
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'zones'     => $zones,
            'snapshot'  => $dbSnapshot
        ]);
    }

    /**
     * @param JsonData $SubnetIds
     */
    public function xRestoreInstanceAction(JsonData $SubnetIds = null)
    {
        $cloudLocation = $this->getParam('cloudLocation');
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $request = new RestoreDBInstanceFromDBSnapshotRequestData(
            $this->getParam('DBInstanceIdentifier'),
            $this->getParam('DBSnapshotIdentifier')
        );

        $engine = $this->getParam('Engine');

        $optionList = $aws->rds->optionGroup->describe($engine);

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $this->getParam('OptionGroupName')) {
                $optionGroup = $option;
                break;
            }
        }

        if (!empty($optionGroup)) {
            $request->optionGroupName = $optionGroup->optionGroupName;
        }

        $request->dBInstanceClass = $this->getParam('DBInstanceClass') ?: null;
        $request->port = $this->getParam('Port') ?: null;
        $request->availabilityZone = $this->getParam('AvailabilityZone') ?: null;

        $multiAz = $this->getParam('MultiAZ');

        if (!empty($multiAz)) {
            $request->multiAZ = $this->getParam('MultiAZ') == 'false' ? false : true;
        }

        $request->autoMinorVersionUpgrade = $this->getParam('AutoMinorVersionUpgrade') == 'false' ? false : true;
        $request->storageType = $this->getParam('StorageType');
        $request->dBSubnetGroupName = $this->getParam('DBSubnetGroupName') ?: null;
        $request->licenseModel = $this->getParam('LicenseModel');
        $request->engine = $engine;
        $request->iops = $this->getParam('Iops') ?: null;
        $request->dBName = $this->getParam('DBName') ?: null;

        $vpcId = $this->getParam('VpcId');

        $errorMessage = $this->checkPolicy(null, $vpcId, $SubnetIds, $cloudLocation);

        if (empty($errorMessage)) {
            $aws->rds->dbInstance->restoreFromSnapshot($request);
            $this->response->success("DB Instance successfully restore from Snapshot");
        } else {
            $this->response->failure($errorMessage);
        }
    }

    /**
     * xGetSubnetGroupAction
     * Gets a list of subnet groups
     *
     * @param string $cloudLocation
     * @param string $vpcId
     */
    public function xGetSubnetGroupAction($cloudLocation, $vpcId)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);
        $marker = null;

        do {
            if (isset($groups)) {
                $marker = $groups->marker;
            }

            $groups = $aws->rds->dbSubnetGroup->describe(null, $marker);

            foreach ($groups as $group) {
                /* @var $group \Scalr\Service\Aws\Rds\DataType\DBSubnetGroupData */
                if ($group->vpcId !== $vpcId) {
                    continue;
                }

                $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);

                $ec2Subnets = $platform->listSubnets(
                    $this->getEnvironment(),
                    $cloudLocation,
                    $vpcId
                );

                $subnetGroup = $group->toArray();

                $subnetGroup['subnets'] = array_map(function (&$subnet) use ($ec2Subnets) {
                    foreach ($ec2Subnets as $ec2Subnet) {
                        if ($ec2Subnet['id'] == $subnet['subnetIdentifier']) {
                            $subnet['type'] = $ec2Subnet['type'];

                            return $subnet;
                        }
                    }
                }, $subnetGroup['subnets']);

                $result[] = $subnetGroup;
            }
        } while ($groups->marker !== null);

        $this->response->data(['subnetGroups' => $result]);
    }

    /**
     * Creates new subnet group
     *
     * @param string    $dbSubnetGroupName
     * @param string    $dbSubnetGroupDescription
     * @param string    $cloudLocation
     * @param JsonData  $subnets
     */
    public function xCreateSubnetGroupAction($dbSubnetGroupName, $dbSubnetGroupDescription, $cloudLocation, JsonData $subnets)
    {
        $request = new CreateDBSubnetGroupRequestData($dbSubnetGroupDescription, $dbSubnetGroupName);

        foreach ($subnets as $subnet) {
            $subnetArr[] = $subnet;
        }

        $request->subnetIds = $subnetArr;

        $subnetGroup = $this->getEnvironment()->aws($cloudLocation)->rds->dbSubnetGroup->create($request)->toArray();

        $this->response->success("DB subnet group successfully created");
        $this->response->data(['subnetGroup' => $subnetGroup]);
    }

    /**
     * Gets a list of engine versions of a specific engine
     *
     * @param string $cloudLocation
     * @param string $engine
     */
    public function xGetEngineVersionsAction($cloudLocation, $engine = null)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $request = new DescribeDBEngineVersionsData();
        $request->engine = $engine;

        $versions = $aws->rds->describeDBEngineVersions($request);
        $engineVersions = [];

        foreach ($versions as $version) {
            /* @var $version \Scalr\Service\Aws\Rds\DataType\DBEngineVersionData */
            $engineVersions[] = [$version->engineVersion];
        }

        $this->response->data(['engineVersions' => $engineVersions]);
    }

    public function createSubnetGroupAction()
    {
        $this->response->page('ui/tools/aws/rds/instances/createSubnetGroup.js', array(
            'cloudLocation'     => $this->getParam('cloudLocation'),
            'vpcId'             => $this->getParam('vpcId')
        ));
    }

    /**
     * xGetOptionGroupsAction
     *
     * @param string $cloudLocation
     * @param string $engine
     * @param string $engineVersion
     * @param bool   $multiAz
     */
    public function xGetOptionGroupsAction($cloudLocation, $engine, $engineVersion, $multiAz = null)
    {
        $majorVersion = null;

        $mirroringEngines = ['sqlserver-se', 'sqlserver-ee'];
        $isMirror = ($multiAz && in_array($engine, $mirroringEngines));

        $aws = $this->getEnvironment()->aws($cloudLocation);

        $arr = explode('.', $engineVersion);
        $majorVersion = implode('.', [$arr[0], $arr[1]]);

        $optionGroups = $aws->rds->optionGroup->describe($engine, $majorVersion);
        $default = [];
        $resultOptionGroups = [];

        foreach ($optionGroups as $optionGroup) {
            /* @var $optionGroup \Scalr\Service\Aws\Rds\DataType\OptionGroupData */
            if (strpos($optionGroup->optionGroupName, 'default:') === 0) {
                if ($isMirror) {
                    $default = $optionGroup->toArray();
                }
            }

            if ($isMirror) {
                foreach ($optionGroup->options as $option) {
                    /* @var $option Scalr\Service\Aws\Rds\DataType\OptionData */
                    if ($option->optionName == 'Mirroring') {
                        $resultOptionGroups[] = $optionGroup->toArray();
                    }
                }
            } else {
                $resultOptionGroups[] = $optionGroup->toArray();
            }
        }

        $defaultName = 'default:' . $engine . '-' . str_replace('.', '-', $majorVersion);

        if ($isMirror) {
            $defaultName .= '-mirroring';
        }

        if (count($resultOptionGroups) == 0) {
            $resultOptionGroups[] = ['optionGroupName' => $defaultName];
            $default['optionGroupName'] = $defaultName;
        }

        if (empty($default)) {
            $default['optionGroupName'] = $defaultName;
        }

        $this->response->data([
            'optionGroups' => $resultOptionGroups,
            'defaultOptionGroupName' => isset($default['optionGroupName']) ? $default['optionGroupName'] : null,
        ]);
    }

    /**
     * xGetParameterGroupAction
     *
     * @param string $cloudLocation
     * @param string $engine
     * @param string $engineVersion
     */
    public function xGetParameterGroupAction($cloudLocation, $engine, $engineVersion)
    {
        $paramGroup = null;

        $aws = $this->getEnvironment()->aws($cloudLocation);

        $arr = explode('.', $engineVersion);
        $majorVersion = implode('.', [$arr[0], $arr[1]]);
        $paramGroupName = 'default.' . strtolower($engine);
        $delimiter = ($engine == 'mysql' || $engine == 'postgres') ? '' : '-';
        $paramGroups = $aws->rds->dbParameterGroup->describe();

        $groups = [];

        foreach ($paramGroups as $group) {
            /* @var $group \Scalr\Service\Aws\Rds\DataType\DBParameterGroupData */
            if ($group->dBParameterGroupName == $paramGroupName . $delimiter . $majorVersion) {
                $paramGroup = $group->dBParameterGroupName;
            }

            if (strpos($group->dBParameterGroupName, $paramGroupName . $delimiter . $majorVersion) === 0) {
                $groups[] = $group->toArray();
            } else if ($group->dBParameterGroupFamily == $engine . $delimiter . $majorVersion) {
                $groups[] = $group->toArray();
            }
        }

        $defaultName = 'default.' . $engine . $delimiter . $majorVersion;

        if (empty($groups)) {
            $groups[] = ['dBParameterGroupName' => $defaultName];
        }

        if (empty($paramGroup)) {
            $paramGroup = $defaultName;
        }

        $this->response->data([
            'default' => $paramGroup,
            'groups'  => $groups
        ]);
    }

    /**
     * xGetAvailabilityZonesAction
     *
     * @param string $cloudLocation
     */
    public function xGetAvailabilityZonesAction($cloudLocation)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $azlist = $aws->ec2->availabilityZone->describe();

        $zones = [];

        foreach ($azlist as $az) {
            /* @var $az \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
            if (stristr($az->zoneState, 'available')) {
                $zones[] = array(
                    'id'   => $az->zoneName,
                    'name' => $az->zoneName,
                );
            }
        }

        $this->response->data(['zones'   => $zones]);
    }

    /**
     * xGetInstanceTypesAction
     *
     * @param string $cloudLocation
     * @param string $engine
     * @param string $engineVersion optional
     * @param string $licenseModel  optional
     */
    public function xGetInstanceTypesAction($cloudLocation, $engine, $engineVersion = null, $licenseModel = null)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $request = new DescribeOrderableDBInstanceOptionsData($engine);
        $request->engineVersion = $engineVersion;
        $request->licenseModel = $licenseModel;

        $marker = null;
        $instanceTypes = [];
        $instanceTypeNames = [];

        do {
            if (isset($instanceTypeList)) {
                $marker = $instanceTypeList->marker;
            }

            $instanceTypeList = $aws->rds->dbInstance->describeTypes($request, $marker);

            foreach ($instanceTypeList as $instanceType) {
                /* @var $instanceType OrderableDBInstanceOptionsData */
                if (!in_array($instanceType->dBInstanceClass, $instanceTypeNames)) {
                    $instanceTypeNames[] = $instanceType->dBInstanceClass;

                    $instanceTypes[] = [
                        'dBInstanceClass' => $instanceType->dBInstanceClass,
                        'multiAZCapable'  => $instanceType->multiAZCapable,
                        'supportsIops'    => $instanceType->supportsIops,
                        'vpc'             => $instanceType->vpc
                    ];
                }
            }
        } while ($instanceTypeList->marker !== null);

        $this->response->data(['instanceTypes' => array_reverse($instanceTypes)]);
    }

    /**
     * Checks request data
     *
     * @param JsonData   $vpcSecurityGroups
     * @param string     $vpcId
     * @param JsonData   $subnetIds
     * @param string     $cloudLocation
     * @return bool|string Returns error message if access to some data restricted. False otherwise.
     * @throws Scalr_Exception_Core
     */
    private function checkPolicy($vpcSecurityGroups = null, $vpcId = null, $subnetIds = null, $cloudLocation = null)
    {
        $governance = new Scalr_Governance($this->getEnvironmentId());
        $values = $governance->getValues(true);

        if (!empty($values[SERVER_PLATFORMS::EC2][Scalr_Governance::AWS_VPC]->value)) {
            if (!empty($cloudLocation) && !array_key_exists($cloudLocation, (array) $values[SERVER_PLATFORMS::EC2][Scalr_Governance::AWS_VPC]->regions)) {
                return sprintf("A Vpc Policy is active in this Environment, access to %s region has been restricted by account owner.", $cloudLocation);
            }

            if (!empty($vpcId)) {
                foreach ($values[SERVER_PLATFORMS::EC2][Scalr_Governance::AWS_VPC]->regions as $region => $policy) {
                    if (!empty($policy->ids) && !empty($cloudLocation) && $cloudLocation == $region && !in_array($vpcId, (array) $policy->ids)) {
                        return sprintf("A Vpc Policy is active in this Environment, access to vpc %s has been restricted by account owner.", $vpcId);
                    }
                }

                foreach ($values[SERVER_PLATFORMS::EC2][Scalr_Governance::AWS_VPC]->ids as $vpc => $restrictions) {
                    $subnetIds = (array) $subnetIds;
                    $missingSubnets = array_diff($subnetIds, $restrictions);
                    $s = count($missingSubnets) > 1 ? 's' : '';

                    if (!empty($restrictions) && is_array($restrictions) && $vpc == $vpcId && !empty($missingSubnets)) {
                        return sprintf("A Vpc Policy is active in this Environment, access to subnet%s %s has been restricted by account owner.",
                            $s, implode(', ', $missingSubnets)
                        );
                    }
                }
            }
        }

        if (!empty($vpcSecurityGroups)) {
            foreach ($vpcSecurityGroups as $vpcSecurityGroup) {
                if (empty($vpcSecurityGroup['id'])) {
                    $notFoundGroups[] = $vpcSecurityGroup['name'];
                }
                $vpcSecurityGroupNames[] = $vpcSecurityGroup['name'];
            }
        }

        if (!empty($values[SERVER_PLATFORMS::EC2][Scalr_Governance::AWS_SECURITY_GROUPS]->value) && !empty($vpcSecurityGroupNames)) {
            if (!empty($notFoundGroups)) {
                $s = count($notFoundGroups) > 1 ? 's' : '';
                $es = $s ? '' : "e$s";
                $they = $s ? "they" : 'it';

                return sprintf("A Security Group Policy is active in this Environment, and requires that you attach the following Security Group%s to your DB instance: %s, but %s do%s not exist in current Vpc.",
                    $s, implode(', ', $notFoundGroups), $they, $es
                );
            }

            $sgDefaultNames = explode(',', $values[SERVER_PLATFORMS::EC2][Scalr_Governance::AWS_SECURITY_GROUPS]->value);

            if ($missingGroups = array_diff($sgDefaultNames, $vpcSecurityGroupNames)) {
                return sprintf("A Security Group Policy is active in this Environment, and requires that you attach the following Security Groups to your DB instance: %s", implode(', ', $missingGroups));
            }

            sort($sgDefaultNames);
            sort($vpcSecurityGroupNames);

            if (empty($values[SERVER_PLATFORMS::EC2][Scalr_Governance::AWS_SECURITY_GROUPS]->allow_additional_sec_groups) && $sgDefaultNames != $vpcSecurityGroupNames) {
                return sprintf("A Security Group Policy is active in this Environment, and you can't apply additional security groups to your DB instance.");
            }
        }

        return false;
    }

    /**
     * xGetDbInstanceDataAction
     *
     * @param string $cloudLocation     DB Instance region
     * @param string $dbInstanceName    DB Instance name
     */
    public function xGetDbInstanceDataAction($cloudLocation, $dbInstanceName)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        /* @var $dbinstance \Scalr\Service\Aws\Rds\DataType\DBInstanceData */
        $dbinstance = $aws->rds->dbInstance->describe($dbInstanceName)->get(0);

        $createdTime = $dbinstance->instanceCreateTime;

        $dbinstance = $dbinstance->toArray(true);

        $vpcSglist = $aws->ec2->securityGroup->describe();

        foreach ($dbinstance['VpcSecurityGroups'] as &$vpcSg) {
            foreach ($vpcSglist as $vpcSqData) {
                /* @var $vpcSqData \Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
                $vpcSecurityGroupName = null;

                if ($vpcSqData->groupId == $vpcSg['VpcSecurityGroupId']) {
                    $vpcSecurityGroupName = $vpcSqData->groupName;
                    break;
                }
            }

            $vpcSg = [
                'vpcSecurityGroupId'   => $vpcSg['VpcSecurityGroupId'],
                'vpcSecurityGroupName' => $vpcSecurityGroupName
            ];
        }

        $dbinstance['DBSubnetGroupName'] = isset($dbinstance['DBSubnetGroup']['DBSubnetGroupName']) ? $dbinstance['DBSubnetGroup']['DBSubnetGroupName'] : null;

        foreach ($dbinstance['DBSecurityGroups'] as &$dbSg) {
            $dbSg = $dbSg['DBSecurityGroupName'];
        }

        foreach ($dbinstance['OptionGroupMembership'] as &$member) {
            $dbinstance['OptionGroupName'] = $member['OptionGroupName'];
            break;
        }

        foreach ($dbinstance['DBParameterGroups'] as &$param) {
            $dbinstance['DBParameterGroup'] = $param['DBParameterGroupName'];
            break;
        }

        $dbinstance['Address'] = $dbinstance['Endpoint']['Address'];
        $dbinstance['EngineVersion'] = isset($dbinstance['PendingModifiedValues']) && !empty($dbinstance['PendingModifiedValues']['EngineVersion']) ? $dbinstance['EngineVersion']. ' <i><font color="red">New value (' . $dbinstance['PendingModifiedValues']['EngineVersion'] . ') is pending</font></i>' : $dbinstance['EngineVersion'];
        $dbinstance['Port'] = isset($dbinstance['PendingModifiedValues']) && !empty($dbinstance['PendingModifiedValues']['Port']) ?
            (string) $dbinstance['Endpoint']['Port'] . ' <i><font color="red">New value (' . $dbinstance['PendingModifiedValues']['Port'] . ') is pending</font></i>' : (string)$dbinstance['Endpoint']['Port'];
        $dbinstance['InstanceCreateTime'] = Scalr_Util_DateTime::convertTz($createdTime);
        $dbinstance['MultiAZ'] = ($dbinstance['MultiAZ'] ? 'Enabled' : 'Disabled') .
            (isset($dbinstance['PendingModifiedValues']) && isset($dbinstance['PendingModifiedValues']['MultiAZ']) ?
                ' <i><font color="red">New value(' . ($dbinstance['PendingModifiedValues']['MultiAZ'] ? 'true' : 'false') . ') is pending</font></i>' : '');
        $dbinstance['DBInstanceClass'] = isset($dbinstance['PendingModifiedValues']) && $dbinstance['PendingModifiedValues']['DBInstanceClass'] ?
            $dbinstance['DBInstanceClass'] . ' <i><font color="red">New value ('. $dbinstance['PendingModifiedValues']['DBInstanceClass'].') is pending</font></i>' : $dbinstance['DBInstanceClass'];
        $dbinstance['AllocatedStorage'] = isset($dbinstance['PendingModifiedValues']) && $dbinstance['PendingModifiedValues']['AllocatedStorage'] ? (string) $dbinstance['AllocatedStorage'] . ' GB' . ' <i><font color="red">New value (' . $dbinstance['PendingModifiedValues']['AllocatedStorage'] . ') is pending</font></i>' : (string) $dbinstance['AllocatedStorage'];
        $dbinstance['BackupRetentionPeriod'] = isset($dbinstance['PendingModifiedValues']) && !empty($dbinstance['PendingModifiedValues']['BackupRetentionPeriod']) ?
            $dbinstance['PendingModifiedValues']['BackupRetentionPeriod']. ' <i><font color="red">(Pending Modified)</font></i>' : $dbinstance['BackupRetentionPeriod'];
        $dbinstance['isReplica'] = !empty($dbinstance['ReadReplicaSourceDBInstanceIdentifier']) ? 1 : 0;

        $this->response->data(array(
            'instance' => $dbinstance
        ));
    }
}
