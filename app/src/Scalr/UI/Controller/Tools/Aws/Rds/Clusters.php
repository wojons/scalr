<?php

use Scalr\Model\Entity\CloudResource;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws\Ec2\DataType\SecurityGroupFilterNameType;
use Scalr\Service\Aws\Rds\DataType\AvailabilityZoneData;
use Scalr\Service\Aws\Rds\DataType\CreateDBClusterRequestData;
use Scalr\Service\Aws\Rds\DataType\CreateDBInstanceRequestData;
use Scalr\Service\Aws\Rds\DataType\DBClusterData;
use Scalr\Service\Aws\Rds\DataType\DBInstanceData;
use Scalr\Service\Aws\Rds\DataType\DBParameterGroupData;
use Scalr\Service\Aws\Rds\DataType\ModifyDBClusterRequestData;
use Scalr\Service\Aws\Rds\DataType\OptionGroupData;
use Scalr\Service\Aws\Rds\DataType\RestoreDBClusterFromSnapshotRequestData;
use Scalr\Service\Aws\Rds\DataType\TagsList;
use Scalr\UI\Request\JsonData;
use Scalr\UI\Request\RawData;
use Scalr\Acl\Acl;
use Scalr\Service\Aws;
use Scalr\Model\Entity;

/**
 * Controller for managing RDS db clusters.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 */
class Scalr_UI_Controller_Tools_Aws_Rds_Clusters extends Scalr_UI_Controller
{
    /**
     * Param name in url
     */
    const CALL_PARAM_NAME = 'dBClusterIdentifier';

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

    /**
     * List page
     */
    public function viewAction()
    {
        $this->response->page('ui/tools/aws/rds/clusters/view.js');
    }

