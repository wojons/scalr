<?php

use Scalr\Exception\ScalrException;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupList;
use Scalr\Service\Aws\Rds\DataType\ClusterMemberData;
use Scalr\Service\Aws\Rds\DataType\CreateDBInstanceReadReplicaData;
use Scalr\Service\Aws\Rds\DataType\CreateDBInstanceRequestData;
use Scalr\Service\Aws\Rds\DataType\CreateDBSubnetGroupRequestData;
use Scalr\Service\Aws\Rds\DataType\DBClusterData;
use Scalr\Service\Aws\Rds\DataType\DBClusterList;
use Scalr\Service\Aws\Rds\DataType\DBInstanceData;
use Scalr\Service\Aws\Rds\DataType\DBParameterGroupData;
use Scalr\Service\Aws\Rds\DataType\DescribeDBEngineVersionsData;
use Scalr\Service\Aws\Rds\DataType\DescribeOrderableDBInstanceOptionsData;
use Scalr\Service\Aws\Rds\DataType\ModifyDBInstanceRequestData;
use Scalr\Service\Aws\Rds\DataType\OptionGroupData;
use Scalr\Service\Aws\Rds\DataType\RestoreDBInstanceFromDBSnapshotRequestData;
use Scalr\Service\Aws\Rds\DataType\OrderableDBInstanceOptionsData;
use Scalr\Service\Aws\Rds\DataType\TagsList;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\RawData;
use Scalr\Acl\Acl;
use Scalr\Model\Entity\CloudResource;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Tools_Aws_Rds_Instances extends Scalr_UI_Controller
{
    /**
     * Param name in url.
     */
    const CALL_PARAM_NAME = 'instanceId';

    /**
     * {@inheritdoc}
     * @see Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_RDS);
    }

    /**
     * Forwards the controller to the default action
     */
    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * Gets AWS Client for the current environment
     *
     * @param  string $cloudLocation Cloud location
     * @return \Scalr\Service\Aws Returns Aws client for current environment
     */
    protected function getAwsClient($cloudLocation)
    {
        return $this->environment->aws($cloudLocation);
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/rds/instances/view.js', []);
    }

    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->response->page(['ui/tools/aws/rds/instances/create.js', 'ui/security/groups/sgeditor.js'], [
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'accountId'     => $this->environment->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress' => $this->request->getRemoteAddr(),
            'farms'         => self::loadController('Farms')->getList()
        ]);
    }

    /**
     * Edit action
     *
     * @param string $cloudLocation Cloud location
     * @param string $instanceId Instance identifier
     * @param string $vpcId optional Vpc id
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Exception
     */
    public function editAction($cloudLocation, $instanceId, $vpcId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $dbinstance = $aws->rds->dbInstance->describe($instanceId)->get(0);

        if (empty($dbinstance)) {
            throw new Exception(sprintf('Db instance with name %s was not found.', $instanceId));
        }

        $vpcSglist = null;

        if (!empty($vpcId)) {
            $filter[] = [
                'name'  => SecurityGroupFilterNameType::vpcId(),
                'value' => $vpcId
            ];

            $vpcSglist = $aws->ec2->securityGroup->describe(null, null, $filter);
        }

        $dbInstanceData = $this->getDbInstanceData($aws, $dbinstance, $vpcSglist);

        $this->response->page([ 'ui/tools/aws/rds/instances/edit.js', 'ui/security/groups/sgeditor.js' ], [
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'instance'      => $dbInstanceData,
            'accountId'     => $this->environment->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress' => $this->request->getRemoteAddr()
        ]);
    }

    /**
     *
     * @param string $cloudLocation
     * @param string $instanceId
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws Exception
     */
    public function createReadReplicaAction($cloudLocation, $instanceId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $dbInstance = $this->getAwsClient($cloudLocation)->rds->dbInstance->describe($instanceId)->get(0);

        if (empty($dbInstance)) {
            throw new Exception(sprintf('Db instance with name %s was not found.', $instanceId));
        }

        /* @var $dbInstance DBInstanceData */
        $dbInstance = $dbInstance->toArray(true);

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

        $this->response->page('ui/tools/aws/rds/instances/createReadReplica.js', [
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'instance'      => $dbInstance
        ]);
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
     * @param string $OptionGroupName            optional
     * @param string $SourceDBInstanceIdentifier optional
     * @param string $DBInstanceClass            optional
     * @param int    $Port                       optional
     * @param bool   $AutoMinorVersionUpgrade    optional
     * @param string $AvailabilityZone           optional
     * @param string $DBSubnetGroupName          optional
     * @param int    $Iops                       optional
     * @param bool   $PubliclyAccessible         optional
     * @param string $StorageType                optional
     */
    public function xSaveReadReplicaAction($cloudLocation, $DBInstanceIdentifier, $OptionGroupName = null,
                                           $SourceDBInstanceIdentifier = null, $DBInstanceClass = null, $Port = null,
                                           $AutoMinorVersionUpgrade = false, $AvailabilityZone = null,
                                           $DBSubnetGroupName = null, $Iops = null, $PubliclyAccessible = null,
                                           $StorageType = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        /* @var $instance DBInstanceData */
        $instance = $aws->rds->dbInstance->describe($SourceDBInstanceIdentifier)->get();

        if (empty($instance->dBClusterIdentifier)) {
            $request = new CreateDBInstanceReadReplicaData($DBInstanceIdentifier, $SourceDBInstanceIdentifier);

            $request->dBInstanceClass            = $DBInstanceClass ?: null;
            $request->sourceDBInstanceIdentifier = $SourceDBInstanceIdentifier ?: null;
            $request->port                       = $Port ?: null;
            $request->storageType                = $StorageType ?: $instance->storageType;
        } else {
            $request = new CreateDBInstanceRequestData($DBInstanceIdentifier, $DBInstanceClass, $instance->engine);

            $request->dBClusterIdentifier  = $instance->dBClusterIdentifier;
            $request->storageEncrypted     = $instance->storageEncrypted;
            $request->kmsKeyId             = $instance->kmsKeyId;
            $request->availabilityZone     = $instance->availabilityZone;
            $request->characterSetName     = $instance->characterSetName;
            $request->dBParameterGroupName = $instance->dBParameterGroups->get()->dBParameterGroupName;

            $optionList = $aws->rds->optionGroup->describe($instance->engine);

            foreach ($optionList as $option) {
                /* @var $option OptionGroupData */
                if ($option->optionGroupName == $OptionGroupName) {
                    $optionGroup = $option;
                    break;
                }
            }

            if (isset($optionGroup)) {
                $request->optionGroupName = $optionGroup->optionGroupName;
            }

            $request->optionGroupName = $instance->optionGroupMembership->get()->optionGroupName;

            $dbSgIds = [];
            foreach ($instance->dBSecurityGroups as $dbSecurityGroup) {
                $dbSgIds[] = $dbSecurityGroup->dBSecurityGroupName;
            }

            $request->dBSecurityGroups = empty($dbSgIds) ? null : $dbSgIds;

            $request->preferredMaintenanceWindow = $instance->preferredMaintenanceWindow;

            if (!empty($instance->multiAZ)) {
                $request->multiAZ = $instance->multiAZ;
            }

            $request->storageType        = 'aurora';
            $request->dBSubnetGroupName  = $instance->dBSubnetGroup->dBSubnetGroupName;
            $request->licenseModel       = $instance->licenseModel;
            $request->engine             = $instance->engine;
            $request->engineVersion      = $instance->engineVersion;
            $request->iops               = $instance->iops;
            $request->publiclyAccessible = $instance->publiclyAccessible;

            $request->tags = $instance->describeTags();
        }

        $request->autoMinorVersionUpgrade = $AutoMinorVersionUpgrade;
        $request->availabilityZone        = $AvailabilityZone ?: $request->availabilityZone;
        $request->dBSubnetGroupName       = $DBSubnetGroupName ?: $request->dBSubnetGroupName;
        $request->iops                    = $Iops ?: $request->iops;
        $request->publiclyAccessible      = $PubliclyAccessible !== null ? $PubliclyAccessible : $request->publiclyAccessible;

        $optionList = $aws->rds->optionGroup->describe();

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $OptionGroupName) {
                $optionGroup = $option;
                break;
            }
        }

        if (isset($optionGroup)) {
            $request->optionGroupName = $optionGroup->optionGroupName;
        }

        if (empty($instance->dBClusterIdentifier)) {
            $instance = $aws->rds->dbInstance->createReplica($request);
        } else {
            $instance = $aws->rds->dbInstance->create($request);
        }

        $clusters = null;

        if (!empty($instance->dBClusterIdentifier)) {
            /* @var $cluster DBClusterData */
            $clusters = $aws->rds->dbCluster->describe($instance->dBClusterIdentifier);
        }

        $data = $this->getDbInstanceData($aws, $instance, null, $clusters);

        $this->response->success("DB Instance Read Replica has been successfully created");
        $this->response->data([
            'instance'      => $data,
            'cloudLocation' => $cloudLocation
        ]);
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
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->getAwsClient($cloudLocation)->rds->dbInstance->promoteReplica($DBInstanceIdentifier, $BackupRetentionPeriod, $PreferredBackupWindow);

        $this->response->success("DB Instance Read Replica has been successfully promoted");
    }

    /**
     * xModifyInstanceAction
     *
     * @param string   $cloudLocation
     * @param string   $StorageType
     * @param string   $DBInstanceClass
     * @param string   $LicenseModel                optional
     * @param string   $DBInstanceIdentifier        optional
     * @param string   $OptionGroupName             optional
     * @param int      $farmId                      optional
     * @param string   $DBParameterGroup            optional
     * @param JsonData $VpcSecurityGroups           optional
     * @param JsonData $DBSecurityGroups            optional
     * @param string   $PreferredMaintenanceWindow  optional
     * @param RawData  $MasterUserPassword          optional
     * @param string   $AllocatedStorage            optional
     * @param string   $BackupRetentionPeriod       optional
     * @param string   $PreferredBackupWindow       optional
     * @param bool     $MultiAZ                     optional
     * @param string   $EngineVersion               optional
     * @param int      $Iops                        optional
     * @param bool     $AutoMinorVersionUpgrade     optional
     * @param bool     $AllowMajorVersionUpgrade    optional
     * @param bool     $ApplyImmediately            optional
     * @param bool     $ignoreGovernance            optional
     * @param string   $VpcId                       optional
     */
    public function xModifyInstanceAction($cloudLocation, $StorageType, $DBInstanceClass, $LicenseModel = null,
                                          $DBInstanceIdentifier = null, $OptionGroupName = null, $farmId = null,
                                          $DBParameterGroup = null, JsonData $VpcSecurityGroups = null,
                                          JsonData $DBSecurityGroups = null, $PreferredMaintenanceWindow = null,
                                          RawData $MasterUserPassword = null, $AllocatedStorage = null,
                                          $BackupRetentionPeriod = null, $PreferredBackupWindow = null,
                                          $MultiAZ = null, $StorageType = null, $EngineVersion = null, $Iops = null,
                                          $AutoMinorVersionUpgrade = false, $AllowMajorVersionUpgrade = true,
                                          $ApplyImmediately = false, $ignoreGovernance = false, $VpcId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        /* @var $instance DBInstanceData */
        $instance = $aws->rds->dbInstance->describe($DBInstanceIdentifier)->get();

        $request = new ModifyDBInstanceRequestData($DBInstanceIdentifier);

        if (!empty($DBParameterGroup)) {
            $paramGroups = $aws->rds->dbParameterGroup->describe();

            foreach ($paramGroups as $param) {
                /* @var $param DBParameterGroupData */
                if ($param->dBParameterGroupName == $DBParameterGroup) {
                    $paramGroup = $param;
                    break;
                }
            }
        }

        if (isset($paramGroup)) {
            $request->dBParameterGroupName = $paramGroup->dBParameterGroupName;
        }

        $dbSgIds = [];
        foreach ($DBSecurityGroups as $DBSecurityGroup) {
            $dbSgIds[] = $DBSecurityGroup;
        }

        $request->dBSecurityGroups = empty($dbSgIds) ? null : $dbSgIds;

        $clusters = null;

        if (empty($instance->dBClusterIdentifier)) {
            $optionList = $aws->rds->optionGroup->describe();

            foreach ($optionList as $option) {
                /* @var $option OptionGroupData */
                if ($option->optionGroupName == $OptionGroupName) {
                    $optionGroup = $option;
                    break;
                }
            }

            if (isset($optionGroup)) {
                $request->optionGroupName = $optionGroup->optionGroupName;
            }

            $request->preferredMaintenanceWindow = $PreferredMaintenanceWindow ?: null;
            $request->masterUserPassword         = (string) $MasterUserPassword ?: null;
            $request->allocatedStorage           = $AllocatedStorage;
            $request->backupRetentionPeriod      = $BackupRetentionPeriod ?: null;
            $request->preferredBackupWindow      = $PreferredBackupWindow ?: null;
            $request->multiAZ                    = $MultiAZ;
            $request->storageType                = $StorageType;
            $request->licenseModel               = $LicenseModel;

            $vpcSgIds = [];
            foreach ($VpcSecurityGroups as $VpcSecurityGroup) {
                $vpcSgIds[] = $VpcSecurityGroup['id'];
            }

            $request->vpcSecurityGroupIds = empty($vpcSgIds) ? null : $vpcSgIds;
            $request->engineVersion       = $EngineVersion ?: null;
            $request->iops                = $Iops ?: null;
        } else {
            /* @var $cluster DBClusterData */
            $clusters = $aws->rds->dbCluster->describe($instance->dBClusterIdentifier);
        }

        $request->autoMinorVersionUpgrade  = $AutoMinorVersionUpgrade;
        $request->allowMajorVersionUpgrade = $AllowMajorVersionUpgrade;
        $request->dBInstanceClass          = $DBInstanceClass;
        $request->applyImmediately         = $ApplyImmediately;

        if (!$ignoreGovernance) {
            $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkSecurityGroupsPolicy($VpcSecurityGroups, Aws::SERVICE_INTERFACE_RDS);
        }

        if (!isset($result) || $result === true) {
            $instance = $aws->rds->dbInstance->modify($request);

            //NOTE: Currently modifying of associated farm is broken in UI
//
//            /* @var $previousCloudResource CloudResource */
//            $previousCloudResource = CloudResource::findOneById($request->dBInstanceIdentifier);
//
//            if (empty($farmId)) {
//                if (!empty($previousCloudResource)) {
//                    $previousCloudResource->delete();
//                }
//            } else if (empty($previousCloudResource) || $previousCloudResource->farmId != $farmId) {
//                $previousCloudResource->delete();
//
//                $cloudResource = new CloudResource();
//                $cloudResource->id            = $request->dBInstanceIdentifier;
//                $cloudResource->type          = CloudResource::TYPE_AWS_RDS;
//                $cloudResource->platform      = \SERVER_PLATFORMS::EC2;
//                $cloudResource->cloudLocation = $cloudLocation;
//                $cloudResource->envId         = $this->getEnvironmentId();
//                $cloudResource->farmId        = $farmId;
//                $cloudResource->save();
//            }

            $vpcSglist = null;

            if (!empty($VpcId)) {
                $filter[] = [
                    'name'  => SecurityGroupFilterNameType::vpcId(),
                    'value' => $VpcId
                ];

                $vpcSglist = $aws->ec2->securityGroup->describe(null, null, $filter);
            }

            $data = $this->getDbInstanceData($aws, $instance, $vpcSglist, $clusters);

            $this->response->success("DB Instance successfully modified");
            $this->response->data([
                'instance'      => $data,
                'cloudLocation' => $cloudLocation
            ]);
        } else {
            $this->response->failure($result);
        }
    }

    /**
     * xLaunchInstanceAction
     *
     * @param string   $cloudLocation
     * @param string   $Engine
     * @param string   $DBInstanceIdentifier
     * @param string   $DBInstanceClass
     * @param string   $MasterUsername
     * @param RawData  $MasterUserPassword
     * @param string   $DBParameterGroup
     * @param string   $LicenseModel                optional
     * @param string   $OptionGroupName             optional
     * @param string   $AllocatedStorage            optional
     * @param string   $StorageType                 optional
     * @param int      $farmId                      optional
     * @param string   $DBName                      optional
     * @param int      $Port                        optional
     * @param string   $VpcId                       optional
     * @param JsonData $VpcSecurityGroups           optional
     * @param JsonData $DBSecurityGroups            optional
     * @param JsonData $SubnetIds                   optional
     * @param bool     $StorageEncrypted            optional
     * @param string   $KmsKeyId                    optional
     * @param string   $PreferredBackupWindow       optional
     * @param string   $CharacterSetName            optional
     * @param bool     $MultiAZ                     optional
     * @param bool     $AutoMinorVersionUpgrade     optional
     * @param string   $AvailabilityZone            optional
     * @param int      $Iops                        optional
     * @param string   $BackupRetentionPeriod       optional
     * @param string   $PreferredMaintenanceWindow  optional
     * @param string   $DBSubnetGroupName           optional
     * @param string   $EngineVersion               optional
     * @param bool     $PubliclyAccessible          optional
     * @throws Exception
     * @throws ScalrException
     */
    public function xLaunchInstanceAction($cloudLocation, $Engine, $DBInstanceIdentifier, $DBInstanceClass,
                                          $MasterUsername, RawData $MasterUserPassword, $DBParameterGroup, $LicenseModel = null,
                                          $OptionGroupName = null, $AllocatedStorage = null, $StorageType= null, $farmId = null,
                                          $DBName = null, $Port = null, $VpcId = null, JsonData $VpcSecurityGroups = null,
                                          JsonData $DBSecurityGroups = null, JsonData $SubnetIds = null,
                                          $StorageEncrypted = false, $KmsKeyId = null, $PreferredBackupWindow = null,
                                          $CharacterSetName = null, $MultiAZ = null, $AutoMinorVersionUpgrade = false,
                                          $AvailabilityZone = null, $Iops = null, $BackupRetentionPeriod = null,
                                          $PreferredMaintenanceWindow = null, $DBSubnetGroupName = null,
                                          $EngineVersion = null, $PubliclyAccessible = false)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        if ($Engine == 'mysql') {
            $Engine = 'MySQL';
        }

        $request = new CreateDBInstanceRequestData(
            $DBInstanceIdentifier,
            $DBInstanceClass,
            $Engine
        );

        if ($Engine == 'aurora') {
            $StorageType = 'aurora';
            $request->dBClusterIdentifier = strtolower($DBInstanceIdentifier);
        }

        if ($StorageEncrypted) {
            $request->storageEncrypted = true;

            if ($KmsKeyId) {
                $kmsKey = $aws->kms->key->describe($KmsKeyId);

                if (!$kmsKey->enabled) {
                    throw new Exception("This KMS Key is disabled, please choose another one.");
                }

                $allowed = true;

                $governance = new Scalr_Governance($this->getEnvironmentId());
                $allowedKeys = $governance->getValue(SERVER_PLATFORMS::EC2, Scalr_Governance::AWS_KMS_KEYS, $cloudLocation);

                if (!empty($allowedKeys)) {
                    $allowed = false;

                    foreach ($allowedKeys['keys'] as $key) {
                        if ($key['id'] == $kmsKey->keyId) {
                            $allowed = true;
                            break;
                        }
                    }
                }

                if (!$allowed) {
                    throw new ScalrException("A KMS Policy is active in this Environment, access to '{$kmsKey->keyId}' has been restricted by account owner.");
                }

                $request->kmsKeyId = $KmsKeyId;
            }
        }

        if (empty($request->dBClusterIdentifier)) {
            $request->allocatedStorage      = $AllocatedStorage;
            $request->masterUsername        = $MasterUsername;
            $request->masterUserPassword    = (string) $MasterUserPassword;
            $request->dBName                = $DBName ?: null;
            $request->port                  = $Port ?: null;
            $request->preferredBackupWindow = $PreferredBackupWindow ?: null;

            $vpcSgIds = [];
            foreach ($VpcSecurityGroups as $VpcSecurityGroup) {
                $vpcSgIds[] = $VpcSecurityGroup['id'];
            }

            $request->vpcSecurityGroupIds   = empty($vpcSgIds) ? null : $vpcSgIds;
        }

        $request->characterSetName          = $CharacterSetName ?: null;

        if (!empty($DBParameterGroup)) {
            $paramGroups = $aws->rds->dbParameterGroup->describe();

            foreach ($paramGroups as $param) {
                /* @var $param DBParameterGroupData */
                if ($param->dBParameterGroupName == $DBParameterGroup) {
                    $paramGroup = $param;
                    break;
                }
            }
        }

        if (!empty($paramGroup)) {
            $request->dBParameterGroupName = $paramGroup->dBParameterGroupName;
        }

        $optionList = $aws->rds->optionGroup->describe($Engine);

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $OptionGroupName) {
                $optionGroup = $option;
                break;
            }
        }

        if (isset($optionGroup)) {
            $request->optionGroupName = $optionGroup->optionGroupName;
        }

        $dbSgIds = [];
        foreach ($DBSecurityGroups as $DBSecurityGroup) {
            $dbSgIds[] = $DBSecurityGroup;
        }

        $request->dBSecurityGroups           = empty($dbSgIds) ? null : $dbSgIds;
        $request->autoMinorVersionUpgrade    = $AutoMinorVersionUpgrade;
        $request->availabilityZone           = $AvailabilityZone ?: null;
        $request->backupRetentionPeriod      = $BackupRetentionPeriod ?: null;
        $request->preferredMaintenanceWindow = $PreferredMaintenanceWindow ?: null;
        $request->multiAZ                    = $MultiAZ;
        $request->storageType                = $StorageType;
        $request->dBSubnetGroupName          = $DBSubnetGroupName ?: null;
        $request->licenseModel               = $LicenseModel;
        $request->engineVersion              = $EngineVersion ?: null;
        $request->iops                       = $Iops ?: null;

        if ($VpcId) {
            $request->publiclyAccessible     = $PubliclyAccessible;
        }

        $tagsObject = $farmId ? DBFarm::LoadByID($farmId) : $this->environment;

        $request->tags = new TagsList($tagsObject->getAwsTags());

        $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkSecurityGroupsPolicy($VpcSecurityGroups, Aws::SERVICE_INTERFACE_RDS);

        if ($result === true) {
            $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkVpcPolicy($VpcId, $SubnetIds, $cloudLocation);
        }

        if ($result === true) {
            if (!empty($request->dBClusterIdentifier)) {
                try {
                    $checkInstance = $aws->rds->dbInstance->describe($request->dBInstanceIdentifier);
                } catch (Exception $e) {
                    $checkInstance = [];
                }

                if (count($checkInstance) > 0) {
                    throw new Exception(sprintf("AWS Error. DB Instance with identifier %s already exists.", $request->dBInstanceIdentifier));
                }

                self::loadController('Clusters', 'Scalr_UI_Controller_Tools_Aws_Rds')->xSaveAction(
                    $cloudLocation, $request->dBClusterIdentifier, $Engine, $MasterUsername,
                    $MasterUserPassword, $VpcId, $Port, $DBName, $request->characterSetName,
                    $request->dBParameterGroupName, $request->optionGroupName, new JsonData([$request->availabilityZone]),
                    $request->backupRetentionPeriod, $PreferredBackupWindow, $request->preferredMaintenanceWindow,
                    $request->dBSubnetGroupName, $request->engineVersion, $farmId, $VpcSecurityGroups, $SubnetIds
                );
            }

            $instance = $aws->rds->dbInstance->create($request);

            CloudResource::deletePk(
                $request->dBInstanceIdentifier,
                CloudResource::TYPE_AWS_RDS,
                $this->getEnvironmentId(),
                \SERVER_PLATFORMS::EC2,
                $cloudLocation
            );

            if ($farmId) {
                $cloudResource = new CloudResource();
                $cloudResource->id            = $request->dBInstanceIdentifier;
                $cloudResource->type          = CloudResource::TYPE_AWS_RDS;
                $cloudResource->platform      = \SERVER_PLATFORMS::EC2;
                $cloudResource->cloudLocation = $cloudLocation;
                $cloudResource->envId         = $this->getEnvironmentId();
                $cloudResource->farmId        = $farmId;
                $cloudResource->save();
            }

            $vpcSglist = null;

            if (!empty($VpcId)) {
                $filter[] = [
                    'name'  => SecurityGroupFilterNameType::vpcId(),
                    'value' => $VpcId
                ];

                $vpcSglist = $aws->ec2->securityGroup->describe(null, null, $filter);
            }

            $clusters = null;

            if (!empty($instance->dBClusterIdentifier)) {
                /* @var $cluster DBClusterData */
                $clusters = $aws->rds->dbCluster->describe($instance->dBClusterIdentifier);
            }

            $data = $this->getDbInstanceData($aws, $instance, $vpcSglist, $clusters);

            $data['isReplica'] = false;

            $this->response->success("DB Instance successfully created");
            $this->response->data([
                'instance'      => $data,
                'cloudLocation' => $cloudLocation
            ]);
        } else {
            $this->response->failure($result);
        }
    }

    /**
     * xRebootAction
     *
     * @param JsonData $dbInstancesIds
     * @param string   $cloudLocation
     */
    public function xRebootAction(JsonData $dbInstancesIds, $cloudLocation)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $processed = [];

        try {
            foreach ($dbInstancesIds as $dbInstancesId) {
                $aws->rds->dbInstance->reboot($dbInstancesId);
                $processed[] = $dbInstancesId;
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
        }

        $data = ['processed' => $processed];

        if (isset($message)) {
            $this->response->failure($message);
        } else {
            $this->response->success();
        }

        $this->response->data($data);
    }

    /**
     * xTerminateAction
     *
     * @param JsonData $dbInstancesIds
     * @param string $cloudLocation
     * @throws ScalrException
     * @throws Scalr_Exception_Core
     * @throws \Scalr\Exception\ModelException
     */
    public function xTerminateAction(JsonData $dbInstancesIds, $cloudLocation)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $clusters = [];
        $processed = [];
        $message = '';

        try {
            foreach ($dbInstancesIds as $dbInstanceInfo) {
                $dbInstanceIdentifier = $dbInstanceInfo['dbInstanceIdentifier'];

                $aws->rds->dbInstance->delete($dbInstanceIdentifier, true);

                $processed[] = $dbInstanceIdentifier;

                $cloudResource = CloudResource::findPk(
                    $dbInstanceIdentifier,
                    CloudResource::TYPE_AWS_RDS,
                    $this->getEnvironmentId(),
                    \SERVER_PLATFORMS::EC2,
                    $cloudLocation
                );

                if ($cloudResource) {
                    $cloudResource->delete();
                }
                //We must remove empty clusters to avoid conflicts in future
                if (isset($dbInstanceInfo['dbClusterIdentifier'])) {
                    $clusters[$dbInstanceInfo['dbClusterIdentifier']][] = $dbInstanceIdentifier;
                }
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
        }
        //To delete empty clusters we need to check all clusters of which instances deleted
        if (!empty($clusters)) {
            /* @var $clustersInfo DBClusterData[] */
            /* @var $instancesInfo DBInstanceData[] */
            //In order to not make excess requests, if necessary we load all clusters and instances at once
            if (count($clusters) > 1) {
                //WARN: memory impact — loads all clusters and instances
                $_clustersInfo = $aws->rds->dbCluster->describe();
                $_instancesInfo = $aws->rds->dbInstance->describe();
            } else {
                $_clustersInfo = $aws->rds->dbCluster->describe(array_keys($clusters)[0]);

                if (count($_clustersInfo->get()->dBClusterMembers) > 1) {
                    $_instancesInfo = $aws->rds->dbInstance->describe();
                } else {
                    //WARN: memory impact — loads all instances
                    $_instancesInfo = $aws->rds->dbInstance->describe($_clustersInfo->get()->dBClusterMembers->get()->dBInstanceIdentifier);
                }
            }
            //just for facilities
            foreach ($_clustersInfo as $clusterInfo) {
                $clustersInfo[$clusterInfo->dBClusterIdentifier] = $clusterInfo;
            }
            unset($_clustersInfo);

            foreach ($_instancesInfo as $instanceInfo) {
                $instancesInfo[$instanceInfo->dBInstanceIdentifier] = $instanceInfo;
            }
            unset($_instancesInfo);

            foreach ($clusters as $clusterId => $deletedInstances) {
                if (empty($clustersInfo[$clusterId])) {
                    \Scalr::getContainer()->logger(__CLASS__)->warn("DB Cluster '{$clusterId}' not found!");
                    continue;
                }

                $cluster = $clustersInfo[$clusterId];
                //Necessary to check status of all instances in each affected cluster, because some of them may be in 'deleting' state
                /* @var $member ClusterMemberData */
                foreach ($cluster->dBClusterMembers as $pos => $member) {
                    if (in_array($member->dBInstanceIdentifier, $deletedInstances) ||
                        (isset($instancesInfo[$member->dBInstanceIdentifier]) && $instancesInfo[$member->dBInstanceIdentifier]->dBInstanceStatus == 'deleting')) {
                        unset($cluster->dBClusterMembers[$pos]);
                    } else if (!isset($instancesInfo[$member->dBInstanceIdentifier])) {
                        \Scalr::getContainer()->logger(__CLASS__)->warn("DB Instance '{$member->dBInstanceIdentifier}' not found!");
                        continue;
                    }
                }

                if (count($cluster->dBClusterMembers) === 0) {
                    try {
                        $cluster->delete(true);
                    } catch (Exception $e) {
                        \Scalr::getContainer()->logger(__CLASS__)->error("Cluster '{$clusterId}' deletion failed: " . $e->getMessage());
                        $exceptions[] = $clusterId;
                    }
                }
            }
        }

        if (!empty($exceptions)) {
            $message .= " Some clusters not deleted: '" . implode("','", $exceptions) . "'";
        }

        $data = ['processed' => $processed];

        if (!empty($message)) {
            $this->response->failure($message);
        } else {
            $this->response->success();
        }

        $this->response->data($data);
    }

    /**
     *
     * @param string $cloudLocation
     */
    public function xGetParametersAction($cloudLocation)
    {
        $aws = $this->getAwsClient($cloudLocation);

        $sgroups = $aws->rds->dbSecurityGroup->describe();
        $azlist  = $aws->ec2->availabilityZone->describe();

        $zones = [];

        foreach ($azlist as $az) {
            /* @var $az \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
            if (stristr($az->zoneState, 'available')) {
                $zones[] = [
                    'id'   => $az->zoneName,
                    'name' => $az->zoneName,
                ];
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

        $this->response->data([
            'sgroups' => $sgroup,
            'zones'   => $zones,
        ]);
    }

    /**
     * xListInstancesAction
     *
     * @param string $cloudLocation                  DB Instance region
     * @param string $dBInstanceIdentifier  optional DB Instance identifier
     * @param string $dBClusterIdentifier   optional DB Cluster identifier
     */
    public function xListInstancesAction($cloudLocation, $dBInstanceIdentifier = null, $dBClusterIdentifier = null)
    {
        $aws = $this->getAwsClient($cloudLocation);

        $dbinstances = $aws->rds->dbInstance->describe($dBInstanceIdentifier);

        $data = [];

        if (count($dbinstances) > 0) {

            $clusters = $aws->rds->dbCluster->describe($dBClusterIdentifier);

            $vpcSglist = $aws->ec2->securityGroup->describe();

            foreach ($dbinstances as $dbinstance) {
                /* @var $dbinstance DBInstanceData */
                if (isset($dBClusterIdentifier) && $dbinstance->dBClusterIdentifier != $dBClusterIdentifier) {
                    continue;
                }

                $data[] = $this->getDbInstanceData($aws, $dbinstance, $vpcSglist, $clusters);
            }
        }

        $response = $this->buildResponseFromData($data, ['DBInstanceIdentifier']);

        $this->response->data($response);
    }

    /**
     *
     * @param string $snapshot
     * @param string $cloudLocation
     */
    public function restoreAction($snapshot, $cloudLocation)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);
        $azlist = $aws->ec2->availabilityZone->describe();

        $zones = [];

        foreach ($azlist as $az) {
            /* @var $az \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
            if (stristr($az->zoneState, 'available')) {
                $zones[] = [
                    'id'   => $az->zoneName,
                    'name' => $az->zoneName,
                ];
            }
        }

        $dbSnapshot = $aws->rds->dbSnapshot->describe(null, $snapshot)->get(0)->toArray(true);

        unset($dbSnapshot['DBInstanceIdentifier']);

        $this->response->page('ui/tools/aws/rds/instances/restore.js', [
            'locations' => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'zones'     => $zones,
            'snapshot'  => $dbSnapshot
        ]);
    }

    /**
     * @param string   $cloudLocation
     * @param string   $DBInstanceIdentifier
     * @param string   $DBSnapshotIdentifier
     * @param string   $Engine
     * @param string   $StorageType
     * @param string   $VpcId                   optional
     * @param string   $LicenseModel            optional
     * @param string   $OptionGroupName         optional
     * @param string   $DBInstanceClass         optional
     * @param JsonData $SubnetIds               optional
     * @param int      $Port                    optional
     * @param string   $AvailabilityZone        optional
     * @param bool     $MultiAZ                 optional
     * @param bool     $AutoMinorVersionUpgrade optional
     * @param string   $DBSubnetGroupName       optional
     * @param int      $Iops                    optional
     * @param string   $DBName                  optional
     */
    public function xRestoreInstanceAction($cloudLocation, $DBInstanceIdentifier, $DBSnapshotIdentifier, $Engine,
                                           $StorageType, $VpcId = null, $LicenseModel = null, $OptionGroupName = null, $DBInstanceClass = null,
                                           JsonData $SubnetIds = null, $Port = null, $AvailabilityZone = null,
                                           $MultiAZ = null, $AutoMinorVersionUpgrade = false, $DBSubnetGroupName = null,
                                           $Iops = null, $DBName = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $request = new RestoreDBInstanceFromDBSnapshotRequestData($DBInstanceIdentifier, $DBSnapshotIdentifier);

        $optionList = $aws->rds->optionGroup->describe($Engine);

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $OptionGroupName) {
                $optionGroup = $option;
                break;
            }
        }

        if (isset($optionGroup)) {
            $request->optionGroupName = $optionGroup->optionGroupName;
        }

        $request->dBInstanceClass         = $DBInstanceClass ?: null;
        $request->port                    = $Port ?: null;
        $request->availabilityZone        = $AvailabilityZone ?: null;
        $request->multiAZ                 = $MultiAZ;
        $request->autoMinorVersionUpgrade = $AutoMinorVersionUpgrade;
        $request->storageType             = $StorageType;
        $request->dBSubnetGroupName       = $DBSubnetGroupName ?: null;
        $request->licenseModel            = $LicenseModel;
        $request->engine                  = $Engine;
        $request->iops                    = $Iops ?: null;
        $request->dBName                  = $DBName ?: null;

        $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkVpcPolicy($VpcId, $SubnetIds, $cloudLocation);

        if ($result === true) {
            $aws->rds->dbInstance->restoreFromSnapshot($request);
            $this->response->success("DB Instance successfully restore from Snapshot");
        } else {
            $this->response->failure($result);
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
        $aws = $this->getAwsClient($cloudLocation);
        $marker = null;
        $result = [];

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

                if (count($subnetGroup['subnets']) > 0) {
                    $subnetGroup['subnets'] = array_map(function (&$subnet) use ($ec2Subnets) {
                        foreach ($ec2Subnets as $ec2Subnet) {
                            if ($ec2Subnet['id'] == $subnet['subnetIdentifier']) {
                                $subnet['type'] = $ec2Subnet['type'];

                                return $subnet;
                            }
                        }

                        return $subnet;
                    }, $subnetGroup['subnets']);
                }

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
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $request = new CreateDBSubnetGroupRequestData($dbSubnetGroupDescription, $dbSubnetGroupName);

        $subnetArr = [];
        foreach ($subnets as $subnet) {
            $subnetArr[] = $subnet;
        }

        $request->subnetIds = $subnetArr;

        $subnetGroup = $this->getAwsClient($cloudLocation)->rds->dbSubnetGroup->create($request)->toArray();

        $this->response->success("DB subnet group successfully created");
        $this->response->data(['subnetGroup' => $subnetGroup]);
    }

    /**
     * Gets a list of engine versions of a specific engine
     *
     * @param string $cloudLocation
     * @param string $engine        optional
     */
    public function xGetEngineVersionsAction($cloudLocation, $engine = null)
    {
        $aws = $this->getAwsClient($cloudLocation);

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

    /**
     *
     * @param string $cloudLocation
     * @param string $vpcId
     */
    public function createSubnetGroupAction($cloudLocation, $vpcId)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->response->page('ui/tools/aws/rds/instances/createSubnetGroup.js', [
            'cloudLocation' => $cloudLocation,
            'vpcId'         => $vpcId
        ]);
    }

    /**
     * xGetOptionGroupsAction
     *
     * @param string $cloudLocation
     * @param string $engine
     * @param string $engineVersion
     * @param bool   $multiAz       optional
     */
    public function xGetOptionGroupsAction($cloudLocation, $engine, $engineVersion, $multiAz = null)
    {
        $majorVersion = null;

        $mirroringEngines = ['sqlserver-se', 'sqlserver-ee'];
        $isMirror = $multiAz && in_array($engine, $mirroringEngines);

        $aws = $this->getAwsClient($cloudLocation);

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

        if (empty($resultOptionGroups)) {
            $resultOptionGroups[] = ['optionGroupName' => $defaultName];
            $default['optionGroupName'] = $defaultName;
        }

        if (empty($default)) {
            $default['optionGroupName'] = $defaultName;
        }

        $this->response->data([
            'optionGroups'           => $resultOptionGroups,
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

        $aws = $this->getAwsClient($cloudLocation);

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
        $zones = [];

        foreach ($this->getAwsClient($cloudLocation)->ec2->availabilityZone->describe() as $az) {
            /* @var $az \Scalr\Service\Aws\Ec2\DataType\AvailabilityZoneData */
            if (stristr($az->zoneState, 'available')) {
                $zones[] = [
                    'id'   => $az->zoneName,
                    'name' => $az->zoneName,
                ];
            }
        }

        $this->response->data(['zones' => $zones]);
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
        $aws = $this->getAwsClient($cloudLocation);

        $request = new DescribeOrderableDBInstanceOptionsData($engine);
        $request->engineVersion = $engineVersion;
        $request->licenseModel  = $licenseModel;

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
     * Get db instance details
     *
     * @param Aws                    $aws
     * @param DBInstanceData         $dbinstance
     * @param SecurityGroupList|null $vpcSglist      optional
     * @param DBClusterList|null     $clusters       optional
     * @return mixed
     * @throws Scalr_Exception_Core
     */
    private function getDbInstanceData(Aws $aws, DBInstanceData $dbinstance, $vpcSglist = null, $clusters = null)
    {
        $cloudLocation = $aws->getRegion();

        $createdTime = $dbinstance->instanceCreateTime;

        $dbinstance = $dbinstance->toArray(true);

        foreach ($dbinstance['VpcSecurityGroups'] as &$vpcSg) {
            $vpcSecurityGroupName = null;

            if (isset($vpcSglist)) {
                foreach ($vpcSglist as $vpcSqData) {
                    /* @var $vpcSqData \Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
                    if ($vpcSqData->groupId == $vpcSg['VpcSecurityGroupId']) {
                        $vpcSecurityGroupName = $vpcSqData->groupName;
                        $vpcId = $vpcSqData->vpcId;
                        break;
                    }
                }
            }

            $vpcSg = [
                'vpcSecurityGroupId'   => $vpcSg['VpcSecurityGroupId'],
                'vpcSecurityGroupName' => $vpcSecurityGroupName
            ];
        }

        $dbinstance ['VpcId'] = !empty($vpcId) ? $vpcId : null;

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

        $dbinstance['Address']               = $dbinstance['Endpoint']['Address'];
        $dbinstance['EngineVersion']         = isset($dbinstance['PendingModifiedValues']) && !empty($dbinstance['PendingModifiedValues']['EngineVersion']) ? $dbinstance['EngineVersion']. ' <i><font color="red">New value (' . $dbinstance['PendingModifiedValues']['EngineVersion'] . ') is pending</font></i>' : $dbinstance['EngineVersion'];
        $dbinstance['Port'] = isset($dbinstance['PendingModifiedValues']) && !empty($dbinstance['PendingModifiedValues']['Port']) ?
            (string) $dbinstance['Endpoint']['Port'] . ' <i><font color="red">New value (' . $dbinstance['PendingModifiedValues']['Port'] . ') is pending</font></i>' : (string)$dbinstance['Endpoint']['Port'];
        $dbinstance['InstanceCreateTime']    = Scalr_Util_DateTime::convertTz($createdTime);
        $dbinstance['DBInstanceClass']       = isset($dbinstance['PendingModifiedValues']) && $dbinstance['PendingModifiedValues']['DBInstanceClass'] ?
            $dbinstance['DBInstanceClass'] . ' <i><font color="red">New value ('. $dbinstance['PendingModifiedValues']['DBInstanceClass'].') is pending</font></i>' : $dbinstance['DBInstanceClass'];
        $dbinstance['AllocatedStorage']      = isset($dbinstance['PendingModifiedValues']) && $dbinstance['PendingModifiedValues']['AllocatedStorage'] ? (string) $dbinstance['AllocatedStorage'] . ' GB' . ' <i><font color="red">New value (' . $dbinstance['PendingModifiedValues']['AllocatedStorage'] . ') is pending</font></i>' : (string) $dbinstance['AllocatedStorage'];
        $dbinstance['BackupRetentionPeriod'] = isset($dbinstance['PendingModifiedValues']) && !empty($dbinstance['PendingModifiedValues']['BackupRetentionPeriod']) ?
            $dbinstance['PendingModifiedValues']['BackupRetentionPeriod']. ' <i><font color="red">(Pending Modified)</font></i>' : $dbinstance['BackupRetentionPeriod'];

        if ($dbinstance['StorageEncrypted']) {
            /* @var $key Aws\Kms\DataType\AliasData */
            foreach ($aws->kms->alias->list() as $key) {
                if (str_replace($key->aliasName, "key/{$key->targetKeyId}", $key->aliasArn) == $dbinstance['KmsKeyId']) {
                    $dbinstance['KmsKeyId'] = $key->aliasName;
                    break;
                }
            }
        }

        if (!empty($dbinstance['DBClusterIdentifier']) && isset($clusters)) {
            foreach ($clusters as $cluster) {
                /* @var $cluster DBClusterData */
                if ($cluster->dBClusterIdentifier == $dbinstance['DBClusterIdentifier']) {
                    foreach ($cluster->dBClusterMembers as $member) {
                        /* @var $member ClusterMemberData */
                        if ($dbinstance['DBInstanceIdentifier'] == $member->dBInstanceIdentifier) {
                            $dbinstance['isReplica'] = !$member->isClusterWriter;
                            break;
                        }
                    }

                    $dbinstance['MultiAZ'] = 'Enabled';
                    break;
                }
            }
        } else {
            $dbinstance['isReplica'] = !empty($dbinstance['ReadReplicaSourceDBInstanceIdentifier']) ? true : false;

            $dbinstance['MultiAZ'] = ($dbinstance['MultiAZ'] ? 'Enabled' : 'Disabled') .
                (isset($dbinstance['PendingModifiedValues']) && isset($dbinstance['PendingModifiedValues']['MultiAZ']) ?
                    ' <i><font color="red">New value(' . ($dbinstance['PendingModifiedValues']['MultiAZ'] ? 'Enabled' : 'Disabled') . ') is pending</font></i>' : '');
        }

        /* @var $cloudResource CloudResource */
        $cloudResource = CloudResource::findPk(
            $dbinstance['DBInstanceIdentifier'],
            CloudResource::TYPE_AWS_RDS,
            $this->getEnvironmentId(),
            \SERVER_PLATFORMS::EC2,
            $cloudLocation
        );

        if ($cloudResource) {
            $dbinstance['farmId']   = $cloudResource->farmId;
            $dbinstance['farmName'] = $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", [$cloudResource->farmId]);
        }

        return $dbinstance;
    }

    /**
     * Action for polling db instances' statuses
     *
     * @param string    $cloudLocation  Aws region
     * @param JsonData  $dbInstances    Db instances to update
     */
    public function xGetDbInstancesStatusAction($cloudLocation, JsonData $dbInstances = null)
    {
        $aws = $this->getAwsClient($cloudLocation);

        $instances = $aws->rds->dbInstance->describe();

        $vpcSglist = $aws->ec2->securityGroup->describe();

        $clusters = $aws->rds->dbCluster->describe();
        /* @var $cluster DBClusterData */
        $updatedInstances = [];

        foreach ($instances as $instance) {
            /* @var $instance DBInstanceData */
            $updatedInstances[$instance->dBInstanceIdentifier] = $instance;
        }

        $data = [];

        foreach ($dbInstances as $dbInstanceId => $status) {
            if (isset($updatedInstances[$dbInstanceId])) {
                if ($status != $updatedInstances[$dbInstanceId]->dBInstanceStatus) {
                    $instanceData = $this->getDbInstanceData($aws, $updatedInstances[$dbInstanceId], $vpcSglist, $clusters);
                    $data[$dbInstanceId] = $instanceData;
                }
            } else {
                $data[$dbInstanceId] = 'deleted';
            }
        }

        $this->response->data([
            'dbInstances' => $data
        ]);
    }

}