    /**
     * Create page
     */
    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $this->response->page('ui/tools/aws/rds/clusters/create.js', [
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'accountId'     => $this->environment->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress' => $this->request->getRemoteAddr(),
        ]);
    }

    /**
     * Edit page
     *
     * @param string $cloudLocation                AWS region
     * @param string $dBClusterIdentifier optional DB Cluster identifier
     * @param string $vpcId                        Vpc id
     */
    public function editAction($cloudLocation, $dBClusterIdentifier = null, $vpcId = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $dbCluster = $aws->rds->dbCluster->describe($dBClusterIdentifier)->get(0)->toArray(true);

        $vpcSglist = null;

        if (!empty($vpcId)) {
            $filter[] = [
                'name'  => SecurityGroupFilterNameType::vpcId(),
                'value' => $vpcId
            ];

            $vpcSglist = $aws->ec2->securityGroup->describe(null, null, $filter);
        }

        foreach ($dbCluster['VpcSecurityGroups'] as &$vpcSg) {
            $vpcSecurityGroupName = null;

            foreach ($vpcSglist as $vpcSqData) {
                /* @var $vpcSqData \Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
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

        $dbCluster['VpcId'] = !empty($vpcId) ? $vpcId : null;

        $this->response->page([ 'ui/tools/aws/rds/clusters/edit.js', 'ui/security/groups/sgeditor.js' ], [
            'locations'     => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'cluster'       => $dbCluster,
            'accountId'     => $this->environment->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID],
            'remoteAddress' => $this->request->getRemoteAddr()
        ]);
    }

    /**
     * xListAction
     *
     * @param string $cloudLocation                Aws region
     * @param string $dBClusterIdentifier optional DB Cluster identifier
     */
    public function xListAction($cloudLocation, $dBClusterIdentifier = null)
    {
        $aws = $this->getAwsClient($cloudLocation);

        $clusters = $aws->rds->dbCluster->describe($dBClusterIdentifier)->toArray();

        if (count($clusters) > 0) {
            $vpcSglist = $aws->ec2->securityGroup->describe();

            foreach ($clusters as &$cluster) {
                foreach ($cluster['vpcSecurityGroups'] as &$vpcSg) {
                    $vpcSecurityGroupName = null;

                    foreach ($vpcSglist as $vpcSqData) {
                        /* @var $vpcSqData \Scalr\Service\Aws\Ec2\DataType\SecurityGroupData */
                        if ($vpcSqData->groupId == $vpcSg['vpcSecurityGroupId']) {
                            $vpcSecurityGroupName = $vpcSqData->groupName;
                            $vpcId = $vpcSqData->vpcId;
                            break;
                        }
                    }

                    $vpcSg = [
                        'vpcSecurityGroupId'   => $vpcSg['vpcSecurityGroupId'],
                        'vpcSecurityGroupName' => $vpcSecurityGroupName
                    ];
                }

                $cluster['vpcId'] = !empty($vpcId) ? $vpcId : null;

                $members = [];

                foreach ($cluster['dBClusterMembers'] as $member) {
                    $members[] = $member['dBInstanceIdentifier'];
                }

                $zones = [];

                foreach ($cluster['availabilityZones'] as $availabilityZone) {
                    $zones[] = $availabilityZone['name'];
                }

                $cluster['dBClusterMembers']  = $members;
                $cluster['availabilityZones'] = $zones;
                $cluster['membersCount']      = count($members);
            }
        }

        $response = $this->buildResponseFromData($clusters, ['dBClusterIdentifier', 'status']);
        $this->response->data($response);
    }

    /**
     * xDeleteAction
     *
     * @param  string       $cloudLocation          Aws region
     * @param  JsonData     $dBClusterIdentifiers   List of cluster ids
     */
    public function xDeleteAction($cloudLocation, JsonData $dBClusterIdentifiers)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $dBClusterIdentifiers = (array) $dBClusterIdentifiers;

        $aws = $this->getAwsClient($cloudLocation);

        $instances = $aws->rds->dbInstance->describe();

        foreach ($instances as $instance) {
            /* @var $instance DBInstanceData */
            if (in_array($instance->dBClusterIdentifier, $dBClusterIdentifiers) && $instance->dBInstanceStatus != 'deleting') {
                $instance->delete(true);

                CloudResource::deletePk(
                    $instance->dBInstanceIdentifier,
                    CloudResource::TYPE_AWS_RDS,
                    $this->getEnvironmentId(),
                    \SERVER_PLATFORMS::EC2,
                    $cloudLocation
                );
            }
        }

        foreach ($dBClusterIdentifiers as $dBClusterIdentifier) {
            $aws->rds->dbCluster->delete($dBClusterIdentifier);
        }

        $this->response->success();
    }

    /**
     * Creates new DB Cluster from request data
     *
     * @param   string      $cloudLocation                        Instance cloud location
     * @param   string      $DBClusterIdentifier                  DBClusterIdentifier field
     * @param   string      $Engine Database                      Db engine
     * @param   string      $MasterUsername                       User name
     * @param   RawData     $MasterUserPassword                   User password
     * @param   string      $VpcId                       optional Ec2 vpc id
     * @param   int         $Port                        optional DB Port
     * @param   string      $DBName                      optional DB name
     * @param   string      $CharacterSetName            optional Character set name
     * @param   string      $DBParameterGroup            optional Parameter group name
     * @param   string      $OptionGroupName             optional Option group name
     * @param   JsonData    $AvailabilityZones           optional Aws availability zone list
     * @param   int         $BackupRetentionPeriod       optional Backup Retention Period
     * @param   string      $PreferredBackupWindow       optional Preferred Backup Window
     * @param   string      $PreferredMaintenanceWindow  optional Preferred Maintenance Window
     * @param   string      $DBSubnetGroupName           optional Subnet group name
     * @param   string      $EngineVersion               optional Engine's version
     * @param   int         $farmId                      optional Farm identifier
     * @param   JsonData    $VpcSecurityGroups           optional VPC Security groups list
     * @param   JsonData    $SubnetIds                   optional Subnets list
     */
    public function xSaveAction($cloudLocation, $DBClusterIdentifier, $Engine, $MasterUsername, RawData $MasterUserPassword,
                                $VpcId = null, $Port = null, $DBName = null, $CharacterSetName = null, $DBParameterGroup = null,
                                $OptionGroupName = null, JsonData $AvailabilityZones = null, $BackupRetentionPeriod = null,
                                $PreferredBackupWindow = null, $PreferredMaintenanceWindow = null, $DBSubnetGroupName = null,
                                $EngineVersion = null, $farmId = null, JsonData $VpcSecurityGroups = null, JsonData $SubnetIds = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $request = new CreateDBClusterRequestData($DBClusterIdentifier, $Engine, $MasterUsername, (string) $MasterUserPassword ?: null);

        $request->port             = $Port ?: null;
        $request->databaseName     = $DBName ?: null;
        $request->characterSetName = $CharacterSetName ?: null;

        $paramName = $DBParameterGroup;

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
            $request->dBClusterParameterGroupName = $paramGroup->dBParameterGroupName;
        }

        $optionList = $aws->rds->optionGroup->describe($Engine);

        foreach ($optionList as $option) {
            /* @var $option OptionGroupData */
            if ($option->optionGroupName == $OptionGroupName) {
                $optionGroup = $option;
                break;
            }
        }

        if (!empty($optionGroup)) {
            //NOTE: currently OptionGroups not supported for clusters
            //$request->optionGroupName = $optionGroup->optionGroupName;
        }

        $request->availabilityZones          = count($AvailabilityZones) > 0 ? (array) $AvailabilityZones : null;
        $request->backupRetentionPeriod      = $BackupRetentionPeriod ?: null;
        $request->preferredBackupWindow      = $PreferredBackupWindow ?: null;
        $request->preferredMaintenanceWindow = $PreferredMaintenanceWindow ?: null;

        $request->dBSubnetGroupName = $DBSubnetGroupName ?: null;

        $vpcSgIds = [];
        foreach ($VpcSecurityGroups as $VpcSecurityGroup) {
            $vpcSgIds[] = $VpcSecurityGroup['id'];
        }

        $request->vpcSecurityGroupIds = empty($vpcSgIds) ? null : $vpcSgIds;
        $request->engineVersion = $EngineVersion ?: null;

        $tagsObject = $farmId ? DBFarm::LoadByID($farmId) : $this->environment;

        $request->tags = new TagsList($tagsObject->getAwsTags());

        $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkSecurityGroupsPolicy($VpcSecurityGroups, Aws::SERVICE_INTERFACE_RDS);

        if ($result === true) {
            $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkVpcPolicy($VpcId, $SubnetIds, $cloudLocation);
        }

        if ($result === true) {
            $aws->rds->dbCluster->create($request);
        } else {
            $this->response->failure($result);
        }
    }

    /**
     * xRestoreClusterAction
     *
     * @param string   $cloudLocation           Ec2 region
     * @param string   $DBClusterIdentifier     DBClusterIdentifier field
     * @param string   $DBSnapshotIdentifier    DBSnapshotIdentifier field
     * @param string   $Engine                  Aurora engine
     * @param string   $VpcId                   Vpc id
     * @param int      $Port                    Port value
     * @param string   $DBInstanceClass         Db instance class
     * @param bool     $PublicAccessible        True if instance is public accessible
     * @param RawData  $MasterUserPassword      DB Password
     * @param JsonData $SubnetIds               optional List of subnet ids
     * @param string   $OptionGroupName         optional Option group name
     * @param JsonData $AvailabilityZones       optional List of availability zones
     * @param string   $DBSubnetGroupName       optional Subnet group name
     * @param bool     $AutoMinorVersionUpgrade optional Auto minor version upgrade
     */
    public function xRestoreClusterAction($cloudLocation, $DBClusterIdentifier, $DBSnapshotIdentifier, $Engine, $VpcId, $Port,
                                          $DBInstanceClass, $PublicAccessible, RawData $MasterUserPassword, JsonData $SubnetIds = null, $OptionGroupName = null,
                                          JsonData $AvailabilityZones = null, $DBSubnetGroupName = null, $AutoMinorVersionUpgrade = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $aws = $this->getAwsClient($cloudLocation);

        $request = new RestoreDBClusterFromSnapshotRequestData($DBClusterIdentifier, $DBSnapshotIdentifier);

        //NOTE: Options groups currently not supported for db clusters
//        $optionList = $aws->rds->optionGroup->describe($Engine);
//
//        foreach ($optionList as $option) {
//            /* @var $option OptionGroupData */
//            if ($option->optionGroupName == $OptionGroupName) {
//                $optionGroup = $option;
//                break;
//            }
//        }
//
//        if (isset($optionGroup)) {
//            $request->optionGroupName = $optionGroup->optionGroupName;
//        }

        $request->port                    = $Port ?: null;
        $request->availabilityZones       = count($AvailabilityZones) > 0 ? (array) $AvailabilityZones : null;
        $request->dBSubnetGroupName       = $DBSubnetGroupName ?: null;
        $request->engine                  = $Engine;

        $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkVpcPolicy($VpcId, $SubnetIds, $cloudLocation);

        if ($result === true) {
            $restoreResponse = $aws->rds->dbCluster->restoreFromSnapshot($request);

            try {
                $instance = $aws->rds->dbInstance->describe($DBClusterIdentifier)->get();
            } catch (Exception $e) {
                $instance = false;
            }

            if (!$instance) {
                $dbInstanceIdentifier = $DBClusterIdentifier;
            } else {
                $dbInstanceIdentifier = $DBClusterIdentifier . '-restored';
            }

            $createRequest = new CreateDBInstanceRequestData($dbInstanceIdentifier, $DBInstanceClass, $Engine);
            $createRequest->dBSubnetGroupName = $DBSubnetGroupName;
            $createRequest->publiclyAccessible = $PublicAccessible;
            $createRequest->licenseModel = 'general-public-license';
            $createRequest->engineVersion = $restoreResponse->engineVersion;
            $createRequest->storageType = 'aurora';
            $createRequest->setTags($this->environment->getAwsTags());
            $createRequest->autoMinorVersionUpgrade = $AutoMinorVersionUpgrade;
            $createRequest->dBClusterIdentifier = $restoreResponse->dBClusterIdentifier;

            $aws->rds->dbInstance->create($createRequest);

            CloudResource::deletePk(
                $dbInstanceIdentifier,
                CloudResource::TYPE_AWS_RDS,
                $this->getEnvironmentId(),
                \SERVER_PLATFORMS::EC2,
                $cloudLocation
            );

            $this->response->success("DB Cluster has been successfully restored from Snapshot");
        } else {
            $this->response->failure($result);
        }
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

        $dbSnapshot = $aws->rds->dbClusterSnapshot->describe(null, $snapshot)->get(0);

        $snapshotZones = [];

        foreach ($dbSnapshot->availabilityZones as $zone) {
            /* @var $zone AvailabilityZoneData */
            $snapshotZones[] = $zone->name;
        }

        $dbSnapshot = $dbSnapshot->toArray(true);

        $dbSnapshot['AvailabilityZones'] = $snapshotZones;

        unset($dbSnapshot['DBClusterIdentifier'], $dbSnapshot['LicenseModel'], $dbSnapshot['AllocatedStorage'],
            $dbSnapshot['ClusterCreateTime'], $dbSnapshot['SnapshotCreateTime'], $dbSnapshot['Status']);

        $this->response->page('ui/tools/aws/rds/clusters/restore.js', [
            'locations' => self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false),
            'zones'     => $zones,
            'snapshot'  => $dbSnapshot
        ]);
    }

    /**
     * Modifies selected cluster
     *
     * @param   string      $DBClusterIdentifier                     DBClusterIdentifier field
     * @param   string      $cloudLocation                           Ec2 region
     * @param   JsonData    $VpcSecurityGroupIds            optional VPC Security groups list
     * @param   string      $PreferredMaintenanceWindow     optional Preferred Maintenance Window
     * @param   RawData     $MasterUserPassword             optional User password
     * @param   string      $BackupRetentionPeriod          optional Backup Retention Period
     * @param   string      $PreferredBackupWindow          optional Preferred Backup Window
     * @param   string      $OptionGroupName                optional Option group name
     * @param   bool        $ApplyImmediately               optional ApplyImmediately flag
     * @param   bool        $ignoreGovernance               optional Ignore governance if true
     */
    public function xModifyAction($DBClusterIdentifier, $cloudLocation, JsonData $VpcSecurityGroupIds = null,
                                  $PreferredMaintenanceWindow = null, RawData $MasterUserPassword = null,
                                  $BackupRetentionPeriod = null, $PreferredBackupWindow = null, $OptionGroupName = null,
                                  $ApplyImmediately = false, $ignoreGovernance = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_RDS, Acl::PERM_AWS_RDS_MANAGE);

        $request = new ModifyDBClusterRequestData($DBClusterIdentifier);
        $request->preferredMaintenanceWindow = $PreferredMaintenanceWindow;
        $request->masterUserPassword         = (string) $MasterUserPassword ?: null;
        $request->backupRetentionPeriod      = $BackupRetentionPeriod;
        $request->preferredBackupWindow      = $PreferredBackupWindow;
        $request->applyImmediately           = $ApplyImmediately;

        $vpcSgIds = [];
        foreach ($VpcSecurityGroupIds as $VpcSecurityGroup) {
            $vpcSgIds[] = $VpcSecurityGroup['id'];
        }

        $request->vpcSecurityGroupIds = empty($vpcSgIds) ? null : $vpcSgIds;

        //NOTE: Options groups currently not supported for db clusters
//        $optionList = $aws->rds->optionGroup->describe();
//
//        foreach ($optionList as $option) {
//            /* @var $option OptionGroupData */
//            if ($option->optionGroupName == $OptionGroupName) {
//                $optionGroup = $option;
//                break;
//            }
//        }
//
//        if (!empty($optionGroup)) {
//            $request->optionGroupName = $optionGroup->optionGroupName;
//        }

        if (!$ignoreGovernance) {
            $result = self::loadController('Aws', 'Scalr_UI_Controller_Tools')->checkSecurityGroupsPolicy($VpcSecurityGroupIds, Aws::SERVICE_INTERFACE_RDS);
        }

        if (!isset($result) || $result === true) {
            $this->getAwsClient($cloudLocation)->rds->dbCluster->modify($request);
            $this->response->success("DB Cluster successfully modified");
        } else {
            $this->response->failure($result);
        }
    }

    /**
     * Action for polling db clusters' statuses
     *
     * @param string    $cloudLocation  Aws region
     * @param JsonData  $dbClusters     Db clusters to update
     */
    public function xGetDbClustersStatusAction($cloudLocation, JsonData $dbClusters = null)
    {
        $aws = $this->getAwsClient($cloudLocation);

        $clusters = $aws->rds->dbCluster->describe();

        $updatedClusters = [];

        foreach ($clusters as $cluster) {
            /* @var $cluster DBClusterData */
            $updatedClusters[$cluster->dBClusterIdentifier] = $cluster->status;
        }

        $data = [];

        foreach ($dbClusters as $dbClusterId => $status) {
            if (isset($updatedClusters[$dbClusterId])) {
                if ($status != $updatedClusters[$dbClusterId]) {
                    $data[$dbClusterId] = $updatedClusters[$dbClusterId];
                }
            } else {
                $data[$dbClusterId] = 'deleted';
            }
        }

        $this->response->data($data);
    }

}
