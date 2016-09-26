<?php
namespace Scalr\Service\Aws\Rds\V20141031;

use Scalr\Service\Aws\Rds\DataType\AvailabilityZoneData;
use Scalr\Service\Aws\Rds\DataType\AvailabilityZoneList;
use Scalr\Service\Aws\Rds\DataType\CharacterSetData;
use Scalr\Service\Aws\Rds\DataType\CharacterSetList;
use Scalr\Service\Aws\Rds\DataType\CreateDBClusterRequestData;
use Scalr\Service\Aws\Rds\DataType\CreateDBInstanceReadReplicaData;
use Scalr\Service\Aws\Rds\DataType\DBClusterData;
use Scalr\Service\Aws\Rds\DataType\DBClusterList;
use Scalr\Service\Aws\Rds\DataType\DBClusterSnapshotData;
use Scalr\Service\Aws\Rds\DataType\DBClusterSnapshotList;
use Scalr\Service\Aws\Rds\DataType\DBEngineVersionList;
use Scalr\Service\Aws\Rds\DataType\DBEngineVersionData;
use Scalr\Service\Aws\Rds\DataType\DBSubnetGroupList;
use Scalr\Service\Aws\Rds\DataType\DescribeDBEngineVersionsData;
use Scalr\Service\Aws\Rds\DataType\DescribeOrderableDBInstanceOptionsData;
use Scalr\Service\Aws\Rds\DataType\EventData;
use Scalr\Service\Aws\Rds\DataType\EventList;
use Scalr\Service\Aws\Rds\DataType\DescribeEventRequestData;
use Scalr\Service\Aws\Rds\DataType\ModifyDBClusterRequestData;
use Scalr\Service\Aws\Rds\DataType\ModifyDBSubnetGroupRequestData;
use Scalr\Service\Aws\Rds\DataType\OptionData;
use Scalr\Service\Aws\Rds\DataType\OptionGroupData;
use Scalr\Service\Aws\Rds\DataType\OptionGroupsList;
use Scalr\Service\Aws\Rds\DataType\OptionList;
use Scalr\Service\Aws\Rds\DataType\OptionSettingData;
use Scalr\Service\Aws\Rds\DataType\OptionSettingList;
use Scalr\Service\Aws\Rds\DataType\OrderableDBInstanceOptionsData;
use Scalr\Service\Aws\Rds\DataType\OrderableDBInstanceOptionsList;
use Scalr\Service\Aws\Rds\DataType\RestoreDBClusterFromSnapshotRequestData;
use Scalr\Service\Aws\Rds\DataType\RestoreDBInstanceFromDBSnapshotRequestData;
use Scalr\Service\Aws\Rds\DataType\DBSnapshotData;
use Scalr\Service\Aws\Rds\DataType\DBSnapshotList;
use Scalr\Service\Aws\Rds\DataType\ParameterData;
use Scalr\Service\Aws\Rds\DataType\ParameterList;
use Scalr\Service\Aws\Rds\DataType\DBParameterGroupData;
use Scalr\Service\Aws\Rds\DataType\DBParameterGroupList;
use Scalr\Service\Aws\Rds\DataType\DBSecurityGroupIngressRequestData;
use Scalr\Service\Aws\Rds\DataType\IPRangeData;
use Scalr\Service\Aws\Rds\DataType\IPRangeList;
use Scalr\Service\Aws\Rds\DataType\EC2SecurityGroupData;
use Scalr\Service\Aws\Rds\DataType\EC2SecurityGroupList;
use Scalr\Service\Aws\Rds\DataType\DBSecurityGroupData;
use Scalr\Service\Aws\Rds\DataType\DBSecurityGroupList;
use Scalr\Service\Aws\Rds\DataType\ModifyDBInstanceRequestData;
use Scalr\Service\Aws\Rds\DataType\SubnetData;
use Scalr\Service\Aws\Rds\DataType\SubnetList;
use Scalr\Service\Aws\Rds\DataType\TagsData;
use Scalr\Service\Aws\Rds\DataType\TagsList;
use Scalr\Service\Aws\Rds\DataType\VpcSecurityGroupMembershipData;
use Scalr\Service\Aws\Rds\DataType\VpcSecurityGroupMembershipList;
use Scalr\Service\Aws\Rds\DataType\DBSecurityGroupMembershipData;
use Scalr\Service\Aws\Rds\DataType\DBSecurityGroupMembershipList;
use Scalr\Service\Aws\Rds\DataType\DBParameterGroupStatusData;
use Scalr\Service\Aws\Rds\DataType\DBParameterGroupStatusList;
use Scalr\Service\Aws\Rds\DataType\PendingModifiedValuesData;
use Scalr\Service\Aws\Rds\DataType\OptionGroupMembershipData;
use Scalr\Service\Aws\Rds\DataType\OptionGroupMembershipList;
use Scalr\Service\Aws\Rds\DataType\EndpointData;
use Scalr\Service\Aws\Rds\DataType\DBInstanceData;
use Scalr\Service\Aws\Rds\DataType\DBInstanceList;
use Scalr\Service\Aws\Rds\DataType\CreateDBInstanceRequestData;
use Scalr\Service\Aws\Rds\DataType\CreateDBSubnetGroupRequestData;
use Scalr\Service\Aws\Rds\DataType\DBSubnetGroupData;
use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws;
use Scalr\Service\Aws\AbstractApi;
use Scalr\Service\Aws\Rds;
use Scalr\Service\Aws\RdsException;
use Scalr\Service\Aws\EntityManager;
use Scalr\Service\Aws\Client\ClientInterface;
use Scalr\Service\Aws\Client\ClientException;
use DateTimeZone;
use DateTime;


/**
 * Rds Api messaging.
 *
 * Implements Rds Low-Level API Actions.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 */
class RdsApi extends AbstractApi
{

    const UNEXPECTED = 'Could not %s. Unexpected response from AWS.';

    /**
     * @var Rds
     */
    protected $rds;

    /**
     * @var string
     */
    protected $versiondate;

    /**
     * Constructor
     *
     * @param   Rds                 $rds          Rds instance
     * @param   ClientInterface     $client       Client Interface
     */
    public function __construct(Rds $rds, ClientInterface $client)
    {
        $this->rds = $rds;
        $this->client = $client;
        $this->versiondate = preg_replace('#^.+V(\d{4})(\d{2})(\d{2})$#', '\\1-\\2-\\3', __NAMESPACE__);
    }

    /**
     * Gets an entity manager
     *
     * @return EntityManager
     */
    public function getEntityManager()
    {
        return $this->rds->getEntityManager();
    }

    /**
     * DescribeDBInstances action
     *
     * Returns information about provisioned RDS instances. This API supports pagination
     *
     * @param   string          $dbInstanceIdentifier optional The user-specified instance identifier.
     * @param   string          $marker               optional The response includes only records beyond the marker.
     * @param   int             $maxRecords           optional The maximum number of records to include in the response.
     * @return  DBInstanceList  Returns the list of DB Instances
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeDBInstances($dbInstanceIdentifier = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = array();
        if ($dbInstanceIdentifier !== null) {
            $options['DBInstanceIdentifier'] = (string) $dbInstanceIdentifier;
        }
        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }
        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = new DBInstanceList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($sxml->DescribeDBInstancesResult->Marker) ?
                (string) $sxml->DescribeDBInstancesResult->Marker : null;
            if (isset($sxml->DescribeDBInstancesResult->DBInstances->DBInstance)) {
                foreach ($sxml->DescribeDBInstancesResult->DBInstances->DBInstance as $v) {
                    $item = $this->_loadDBInstanceData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }
        return $result;
    }

    /**
     * Loads DBInstanceData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBInstanceData Returns DBInstanceData
     */
    protected function _loadDBInstanceData(\SimpleXMLElement $sxml)
    {
        $item = null;

        if ($this->exist($sxml)) {
            $dbInstanceIdentifier = (string) $sxml->DBInstanceIdentifier;
            $item = $this->rds->getEntityManagerEnabled() ? $this->rds->dbInstance->get($dbInstanceIdentifier) : null;

            if ($item === null) {
                $item = new DBInstanceData();
                $item->setRds($this->rds);
                $bAttach = true;
            } else {
                $item->resetObject();
                $bAttach = false;
            }

            $item->dBInstanceIdentifier = $dbInstanceIdentifier;
            $item->dBClusterIdentifier = $this->get($sxml->DBClusterIdentifier);
            $item->allocatedStorage = $this->exist($sxml->AllocatedStorage) ? (int) $sxml->AllocatedStorage : null;
            $item->autoMinorVersionUpgrade = $this->exist($sxml->AutoMinorVersionUpgrade) ?
                (((string)$sxml->AutoMinorVersionUpgrade) == 'true') : null;
            $item->availabilityZone = $this->exist($sxml->AvailabilityZone) ? (string) $sxml->AvailabilityZone : null;
            $item->backupRetentionPeriod = $this->exist($sxml->BackupRetentionPeriod) ?
                (int) $sxml->BackupRetentionPeriod : null;
            $item->characterSetName = $this->exist($sxml->CharacterSetName) ? (string) $sxml->CharacterSetName : null;
            $item->dBInstanceClass = $this->exist($sxml->DBInstanceClass) ? (string) $sxml->DBInstanceClass : null;
            $item->dBInstanceStatus = $this->exist($sxml->DBInstanceStatus) ? (string) $sxml->DBInstanceStatus : null;
            $item->dBName = $this->exist($sxml->DBName) ? (string) $sxml->DBName : null;
            $item->engine = $this->exist($sxml->Engine) ? (string) $sxml->Engine : null;
            $item->engineVersion = $this->exist($sxml->EngineVersion) ? (string) $sxml->EngineVersion : null;
            $item->instanceCreateTime = $this->exist($sxml->InstanceCreateTime) ?
                new DateTime((string) $sxml->InstanceCreateTime, new DateTimeZone('UTC')) : null;
            $item->iops = $this->exist($sxml->Iops) ? (int) $sxml->Iops : null;
            $item->latestRestorableTime = $this->exist($sxml->LatestRestorableTime) ?
                new DateTime((string) $sxml->LatestRestorableTime, new DateTimeZone('UTC')) : null;
            $item->licenseModel = $this->exist($sxml->LicenseModel) ? (string) $sxml->LicenseModel : null;
            $item->masterUsername = $this->exist($sxml->MasterUsername) ? (string) $sxml->MasterUsername : null;
            $item->multiAZ = $this->exist($sxml->MultiAZ) ? (((string)$sxml->MultiAZ) == 'true') : null;
            $item->preferredBackupWindow = $this->exist($sxml->PreferredBackupWindow) ?
                (string) $sxml->PreferredBackupWindow : null;
            $item->preferredMaintenanceWindow = $this->exist($sxml->PreferredMaintenanceWindow) ?
                (string) $sxml->PreferredMaintenanceWindow : null;
            $item->publiclyAccessible = $this->exist($sxml->PubliclyAccessible) ?
                (((string)$sxml->PubliclyAccessible) == 'true') : null;
            $item->readReplicaSourceDBInstanceIdentifier = $this->exist($sxml->ReadReplicaSourceDBInstanceIdentifier) ?
                (string) $sxml->ReadReplicaSourceDBInstanceIdentifier : null;
            $item->secondaryAvailabilityZone = $this->exist($sxml->SecondaryAvailabilityZone) ?
                (string) $sxml->SecondaryAvailabilityZone : null;
            $item->iops = $this->exist($sxml->Iops) ? (int) $sxml->Iops : null;
            $item->storageEncrypted = $this->get($sxml->StorageEncrypted, 'bool');
            $item->kmsKeyId = $this->get($sxml->KmsKeyId);
            $item->storageType = $this->exist($sxml->StorageType) ? (string) $sxml->StorageType : null;

            $item->readReplicaDBInstanceIdentifiers = array();
            if (!empty($sxml->ReadReplicaDBInstanceIdentifiers->ReadReplicaDBInstanceIdentifier)) {
                foreach ($sxml->ReadReplicaDBInstanceIdentifiers->ReadReplicaDBInstanceIdentifier as $v) {
                    $item->readReplicaDBInstanceIdentifiers[] = (string) $v;
                }
            }
            $item->dBParameterGroups = $this->_loadDBParameterGroupStatusList($sxml->DBParameterGroups);
            $item->dBSecurityGroups = $this->_loadDBSecurityGroupMembershipList($sxml->DBSecurityGroups);
            $item->vpcSecurityGroups = $this->_loadVpcSecurityGroupMembershipList($sxml->VpcSecurityGroups);
            $item->endpoint = $this->_loadEndpointData($sxml->Endpoint);
            $item->optionGroupMembership = $this->_loadOptionGroupMembershipList($sxml->OptionGroupMemberships);
            $item->pendingModifiedValues = $this->_loadPendingModifiedValuesData($sxml->PendingModifiedValues);
            $item->dBSubnetGroup = $this->_loadDBSubnetGroupData($sxml->DBSubnetGroup);

            if ($bAttach && $this->rds->getEntityManagerEnabled()) {
                $this->getEntityManager()->attach($item);
            }
        }

        return $item;
    }

    /**
     * Loads EndpointData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  EndpointData Returns EndpointData
     */
    protected function _loadEndpointData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new EndpointData(
                ($this->exist($sxml->Address) ? (string) $sxml->Address : null),
                ($this->exist($sxml->Port) ? (int) $sxml->Port : null)
            );
            $item->setRds($this->rds);
        }
        return $item;
    }

    /**
     * Loads OptionGroupMembershipList from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return OptionGroupMembershipList
     */
    protected function _loadOptionGroupMembershipList(\SimpleXMLElement $sxml)
    {
        $result = new OptionGroupMembershipList();

        $result->setRds($this->rds);

        if ($this->exist($sxml->OptionGroupMembership)) {
            foreach ($sxml->OptionGroupMembership as $v) {
                $item = new OptionGroupMembershipData(
                    ($this->exist($v->OptionGroupName) ? (string) $v->OptionGroupName : null),
                    ($this->exist($v->Status) ? (string)$v->Status : null)
                );
                $item->setRds($this->rds);
                $result->append($item);
                unset($item);
            }
        }
        return $result;
    }

    /**
     * Loads OptionGroupMembershipData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  OptionGroupMembershipData Returns OptionGroupMembershipData
     */
    protected function _loadOptionGroupMembershipData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new OptionGroupMembershipData(
                ($this->exist($sxml->OptionGroupName) ? (string) $sxml->OptionGroupName : null),
                ($this->exist($sxml->Status) ? (string)$sxml->Status : null)
            );
            $item->setRds($this->rds);
        }
        return $item;
    }

    /**
     * Loads PendingModifiedValuesData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  PendingModifiedValuesData Returns PendingModifiedValuesData
     */
    protected function _loadPendingModifiedValuesData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new PendingModifiedValuesData();
            $item->setRds($this->rds);
            $item->allocatedStorage = $this->exist($sxml->AllocatedStorage) ? (int)$sxml->AllocatedStorage : null;
            $item->backupRetentionPeriod = $this->exist($sxml->BackupRetentionPeriod) ? (int)$sxml->BackupRetentionPeriod : null;
            $item->dBInstanceClass = $this->exist($sxml->DBInstanceClass) ? (string)$sxml->DBInstanceClass : null;
            $item->dBInstanceIdentifier = $this->exist($sxml->DBInstanceIdentifier) ? (string)$sxml->DBInstanceIdentifier : null;
            $item->engineVersion = $this->exist($sxml->EngineVersion) ? (string)$sxml->EngineVersion : null;
            $item->iops = $this->exist($sxml->Iops) ? (int)$sxml->Iops : null;
            $item->masterUserPassword = $this->exist($sxml->MasterUserPassword) ? (string)$sxml->MasterUserPassword : null;
            $item->multiAZ = $this->exist($sxml->MultiAZ) ? (((string)$sxml->MultiAZ) == 'true') : null;
            $item->port = $this->exist($sxml->Port) ? (int)$sxml->Port : null;
        }
        return $item;
    }

    /**
     * Loads DBParameterGroupStatusList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBParameterGroupStatusList Returns DBParameterGroupStatusList
     */
    protected function _loadDBParameterGroupStatusList(\SimpleXMLElement $sxml)
    {
        $list = new DBParameterGroupStatusList();
        $list->setRds($this->rds);
        if (!empty($sxml->DBParameterGroup)) {
            foreach ($sxml->DBParameterGroup as $v) {
                $item = new DBParameterGroupStatusData(
                    ($this->exist($v->DBParameterGroupName) ? (string)$v->DBParameterGroupName : null),
                    ($this->exist($v->ParameterApplyStatus) ? (string)$v->ParameterApplyStatus : null)
                );
                $item->setRds($this->rds);
                $list->append($item);
                unset($item);
            }
        }
        return $list;
    }

    /**
     * Loads DBSecurityGroupMembershipList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBSecurityGroupMembershipList Returns DBSecurityGroupMembershipList
     */
    protected function _loadDBSecurityGroupMembershipList(\SimpleXMLElement $sxml)
    {
        $list = new DBSecurityGroupMembershipList();
        $list->setRds($this->rds);
        if (!empty($sxml->DBSecurityGroup)) {
            foreach ($sxml->DBSecurityGroup as $v) {
                $item = new DBSecurityGroupMembershipData(
                    ($this->exist($v->DBSecurityGroupName) ? (string)$v->DBSecurityGroupName : null),
                    ($this->exist($v->Status) ? (string)$v->Status : null)
                );
                $item->setRds($this->rds);
                $list->append($item);
                unset($item);
            }
        }
        return $list;
    }

    /**
     * Loads VpcSecurityGroupMembershipList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  VpcSecurityGroupMembershipList Returns VpcSecurityGroupMembershipList
     */
    protected function _loadVpcSecurityGroupMembershipList(\SimpleXMLElement $sxml)
    {
        $list = new VpcSecurityGroupMembershipList();
        $list->setRds($this->rds);
        if (!empty($sxml->VpcSecurityGroupMembership)) {
            foreach ($sxml->VpcSecurityGroupMembership as $v) {
                $item = new VpcSecurityGroupMembershipData(
                    ($this->exist($v->VpcSecurityGroupId) ? (string)$v->VpcSecurityGroupId : null),
                    ($this->exist($v->Status) ? (string)$v->Status : null)
                );
                $item->setRds($this->rds);
                $list->append($item);
                unset($item);
            }
        }
        return $list;
    }

    /**
     * CreateDBInstance action
     *
     * Creates a new DB instance.
     *
     * @param   CreateDBInstanceRequestData $request Created DB Instance request object
     * @return  DBInstanceData  Returns created DBInstance
     * @throws  ClientException
     * @throws  RdsException
     */
    public function createDBInstance(CreateDBInstanceRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();
        if ($this->rds->getApiClientType() === Aws::CLIENT_SOAP) {
            if (isset($options['DBSecurityGroups.member.1']) || isset($options['VpcSecurityGroupIds.member.1'])) {
                foreach ($options as $k => $v) {
                    if (strpos($k, 'DBSecurityGroups.member.') !== false) {
                        $options['DBSecurityGroups']['DBSecurityGroupName'][] = $v;
                        unset($options[$k]);
                    } elseif (strpos($k, 'VpcSecurityGroupIds.member.') !== false) {
                        $options['VpcSecurityGroupIds']['VpcSecurityGroupId'][] = $v;
                        unset($options[$k]);
                    }
                }
            }
        }
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->CreateDBInstanceResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'create DBIntance'));
            }
            $result = $this->_loadDBInstanceData($sxml->CreateDBInstanceResult->DBInstance);
        }
        return $result;
    }

    /**
     * DeleteDBInstance action
     *
     * The DeleteDBInstance API deletes a previously provisioned RDS instance.
     * A successful response from the web service indicates the request
     * was received correctly. If a final DBSnapshot is requested the status
     * of the RDS instance will be "deleting" until the DBSnapshot is created.
     * DescribeDBInstance is used to monitor the status of this operation.
     * This cannot be canceled or reverted once submitted
     *
     * @param   string       $dBInstanceIdentifier      The DB Instance identifier for the DB Instance to be deleted.
     * @param   bool         $skipFinalSnapshot         optional Determines whether a final DB Snapshot is created
     *                                                  before the DB Instance is deleted
     * @param   string       $finalDBSnapshotIdentifier optional The DBSnapshotIdentifier of the new DBSnapshot
     *                                                  created when SkipFinalSnapshot is set to false
     * @return  DBInstanceData  Returns created DBInstance
     * @throws  ClientException
     * @throws  RdsException
     */
    public function deleteDBInstance($dBInstanceIdentifier, $skipFinalSnapshot = null, $finalDBSnapshotIdentifier = null)
    {
        $result = null;
        $options = array(
            'DBInstanceIdentifier' => (string) $dBInstanceIdentifier,
        );
        if ($skipFinalSnapshot !== null) {
            $options['SkipFinalSnapshot'] = $skipFinalSnapshot ? 'true' : 'false';
        }
        if ($finalDBSnapshotIdentifier !== null) {
            $options['FinalDBSnapshotIdentifier'] = (string) $finalDBSnapshotIdentifier;
            if (isset($options['SkipFinalSnapshot']) && $options['SkipFinalSnapshot'] === 'true') {
                throw new \InvalidArgumentException(sprintf(
                    'Specifiying FinalDBSnapshotIdentifier and also setting the '
                  . 'SkipFinalSnapshot parameter to true is forbidden.'
                ));
            }
        }
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->DeleteDBInstanceResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'delete DBIntance'));
            }
            $result = $this->_loadDBInstanceData($sxml->DeleteDBInstanceResult->DBInstance);
        }
        return $result;
    }

    /**
     * ModifyDBInstance action
     *
     * Modify settings for a DB Instance.
     * You can change one or more database configuration parameters by
     * specifying these parameters and the new values in the request.
     *
     * @param   ModifyDBInstanceRequestData $request Modify DB Instance request object
     * @return  DBInstanceData  Returns modified DBInstance
     * @throws  ClientException
     * @throws  RdsException
     */
    public function modifyDBInstance(ModifyDBInstanceRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();
        if ($this->rds->getApiClientType() === Aws::CLIENT_SOAP) {
            if (isset($options['DBSecurityGroups.member.1']) || isset($options['VpcSecurityGroupIds.member.1'])) {
                foreach ($options as $k => $v) {
                    if (strpos($k, 'DBSecurityGroups.member.') !== false) {
                        $options['DBSecurityGroups']['DBSecurityGroupName'][] = $v;
                        unset($options[$k]);
                    } elseif (strpos($k, 'VpcSecurityGroupIds.member.') !== false) {
                        $options['VpcSecurityGroupIds']['VpcSecurityGroupId'][] = $v;
                        unset($options[$k]);
                    }
                }
            }
        }
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->ModifyDBInstanceResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'modify DBIntance'));
            }
            $result = $this->_loadDBInstanceData($sxml->ModifyDBInstanceResult->DBInstance);
        }
        return $result;
    }

    /**
     * ModifyDBCluster action
     *
     * Modify settings for a DB Cluster.
     * You can change one or more database configuration parameters by
     * specifying these parameters and the new values in the request.
     *
     * @param   ModifyDBClusterRequestData  $request    Modify DB Cluster request object
     *
     * @return  DBClusterData  Returns modified DBCluster
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function modifyDBCluster(ModifyDBClusterRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->ModifyDBClusterResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'modify DBCluster'));
            }
            $result = $this->_loadDBClusterData($sxml->ModifyDBClusterResult->DBCluster);
        }
        return $result;
    }

    /**
     * RebootDBInstance action
     *
     * Reboots a previously provisioned RDS instance. This API results in the application of modified
     * DBParameterGroup parameters with ApplyStatus of pending-reboot to the RDS instance. This action is
     * taken as soon as possible, and results in a momentary outage to the RDS instance during which the RDS
     * instance status is set to rebooting. If the RDS instance is configured for MultiAZ, it is possible that the
     * reboot will be conducted through a failover. A DBInstance event is created when the reboot is completed.
     *
     * @param   string     $dBInstanceIdentifier The DB Instance identifier.
     *                                           This parameter is stored as a lowercase string
     * @param   bool       $forceFailover        optional When true, the reboot will be conducted through
     *                                           a MultiAZ failover. You cannot specify true if the instance
     *                                           is not configured for MultiAZ.
     * @return  DBInstanceData  Returns DBInstance
     * @throws  ClientException
     * @throws  RdsException
     */
    public function rebootDBInstance($dBInstanceIdentifier, $forceFailover = null)
    {
        $options = array(
            'DBInstanceIdentifier' => (string) $dBInstanceIdentifier,
        );
        if ($forceFailover !== null) {
            $options['ForceFailover'] = $forceFailover ? 'true' : 'false';
        }
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->RebootDBInstanceResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'reboot DBIntance'));
            }
            $result = $this->_loadDBInstanceData($sxml->RebootDBInstanceResult->DBInstance);
        }
        return $result;
    }

    /**
     * DescribeDBSecurityGroups action
     *
     * Returns a list of DBSecurityGroup descriptions.
     * If a DBSecurityGroupName is specified, the list will contain
     * only the descriptions of the specified DBSecurityGroup.
     *
     * @param   string     $dBSecurityGroupName optional The name of the DB Security Group to return details for.
     * @param   string     $marker              optional Pagination token, provided by a previous request.
     * @param   string     $maxRecords          optional The maximum number of records to include in the response.
     * @return  DBSecurityGroupList             Returns the list of the DBSecurityGroupData
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeDBSecurityGroups($dBSecurityGroupName = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = array();
        if ($dBSecurityGroupName !== null) {
            $options['DBSecurityGroupName'] = (string) $dBSecurityGroupName;
        }
        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }
        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = new DBSecurityGroupList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($sxml->DescribeDBSecurityGroupsResult->Marker) ?
                (string) $sxml->DescribeDBSecurityGroupsResult->Marker : null;
            if (isset($sxml->DescribeDBSecurityGroupsResult->DBSecurityGroups->DBSecurityGroup)) {
                foreach ($sxml->DescribeDBSecurityGroupsResult->DBSecurityGroups->DBSecurityGroup as $v) {
                    $item = $this->_loadDBSecurityGroupData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }
        return $result;
    }

    /**
     * Loads DBSecurityGroupData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBSecurityGroupData Returns DBSecurityGroupData
     */
    protected function _loadDBSecurityGroupData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $dbSecurityGroupName = (string) $sxml->DBSecurityGroupName;
            $item = $this->rds->getEntityManagerEnabled() ? $this->rds->dbSecurityGroup->get($dbSecurityGroupName) : null;
            if ($item === null) {
                $item = new DBSecurityGroupData();
                $item->setRds($this->rds);
                $bAttach = true;
            } else {
                $item->resetObject();
                $bAttach = false;
            }

            $item->dBSecurityGroupName = $dbSecurityGroupName;
            $item->dBSecurityGroupDescription = $this->exist($sxml->DBSecurityGroupDescription) ?
                (string) $sxml->DBSecurityGroupDescription : null;
            $item->ownerId = $this->exist($sxml->OwnerId) ? (string) $sxml->OwnerId : null;
            $item->vpcId = $this->exist($sxml->VpcId) ? (string) $sxml->VpcId : null;
            $item->eC2SecurityGroups = $this->_loadEC2SecurityGroupList($sxml->EC2SecurityGroups);
            $item->iPRanges = $this->_loadIPRangeList($sxml->IPRanges);

            if ($bAttach && $this->rds->getEntityManagerEnabled()) {
                $this->getEntityManager()->attach($item);
            }
        }
        return $item;
    }

    /**
     * Loads IPRangeList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  IPRangeList Returns IPRangeList
     */
    protected function _loadIPRangeList(\SimpleXMLElement $sxml)
    {
        $list = new IPRangeList();
        $list->setRds($this->rds);
        if (!empty($sxml->IPRange)) {
            foreach ($sxml->IPRange as $v) {
                $item = new IPRangeData();
                $item->setRds($this->rds);
                $item->cIDRIP = $this->exist($v->CIDRIP) ? (string)$v->CIDRIP : null;
                $item->status = $this->exist($v->Status) ? (string)$v->Status : null;
                $list->append($item);
                unset($item);
            }
        }
        return $list;
    }

    /**
     * Loads EC2SecurityGroupList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  EC2SecurityGroupList Returns EC2SecurityGroupList
     */
    protected function _loadEC2SecurityGroupList(\SimpleXMLElement $sxml)
    {
        $list = new EC2SecurityGroupList();
        $list->setRds($this->rds);
        if (!empty($sxml->EC2SecurityGroup)) {
            foreach ($sxml->EC2SecurityGroup as $v) {
                $item = new EC2SecurityGroupData();
                $item->setRds($this->rds);
                $item->eC2SecurityGroupId = $this->exist($v->EC2SecurityGroupId) ?
                    (string)$v->EC2SecurityGroupId : null;
                $item->eC2SecurityGroupName = $this->exist($v->EC2SecurityGroupName) ?
                    (string)$v->EC2SecurityGroupName : null;
                $item->eC2SecurityGroupOwnerId = $this->exist($v->EC2SecurityGroupOwnerId) ?
                    (string)$v->EC2SecurityGroupOwnerId : null;
                $item->status = $this->exist($v->Status) ? (string)$v->Status : null;
                $list->append($item);
                unset($item);
            }
        }
        return $list;
    }

    /**
     * DeleteDBSecurityGroup action
     *
     * Deletes a DB Security Group.
     * Note! The specified DB Security Group must not be associated with any DB Instances.
     *
     * @param   string     $dBSecurityGroupName The Name of the DB security group to delete.
     * @return  bool       Returns true on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function deleteDBSecurityGroup($dBSecurityGroupName)
    {
        $result = false;
        $options = array(
            'DBSecurityGroupName' => (string) $dBSecurityGroupName
        );
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = true;
            if ($this->rds->getEntityManagerEnabled() &&
                null !== ($item = $this->rds->dbSecurityGroup->get($options['DBSecurityGroupName']))) {
                $this->getEntityManager()->detach($item);
            }
        }
        return $result;
    }

    /**
     * CreateDBSecurityGroup
     *
     * Creates a new DB Security Group. DB Security Groups control access to a DB Instance
     *
     * @param   string     $name        The name for the DB Security Group. This value is stored as a lowercase string
     * @param   string     $description The description for the DB Security Group
     * @return  DBSecurityGroupData     Returns DBSecurityGroupData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function createDBSecurityGroup($name, $description)
    {
        $result = null;
        $options = array(
            'DBSecurityGroupDescription' => (string) $description,
            'DBSecurityGroupName'        => (string) $name,
        );
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->CreateDBSecurityGroupResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'create DBSecurityGroup'));
            }
            $result = $this->_loadDBSecurityGroupData($sxml->CreateDBSecurityGroupResult->DBSecurityGroup);
        }
        return $result;
    }

    /**
     * AuthorizeDBSecurityGroupIngress action
     *
     * Enables ingress to a DBSecurityGroup using one of two forms of authorization.
     * First, EC2 or VPC Security Groups can be added to the DBSecurityGroup
     * if the application using the database is running on EC2 or VPC instances.
     * Second, IP ranges are available if the application accessing your database is running on
     * the Internet. Required parameters for this API are one of CIDR range,
     * EC2SecurityGroupId for VPC, or (EC2SecurityGroupOwnerId and either EC2SecurityGroupName or
     * EC2SecurityGroupId for non-VPC).
     *
     * Note! You cannot authorize ingress from an EC2 security group in one Region to an Amazon RDS DB
     * Instance in another.You cannot authorize ingress from a VPC security group in one VPC to an
     * Amazon RDS DB Instance in another.
     *
     * @param   DBSecurityGroupIngressRequestData $request
     * @return  DBSecurityGroupData     Returns DBSecurityGroupData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function authorizeDBSecurityGroupIngress(DBSecurityGroupIngressRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->AuthorizeDBSecurityGroupIngressResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'authorize DBSecurityGroupIngress'));
            }
            $result = $this->_loadDBSecurityGroupData($sxml->AuthorizeDBSecurityGroupIngressResult->DBSecurityGroup);
        }
        return $result;
    }

    /**
     * RevokeDBSecurityGroupIngress action
     *
     * Revokes ingress from a DBSecurityGroup for previously
     * authorized IP ranges or EC2 or VPC Security Groups.
     * Required parameters for this API are one of CIDRIP,
     * EC2SecurityGroupId for VPC, or (EC2SecurityGroupOwnerId and
     * either EC2SecurityGroupName or EC2SecurityGroupId).
     *
     * @param   DBSecurityGroupIngressRequestData $request
     * @return  DBSecurityGroupData     Returns DBSecurityGroupData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function revokeDBSecurityGroupIngress(DBSecurityGroupIngressRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();
        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->RevokeDBSecurityGroupIngressResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'revoke DBSecurityGroupIngress'));
            }
            $result = $this->_loadDBSecurityGroupData($sxml->RevokeDBSecurityGroupIngressResult->DBSecurityGroup);
        }
        return $result;
    }

    /**
     * DescribeDBParameterGroups action
     *
     * Returns a list of DBParameterGroup descriptions.
     * If a DBParameterGroupName is specified, the list will contain only the description of the specified DBParameterGroup.
     *
     * @param   string     $dBParameterGroupName optional The name of a specific DB Parameter Group to return details for.
     * @param   string     $marker               optional An optional pagination token provided by a previous
     *                                           DescribeDBParameterGroups request. If this parameter is specified, the response includes
     *                                           only records beyond the marker, up to the value specified by MaxRecords.
     * @param   int        $maxRecords           optional The maximum number of records to include in the response.
     *                                           If more records exist than the specified MaxRecords value,
     *                                           a pagination token called a marker is included in the response so that the
     *                                           remaining results may be retrieved.
     * @return  DBParameterGroupList             Returns DBParameterGroupList on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeDBParameterGroups($dBParameterGroupName = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = array();
        $action = ucfirst(__FUNCTION__);
        if ($dBParameterGroupName !== null) {
            $options['DBParameterGroupName'] = (string) $dBParameterGroupName;
        }
        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }
        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = new DBParameterGroupList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;
            if (isset($ptr->DBParameterGroups->DBParameterGroup)) {
                foreach ($ptr->DBParameterGroups->DBParameterGroup as $v) {
                    $item = $this->_loadDBParameterGroupData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }
        return $result;
    }

    /**
     * Loads DBParameterGroupData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBParameterGroupData Returns DBParameterGroupData
     */
    protected function _loadDBParameterGroupData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $dbParameterGroupName = (string) $sxml->DBParameterGroupName;
            $item = $this->rds->getEntityManagerEnabled() ? $this->rds->dbParameterGroup->get($dbParameterGroupName) : null;
            if ($item === null) {
                $item = new DBParameterGroupData(
                    $dbParameterGroupName,
                    ($this->exist($sxml->DBParameterGroupFamily) ? (string) $sxml->DBParameterGroupFamily : null),
                    ($this->exist($sxml->Description) ? (string) $sxml->Description : null)
                );
                $item->setRds($this->rds);
                $bAttach = true;
            } else {
                $item->resetObject();
                $item->dBParameterGroupName = $dbParameterGroupName;
                $item->dBParameterGroupFamily = $this->exist($sxml->DBParameterGroupFamily) ?
                    (string) $sxml->DBParameterGroupFamily : null;
                $item->description = $this->exist($sxml->Description) ? (string) $sxml->Description : null;
                $bAttach = false;
            }

            if ($bAttach && $this->rds->getEntityManagerEnabled()) {
                $this->getEntityManager()->attach($item);
            }
        }
        return $item;
    }

    /**
     * CreateDBParameterGroup action
     *
     * Creates a new DB Parameter Group.
     * A DB Parameter Group is initially created with the default parameters for the database engine used by
     * the DB Instance. To provide custom values for any of the parameters, you must modify the group after
     * creating it using ModifyDBParameterGroup. Once you've created a DB Parameter Group, you need to
     * associate it with your DB Instance using ModifyDBInstance. When you associate a new DB Parameter
     * Group with a running DB Instance, you need to reboot the DB Instance for the new DB Parameter Group
     * and associated settings to take effect.
     *
     * @param   DBParameterGroupData $request DBParameterGroupData object to create
     * @return  DBParameterGroupData Returns DBParameterGroupData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function createDBParameterGroup(DBParameterGroupData $request)
    {
        $result = null;
        $options = $request->getQueryArray();
        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = $this->_loadDBParameterGroupData($ptr->DBParameterGroup);
        }
        return $result;
    }

    /**
     * DeleteDBParameterGroup action
     *
     * Deletes a specified DBParameterGroup. The DBParameterGroup cannot
     * be associated with any RDS instances to be deleted.
     * Note! The specified DB Parameter Group cannot be associated with any DB Instances
     *
     * @param   string     $dBParameterGroupName The name of the DB Parameter Group
     * @return  bool       Returns true on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function deleteDBParameterGroup($dBParameterGroupName)
    {
        $result = false;
        $options = array(
            'DBParameterGroupName' => (string) $dBParameterGroupName,
        );
        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = true;
            if ($this->rds->getEntityManagerEnabled() &&
                null !== ($item = $this->rds->dbParameterGroup->get($options['DBParameterGroupName']))) {
                $this->getEntityManager()->detach($item);
            }
        }
        return $result;
    }

    /**
     * ModifyDBParameterGroup action
     *
     * Modifies the parameters of a DBParameterGroup. To modify more than one parameter submit a list of
     * the following: ParameterName, ParameterValue, and ApplyMethod. A maximum of 20 parameters can
     * be modified in a single request.
     *
     * Note! The apply-immediate method can be used only for dynamic parameters; the pending-reboot
     * method can be used with MySQL and Oracle DB Instances for either dynamic or static parameters.
     * For Microsoft SQL Server DB Instances, the pending-reboot method can be used only for
     * static parameters.
     *
     * @param   string        $dBParameterGroupName The name of DB Parameter Group to modify.
     * @param   ParameterList $parameters           An list of parameter names, values, and the apply method
     *                                              for the parameter update. At least one parameter name, value,
     *                                              and apply method must be supplied;
     *                                              subsequent arguments are optional.
     *                                              A maximum of 20 parameters may be modified in a single request.
     *                                              Valid Values (for the application method): immediate | pending-reboot
     * @return  string        Returns DBParameterGroupName on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function modifyDBParameterGroup($dBParameterGroupName, ParameterList $parameters)
    {
        $result = false;
        $options = array(
            'DBParameterGroupName' => (string) $dBParameterGroupName,
        );
        if ($this->rds->getApiClientType() == Aws::CLIENT_SOAP) {
            $parameter = array();
            foreach ($parameters as $v) {
                $parameter[] = $v->getQueryArray();
            }
            $options['Parameters']['Parameter'] = $parameter;
        } else {
            $options = array_merge($options, array_filter($parameters->getQueryArray('Parameters'), function($v){
                return $v !== null;
            }));
        }

        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = (string) $ptr->DBParameterGroupName;
        }
        return $result;
    }

    /**
     * ResetDBParameterGroup action
     *
     * Modifies the parameters of a DBParameterGroup to the engine/system default value.
     * To reset specific parameters submit a list of the following: ParameterName and ApplyMethod.
     * To reset the entire DBParameterGroup specify the DBParameterGroup name and ResetAllParameters parameters.
     * When resetting the entire group, dynamic parameters are updated immediately and static parameters are set
     * to pending-reboot to take effect on the next DB instance restart or RebootDBInstance request.
     *
     * @param   string        $dBParameterGroupName The name of DB Parameter Group to modify.
     * @param   ParameterList $parameters           optional An list of parameter names, values, and the apply method
     *                                              for the parameter update. At least one parameter name, value,
     *                                              and apply method must be supplied;
     *                                              subsequent arguments are optional.
     *                                              A maximum of 20 parameters may be modified in a single request.
     *                                              Valid Values (for the application method): immediate | pending-reboot
     * @param   bool          $resetAllParameters   optional Specifies whether (true) or not (false) to reset all parameters
     *                                              in the DB Parameter Group to default values.
     * @return  string        Returns DBParameterGroupName on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function resetDBParameterGroup($dBParameterGroupName, ParameterList $parameters = null, $resetAllParameters = null)
    {
        $result = false;
        $options = array(
            'DBParameterGroupName' => (string) $dBParameterGroupName
        );
        if ($parameters !== null) {
            if ($this->rds->getApiClientType() == Aws::CLIENT_SOAP) {
                $parameter = array();
                foreach ($parameters as $v) {
                    $parameter[] = $v->getQueryArray();
                }
                $options['Parameters']['Parameter'] = $parameter;
            } else {
                $options = array_merge($options, array_filter($parameters->getQueryArray('Parameters'), function($v){
                    return $v !== null;
                }));
            }
        } elseif ($resetAllParameters !== null) {
            $options['ResetAllParameters'] = ($resetAllParameters ? 'true' : 'false');
        }
        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = (string) $ptr->DBParameterGroupName;
        }
        return $result;
    }

    /**
     * DescribeDBParameters action
     *
     * Returns the detailed parameter list for a particular DBParameterGroup.
     *
     * @param   string     $dBParameterGroupName The name of the DB Parameter Group.
     * @param   string     $source               optional The parameter types to return.
     * @param   string     $marker               optional An optional pagination token provided by a previous
     *                                           DescribeDBParameterGroups request. If this parameter is specified, the response includes
     *                                           only records beyond the marker, up to the value specified by MaxRecords.
     * @param   int        $maxRecords           optional The maximum number of records to include in the response.
     *                                           If more records exist than the specified MaxRecords value,
     *                                           a pagination token called a marker is included in the response so that the
     *                                           remaining results may be retrieved.
     * @return  ParameterList Returns ParameterList on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeDBParameters($dBParameterGroupName, $source = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = array(
            'DBParameterGroupName' => (string) $dBParameterGroupName,
        );
        $action = ucfirst(__FUNCTION__);
        if ($source !== null) {
            $options['Source'] = (string) $source;
        }
        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }
        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = new ParameterList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;
            if (isset($ptr->Parameters->Parameter)) {
                foreach ($ptr->Parameters->Parameter as $v) {
                    $item = $this->_loadParameterData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }
        return $result;
    }

    /**
     * Loads ParameterData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  ParameterData Returns ParameterData
     */
    protected function _loadParameterData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new ParameterData(
                (string) $sxml->ParameterName,
                ($this->exist($sxml->ApplyMethod) ? (string) $sxml->ParameterName : null),
                ($this->exist($sxml->ParameterValue) ? (string) $sxml->ParameterValue : null)
            );
            $item->allowedValues = $this->exist($sxml->AllowedValues) ? (string) $sxml->AllowedValues : null;
            $item->applyType = $this->exist($sxml->ApplyType) ? (string) $sxml->ApplyType : null;
            $item->dataType = $this->exist($sxml->DataType) ? (string) $sxml->DataType : null;
            $item->description = $this->exist($sxml->Description) ? (string) $sxml->Description : null;
            $item->isModifiable = $this->exist($sxml->IsModifiable) ? (((string)$sxml->IsModifiable) == 'true') : null;
            $item->minimumEngineVersion = $this->exist($sxml->MinimumEngineVersion) ?
                (string) $sxml->MinimumEngineVersion : null;
            $item->source = $this->exist($sxml->Source) ? (string) $sxml->Source : null;
        }
        return $item;
    }

    /**
     * DescribeDBSnapshots action
     *
     * Returns the detailed parameter list for a particular DBParameterGroup.
     *
     * @param   string     $dBParameterGroupName The name of the DB Parameter Group.
     * @param   string     $source               optional The parameter types to return.
     * @param   string     $marker               optional An optional pagination token provided by a previous
     *                                           DescribeDBParameterGroups request. If this parameter is specified, the response includes
     *                                           only records beyond the marker, up to the value specified by MaxRecords.
     * @param   int        $maxRecords           optional The maximum number of records to include in the response.
     *                                           If more records exist than the specified MaxRecords value,
     *                                           a pagination token called a marker is included in the response so that the
     *                                           remaining results may be retrieved.
     * @return  DBSnapshotList Returns DBSnapshotList on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeDBSnapshots($dBInstanceIdentifier = null, $dBSnapshotIdentifier = null, $snapshotType = null,
                                        $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = array();
        $action = ucfirst(__FUNCTION__);
        if ($dBInstanceIdentifier !== null) {
            $options['DBInstanceIdentifier'] = (string) $dBInstanceIdentifier;
        }
        if ($dBSnapshotIdentifier !== null) {
            $options['DBSnapshotIdentifier'] = (string) $dBSnapshotIdentifier;
        }
        if ($snapshotType !== null) {
            $options['SnapshotType'] = (string) $snapshotType;
        }
        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }
        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = new DBSnapshotList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;
            if (isset($ptr->DBSnapshots->DBSnapshot)) {
                foreach ($ptr->DBSnapshots->DBSnapshot as $v) {
                    $item = $this->_loadDBSnapshotData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }
        return $result;
    }

    /**
     * Loads DBSnapshotData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBSnapshotData Returns DBSnapshotData
     */
    protected function _loadDBSnapshotData(\SimpleXMLElement $sxml)
    {
        $item = null;

        if ($this->exist($sxml)) {
            $dBSnapshotIdentifier = (string) $sxml->DBSnapshotIdentifier;
            $item = $this->rds->getEntityManagerEnabled() ? $this->rds->dbSnapshot->get($dBSnapshotIdentifier) : null;

            if ($item === null) {
                $item = new DBSnapshotData();
                $item->setRds($this->rds);
                $bAttach = true;
            } else {
                $item->resetObject();
                $bAttach = false;
            }

            $item->dBSnapshotIdentifier = $dBSnapshotIdentifier;
            $item->allocatedStorage = $this->exist($sxml->AllocatedStorage) ? (int) $sxml->AllocatedStorage : null;
            $item->availabilityZone = $this->exist($sxml->AvailabilityZone) ? (string) $sxml->AvailabilityZone : null;
            $item->dBInstanceIdentifier = $this->exist($sxml->DBInstanceIdentifier) ?
                (string) $sxml->DBInstanceIdentifier : null;
            $item->engine = $this->exist($sxml->Engine) ? (string) $sxml->Engine : null;
            $item->engineVersion = $this->exist($sxml->EngineVersion) ? (string) $sxml->EngineVersion : null;
            $item->instanceCreateTime = $this->exist($sxml->InstanceCreateTime) ?
                new DateTime((string)$sxml->InstanceCreateTime, new DateTimeZone('UTC')) : null;
            $item->snapshotCreateTime = $this->exist($sxml->SnapshotCreateTime) ?
                new DateTime((string)$sxml->SnapshotCreateTime, new DateTimeZone('UTC')) : null;
            $item->iops = $this->exist($sxml->Iops) ? (int) $sxml->Iops : null;
            $item->port = $this->exist($sxml->Port) ? (int) $sxml->Port : null;
            $item->licenseModel = $this->exist($sxml->LicenseModel) ? (string) $sxml->LicenseModel : null;
            $item->masterUsername = $this->exist($sxml->MasterUsername) ? (string) $sxml->MasterUsername : null;
            $item->snapshotType = $this->exist($sxml->SnapshotType) ? (string) $sxml->SnapshotType : null;
            $item->status = $this->exist($sxml->Status) ? (string) $sxml->Status : null;
            $item->storageType = $this->exist($sxml->StorageType) ? (string) $sxml->StorageType : null;
            $item->vpcId = $this->exist($sxml->VpcId) ? (string) $sxml->VpcId : null;

            if ($bAttach && $this->rds->getEntityManagerEnabled()) {
                $this->getEntityManager()->attach($item);
            }
        }
        return $item;
    }

    /**
     * CreateDBSnapshot
     *
     * Creates a DBSnapshot. The source DBInstance must be in "available" state.
     *
     * @param   string     $dBInstanceIdentifier The DB Instance Identifier
     * @param   string     $dBSnapshotIdentifier The identifier for the DB Snapshot.
     * @return  DBSnapshotData Returns DBSnapshotData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function createDBSnapshot($dBInstanceIdentifier, $dBSnapshotIdentifier)
    {
        $result = null;
        $options = array(
            'DBInstanceIdentifier' => (string) $dBInstanceIdentifier,
            'DBSnapshotIdentifier' => (string) $dBSnapshotIdentifier,
        );
        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = $this->_loadDBSnapshotData($ptr->DBSnapshot);
        }
        return $result;
    }

    /**
     * DeleteDBSnapshot action
     *
     * Deletes a DBSnapshot.
     * Note! The DBSnapshot must be in the available state to be deleted
     *
     * @param   string     $dBSnapshotIdentifier The Identifier for the DB Snapshot to delete.
     * @return  DBSnapshotData Returns DBSnapshotData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function deleteDBSnapshot($dBSnapshotIdentifier)
    {
        $result = null;
        $options = array(
            'DBSnapshotIdentifier' => (string) $dBSnapshotIdentifier,
        );
        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = $this->_loadDBSnapshotData($ptr->DBSnapshot);
        }
        return $result;
    }

    /**
     * RestoreDBInstanceFromDBSnapshot action
     *
     * Creates a new DB Instance from a DB snapshot.The target database is created from the source database
     * restore point with the same configuration as the original source database, except that the new RDS
     * instance is created with the default security group.
     *
     * @param   RestoreDBInstanceFromDBSnapshotRequestData $request The request object.
     * @return  DBInstanceData Returns DBInstanceData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function restoreDBInstanceFromDBSnapshot(RestoreDBInstanceFromDBSnapshotRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();
        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = $this->_loadDBInstanceData($ptr->DBInstance);
        }
        return $result;
    }

    /**
     * DescribeEvents action
     *
     * Returns events related to DB instances, DB security groups, DB Snapshots, and DB parameter groups
     * for the past 14 days. Events specific to a particular DB Iinstance, DB security group, DB Snapshot, or
     * DB parameter group can be obtained by providing the source identifier as a parameter. By default, the
     * past hour of events are returned.
     *
     * @param   DescribeEventRequestData $request optional Request object.
     * @return  EventList Returns EventList on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeEvents(DescribeEventRequestData $request = null)
    {
        $result = null;
        if ($request !== null) {
            $options = $request->getQueryArray();
            if ($this->rds->getApiClientType() == Aws::CLIENT_SOAP) {
                if (isset($options['EventCategories.member.1'])) {
                    foreach ($options as $k => $v) {
                        if (strpos($k, 'EventCategories.member.') === 0) {
                            $options['EventCategories']['EventCategory'][] = $v;
                            unset($options[$k]);
                        }
                    }
                }
            }
        } else {
            $options = array();
        }
        $action = ucfirst(__FUNCTION__);
        $response = $this->client->call($action, $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }
            $ptr = $sxml->{$action . 'Result'};
            $result = new EventList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;
            if (isset($ptr->Events->Event)) {
                foreach ($ptr->Events->Event as $v) {
                    $item = $this->_loadEventData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }
        return $result;
    }

    /**
     * Loads EventData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  EventData Returns EventData
     */
    protected function _loadEventData(\SimpleXMLElement $sxml)
    {
        $item = null;
        if ($this->exist($sxml)) {
            $item = new EventData();
            $item->setRds($this->rds);
            $item->date = new DateTime((string)$sxml->Date, new DateTimeZone('UTC'));
            $item->message = (string)$sxml->Message;
            $item->sourceIdentifier = $this->exist($sxml->SourceIdentifier) ? (string) $sxml->SourceIdentifier : null;
            $item->sourceType = $this->exist($sxml->SourceType) ? (string) $sxml->SourceType : null;
            if (!empty($sxml->EventCategories->EventCategory)) {
                $item->eventCategories = array();
                foreach ($sxml->EventCategories->EventCategory as $v) {
                    $item->eventCategories[] = (string) $v;
                }
            }
        }
        return $item;
    }

    /**
     * Creates a new DB subnet group.
     * DB subnet groups must contain at least one subnet in at least two AZs in the region.
     *
     * @param CreateDBSubnetGroupRequestData $request
     * @return DBSubnetGroupData
     * @throws RdsException
     */
    public function createDBSubnetGroup(CreateDBSubnetGroupRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();

        if ($this->rds->getApiClientType() === Aws::CLIENT_SOAP) {
            if (isset($options['SubnetIds.member.1'])) {
                foreach ($options as $k => $v) {
                    if (strpos($k, 'SubnetIds.member.') !== false) {
                        $options['SubnetIds']['SubnetId'][] = $v;
                        unset($options[$k]);
                    }
                }
            }
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->CreateDBSubnetGroupResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'create DBSubnet group'));
            }

            $result = $this->_loadDBSubnetGroupData($sxml->CreateDBSubnetGroupResult->DBSubnetGroup);
        }

        return $result;
    }

    /**
     * Loads DBSubnetGroupData from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return null|DBSubnetGroupData
     */
    protected function _loadDBSubnetGroupData(\SimpleXMLElement $sxml)
    {
        $item = null;

        if ($this->exist($sxml)) {
            $item = new DBSubnetGroupData();
            $item->setRds($this->rds);
            $item->dBSubnetGroupDescription = $this->exist($sxml->DBSubnetGroupDescription) ? (string) $sxml->DBSubnetGroupDescription : null;
            $item->dBSubnetGroupName = $this->exist($sxml->DBSubnetGroupName) ? (string) $sxml->DBSubnetGroupName : null;
            $item->subnetGroupStatus = $this->exist($sxml->SubnetGroupStatus) ? (string) $sxml->SubnetGroupStatus : null;
            $item->vpcId = $this->exist($sxml->VpcId) ? (string) $sxml->VpcId : null;
            $item->subnets = $this->exist($sxml->Subnets) ? $this->_loadSubnetsList($sxml->Subnets) : null;
        }

        return $item;
    }

    /**
     * Loads SubnetList from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  SubnetList Returns SubnetList
     */
    protected function _loadSubnetsList(\SimpleXMLElement $sxml)
    {
        $list = new SubnetList();
        $list->setRds($this->rds);

        if (!empty($sxml->Subnet)) {
            foreach ($sxml->Subnet as $v) {
                $item = new SubnetData();
                $item->subnetStatus = $this->exist($v->SubnetStatus) ? (string)$v->SubnetStatus : null;
                $item->subnetIdentifier = $this->exist($v->SubnetIdentifier) ? (string)$v->SubnetIdentifier : null;
                $item->subnetAvailabilityZone = $this->exist($v->SubnetAvailabilityZone) ? $this->_loadAvailabilityZoneData($v->SubnetAvailabilityZone) : null;
                $item->setRds($this->rds);
                $list->append($item);
                unset($item);
            }
        }
        return $list;
    }

    /**
     * Loads AvailabilityZoneData from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return AvailabilityZoneData
     */
    protected function _loadAvailabilityZoneData(\SimpleXMLElement $sxml)
    {
        $item = new AvailabilityZoneData(
            $this->exist($sxml->Name) ? (string)$sxml->Name : null,
            $this->exist($sxml->ProvisionedIopsCapable) ? (((string)$sxml->ProvisionedIopsCapable) == 'true') : null
        );
        $item->setRds($this->rds);

        return $item;
    }

    /**
     * Modifies an existing DB subnet group.
     * DB subnet groups must contain at least one subnet in at least two AZs in the region.
     *
     * @param ModifyDBSubnetGroupRequestData $request
     * @return DBSubnetGroupData
     * @throws RdsException
     */
    public function modifyDBSubnetGroup(ModifyDBSubnetGroupRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();

        if ($this->rds->getApiClientType() === Aws::CLIENT_SOAP) {
            if (isset($options['SubnetIds.member.1'])) {
                foreach ($options as $k => $v) {
                    if (strpos($k, 'SubnetIds.member.') !== false) {
                        $options['SubnetIds']['SubnetId'][] = $v;
                        unset($options[$k]);
                    }
                }
            }
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->ModifyDBSubnetGroupResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'modify DBSubnet group'));
            }

            $result = $this->_loadDBSubnetGroupData($sxml->ModifyDBSubnetGroupResult->DBSubnetGroup);
        }

        return $result;
    }

    /**
     * Returns a list of DBSubnetGroup descriptions.
     * If a DBSubnetGroupName is specified, the list will contain only the descriptions of the specified DBSubnetGroup.
     *
     * @param   string     $dBSubnetGroupName   optional Subnet group name
     * @param   string     $marker              optional Pagination token, provided by a previous request.
     * @param   string     $maxRecords          optional The maximum number of records to include in the response.
     * @return  DBSubnetGroupList               Returns the list of the DBSubnetGroupData
     * @throws  RdsException
     */
    public function describeDBSubnetGroups($dBSubnetGroupName = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = [];
        $action = ucfirst(__FUNCTION__);

        if ($dBSubnetGroupName !== null) {
            $options['DBSubnetGroupName'] = (string) $dBSubnetGroupName;
        }

        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }

        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }

        $response = $this->client->call($action, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }

            $ptr = $sxml->{$action . 'Result'};
            $result = new DBSubnetGroupList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;

            if (isset($ptr->DBSubnetGroups->DBSubnetGroup)) {
                foreach ($ptr->DBSubnetGroups->DBSubnetGroup as $v) {
                    $item = $this->_loadDBSubnetGroupData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }

        return $result;
    }

    /**
     * DeleteDBSubnetGroup action
     * Deletes a DB subnet group.
     *
     * @param   string  $dBSubnetGroupName  Subnet group name
     * @return  bool       Returns true on success or throws an exception.
     */
    public function deleteDBSubnetGroup($dBSubnetGroupName)
    {
        $result = false;
        $options = [
            'DBSubnetGroupName' => (string) $dBSubnetGroupName
        ];

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = true;
        }

        return $result;
    }

    /**
     * Returns a list of the available DB engines.
     *
     * @param DescribeDBEngineVersionsData $request
     * @param string                       $marker
     * @param int                          $maxRecords
     * @return DBEngineVersionList
     * @throws RdsException
     */
    public function describeDBEngineVersions(DescribeDBEngineVersionsData $request = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = !empty($request) ? $request->getQueryArray() : [];
        $action = ucfirst(__FUNCTION__);

        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }

        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }

        $response = $this->client->call($action, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }

            $ptr = $sxml->{$action . 'Result'};
            $result = new DBEngineVersionList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;

            if (isset($ptr->DBEngineVersions->DBEngineVersion)) {
                foreach ($ptr->DBEngineVersions->DBEngineVersion as $v) {
                    $item = $this->_loadDBEngineVersionData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }

        return $result;
    }

    /**
     * Loads DBEngineVersionData from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return DBEngineVersionData
     */
    protected function _loadDBEngineVersionData(\SimpleXMLElement $sxml)
    {
        $item = null;

        if ($this->exist($sxml)) {
            $item = new DBEngineVersionData();
            $item->setRds($this->rds);
            $item->dBEngineDescription = $this->exist($sxml->DBEngineDescription) ? (string) $sxml->DBEngineDescription : null;
            $item->dBEngineVersionDescription = $this->exist($sxml->DBEngineVersionDescription) ? (string) $sxml->DBEngineVersionDescription : null;
            $item->dBParameterGroupFamily = $this->exist($sxml->DBParameterGroupFamily) ? (string) $sxml->DBParameterGroupFamily : null;
            $item->engine = $this->exist($sxml->Engine) ? (string) $sxml->Engine : null;
            $item->engineVersion = $this->exist($sxml->EngineVersion) ? (string) $sxml->EngineVersion : null;

            $dfc = null;

            if ($this->exist($sxml->DefaultCharacterSet)) {
                $dfc = new CharacterSetData();
                $dfc->characterSetName = $this->exist($sxml->DefaultCharacterSet->CharacterSetName) ? (string) $sxml->DefaultCharacterSet->CharacterSetName : null;
                $dfc->characterSetDescription = $this->exist($sxml->DefaultCharacterSet->CharacterSetDescription) ? (string) $sxml->DefaultCharacterSet->CharacterSetDescription : null;
            }

            $item->defaultCharacterSet = $dfc;

            $scs = null;

            if ($this->exist($sxml->SupportedCharacterSets->CharacterSet)) {
                $scs = new CharacterSetList();
                $scs->setRds($this->rds);

                foreach ($sxml->SupportedCharacterSets->CharacterSet as $v) {
                    $cs = new CharacterSetData();
                    $cs->characterSetName = $this->exist($v->CharacterSetName) ? (string) $v->CharacterSetName : null;
                    $cs->characterSetDescription = $this->exist($v->CharacterSetDescription) ? (string) $v->CharacterSetDescription : null;
                }
            }

            $item->supportedCharacterSets = $scs;
        }

        return $item;
    }

    /**
     * Describes the available option groups.
     *
     * @param string $engineName
     * @param string $majorEngineVersion
     * @param string $marker
     * @param int    $maxRecords
     * @return OptionGroupsList
     * @throws RdsException
     */
    public function describeOptionGroups($engineName = null, $majorEngineVersion = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $action = ucfirst(__FUNCTION__);

        if ($engineName !== null) {
            $options['EngineName'] = (string) $engineName;
        }

        if ($majorEngineVersion !== null) {
            $options['MajorEngineVersion'] = (string) $majorEngineVersion;
        }

        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }

        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }

        $response = $this->client->call($action, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }

            $ptr = $sxml->{$action . 'Result'};
            $result = new OptionGroupsList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;

            if (isset($ptr->OptionGroupsList->OptionGroup)) {
                foreach ($ptr->OptionGroupsList->OptionGroup as $v) {
                    $item = $this->_loadOptionGroupData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }

        return $result;
    }

    /**
     * Loads OptionGroupData from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return OptionGroupData
     */
    protected function _loadOptionGroupData(\SimpleXMLElement $sxml)
    {
        $item = new OptionGroupData();
        $item->setRds($this->rds);
        $item->allowsVpcAndNonVpcInstanceMemberships = $this->exist($sxml->AllowsVpcAndNonVpcInstanceMemberships)
            ? (((string)$sxml->AllowsVpcAndNonVpcInstanceMemberships) == 'true')
            : null;
        $item->engineName = $this->exist($sxml->EngineName) ? (string) $sxml->EngineName : null;
        $item->majorEngineVersion = $this->exist($sxml->MajorEngineVersion) ? (string) $sxml->MajorEngineVersion : null;
        $item->optionGroupDescription = $this->exist($sxml->OptionGroupDescription) ? (string) $sxml->OptionGroupDescription : null;
        $item->optionGroupName = $this->exist($sxml->OptionGroupName) ? (string) $sxml->OptionGroupName : null;
        $item->vpcId = $this->exist($sxml->VpcId) ? (string) $sxml->VpcId : null;

        $item->options = $this->_loadOptionList($sxml->Options);

        return $item;
    }

    /**
     * Loads OptionList from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return OptionList
     */
    protected function _loadOptionList(\SimpleXMLElement $sxml)
    {
        $result = new OptionList();
        $result->setRds($this->rds);

        if (isset($sxml->Option)) {
            foreach ($sxml->Option as $v) {
                $item = $this->_loadOptionData($v);
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Loads OptionData from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return OptionData
     */
    protected function _loadOptionData(\SimpleXMLElement $sxml)
    {
        $item = new OptionData();
        $item->setRds($this->rds);
        $item->permanent = $this->exist($sxml->Permanent) ? (((string)$sxml->Permanent) == 'true') : null;
        $item->optionDescription = $this->exist($sxml->OptionDescription) ? (string) $sxml->OptionDescription : null;
        $item->optionName = $this->exist($sxml->OptionName) ? (string) $sxml->OptionName : null;
        $item->persistent = $this->exist($sxml->Persistent) ? (((string)$sxml->Persistent) == 'true') : null;
        $item->port = $this->exist($sxml->Port) ? (int) $sxml->Port : null;

        $item->dBSecurityGroupMemberships = $this->_loadVpcSecurityGroupMembershipList($sxml->VpcSecurityGroupMemberships);
        $item->vpcSecurityGroupMemberships = $this->_loadDBSecurityGroupMembershipList($sxml->DBSecurityGroupMemberships);
        $item->optionSettings = $this->_loadOptionSettingList($sxml->OptionSettings);

        return $item;
    }

    /**
     * Loads OptionSettingList from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return OptionSettingList
     */
    protected function _loadOptionSettingList(\SimpleXMLElement $sxml)
    {
        $result = new OptionSettingList();
        $result->setRds($this->rds);

        if (isset($sxml->OptionSetting)) {
            foreach ($sxml->OptionSetting as $v) {
                $item = new OptionSettingData();
                $item->setRds($this->rds);
                $item->isCollection = $this->exist($v->IsCollection) ? (((string)$v->IsCollection) == 'true') : null;
                $item->isModifiable = $this->exist($v->IsModifiable) ? (((string)$v->IsModifiable) == 'true') : null;
                $item->allowedValues = $this->exist($v->AllowedValues) ? (string) $v->AllowedValues : null;
                $item->applyType = $this->exist($v->ApplyType) ? (string) $v->ApplyType : null;
                $item->defaultValue = $this->exist($v->DefaultValue) ? (string) $v->DefaultValue : null;
                $item->description = $this->exist($v->Description) ? (string) $v->Description : null;
                $item->name = $this->exist($v->Name) ? (string) $v->Name : null;
                $item->value = $this->exist($v->Value) ? (string) $v->Value : null;
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Creates a DB instance that acts as a Read Replica of a source DB instance.
     *
     * @param CreateDBInstanceReadReplicaData $request
     * @return DBInstanceData
     * @throws RdsException
     */
    public function createDBInstanceReadReplica(CreateDBInstanceReadReplicaData $request)
    {
        $result = null;
        $options = $request->getQueryArray();

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->CreateDBInstanceReadReplicaResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'create DBInstance Read Replica'));
            }
            $result = $this->_loadDBInstanceData($sxml->CreateDBInstanceReadReplicaResult->DBInstance);
        }

        return $result;
    }

    /**
     * Promotes a Read Replica DB instance to a standalone DB instance.
     *
     * @param string    $dBInstanceIdentifier
     * @param int       $backupRetentionPeriod
     * @param string    $preferredBackupWindow
     * @return DBInstanceData
     * @throws RdsException
     */
    public function promoteReadReplica($dBInstanceIdentifier, $backupRetentionPeriod = null, $preferredBackupWindow = null)
    {
        $result = null;

        if ($dBInstanceIdentifier !== null) {
            $options['DBInstanceIdentifier'] = (string) $dBInstanceIdentifier;
        }

        if ($backupRetentionPeriod !== null) {
            $options['BackupRetentionPeriod'] = (int) $backupRetentionPeriod;
        }

        if ($preferredBackupWindow !== null) {
            $options['PreferredBackupWindow'] = (string) $preferredBackupWindow;
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->PromoteReadReplicaResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'promote DBInstance Read Replica'));
            }
            $result = $this->_loadDBInstanceData($sxml->PromoteReadReplicaResult->DBInstance);
        }

        return $result;
    }

    /**
     * Describe DBInstance Types action
     *
     * Returns a list of orderable DB instance options for the specified engine. This API supports pagination
     *
     * @param   DescribeOrderableDBInstanceOptionsData  $request Describe DB Instance Types request object.
     * @param   string          $marker                 optional The response includes only records beyond the marker.
     * @param   int             $maxRecords             optional The maximum number of records to include in the response.
     * @return  OrderableDBInstanceOptionsList  Returns the list of DB Instance types
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeOrderableDBInstanceOptions(DescribeOrderableDBInstanceOptionsData $request, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = $request->getQueryArray();
        $action = ucfirst(__FUNCTION__);

        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }

        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }

        $response = $this->client->call($action, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }

            $ptr = $sxml->{$action . 'Result'};
            $result = new OrderableDBInstanceOptionsList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($ptr->Marker) ? (string) $ptr->Marker : null;

            if (isset($ptr->OrderableDBInstanceOptions->OrderableDBInstanceOption)) {
                foreach ($ptr->OrderableDBInstanceOptions->OrderableDBInstanceOption as $v) {
                    $item = $this->_loadOrderableDBInstanceOptionData($v);
                    $result->append($item);
                    unset($item);
                }
            }
        }

        return $result;
    }

    /**
     * Loads OrderableDBInstanceOptionsData from simple xml object
     *
     * @param \SimpleXMLElement $v
     * @return OrderableDBInstanceOptionsData
     */
    protected function _loadOrderableDBInstanceOptionData(\SimpleXMLElement $v)
    {
        $item = new OrderableDBInstanceOptionsData();

        $item->setRds($this->rds);
        $item->multiAZCapable = $this->exist($v->MultiAZCapable) ? (((string)$v->MultiAZCapable) == 'true') : null;
        $item->readReplicaCapable = $this->exist($v->ReadReplicaCapable) ? (((string)$v->ReadReplicaCapable) == 'true') : null;
        $item->dBInstanceClass = $this->exist($v->DBInstanceClass) ? (string) $v->DBInstanceClass : null;
        $item->engine = $this->exist($v->Engine) ? (string) $v->Engine : null;
        $item->engineVersion = $this->exist($v->EngineVersion) ? (string) $v->EngineVersion : null;
        $item->licenseModel = $this->exist($v->LicenseModel) ? (string) $v->LicenseModel : null;
        $item->storageType = $this->exist($v->StorageType) ? (string) $v->StorageType : null;
        $item->supportsIops = $this->exist($v->SupportsIops) ? (((string)$v->SupportsIops) == 'true') : null;
        $item->supportsStorageEncryption = $this->exist($v->SupportsStorageEncryption) ? (((string)$v->SupportsStorageEncryption) == 'true') : null;
        $item->vpc = $this->exist($v->Vpc) ? (((string)$v->Vpc) == 'true') : null;
        $item->availabilityZones = $this->_loadAvailabilityZoneList($v->AvailabilityZones);

        return $item;
    }

    /**
     * Loads AvailabilityZoneList from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return AvailabilityZoneList
     */
    protected function _loadAvailabilityZoneList(\SimpleXMLElement $sxml)
    {
        $result = new AvailabilityZoneList();
        $result->setRds($this->rds);

        if (isset($sxml->AvailabilityZone)) {
            foreach ($sxml->AvailabilityZone as $v) {
                $item = new AvailabilityZoneData();
                $item->setRds($this->rds);
                $item->provisionedIopsCapable = $this->exist($v->ProvisionedIopsCapable) ? (((string)$v->ProvisionedIopsCapable) == 'true') : null;
                $item->name = $this->exist($v->Name) ? (string) $v->Name : null;
                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * Lists all tags on an Amazon RDS resource.
     *
     * @param string $resourceName  Resource identifier for the Amazon RDS resource.
     * @param string $resourceType  The type of Amazon RDS resource (db|es|og|pg|ri|secgrp|snapshot|subgrp)
     * @return TagsList
     * @throws RdsException
     */
    public function listTagsForResource($resourceName, $resourceType)
    {
        $result = null;
        $options = ['ResourceName' => $this->rds->getResourceName($resourceName, $resourceType)];
        $action = ucfirst(__FUNCTION__);

        $response = $this->client->call($action, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }

            $ptr = $sxml->{$action . 'Result'};

            $result = new TagsList();
            $result->setRds($this->rds);

            if (isset($ptr->TagList->Tag)) {
                foreach ($ptr->TagList->Tag as $v) {
                    $item = $this->_loadTagData($v);
                    $result->append($item);
                    unset($item);
                }
            }

        }

        return $result;
    }

    /**
     * Loads TagsData from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return TagsData
     */
    public function _loadTagData(\SimpleXMLElement $sxml)
    {
        $item = new TagsData();
        $item->setRds($this->rds);
        $item->value = $this->exist($sxml->Value) ? (string) $sxml->Value : null;
        $item->key = $this->exist($sxml->Key) ? (string) $sxml->Key : null;

        return $item;
    }

    /**
     * Adds metadata tags to an Amazon RDS resource.
     *
     * @param string    $resourceName  Resource identifier for the Amazon RDS resource.
     * @param string    $resourceType  The type of Amazon RDS resource (db|es|og|pg|ri|secgrp|snapshot|subgrp)
     * @param TagsList  $tagsList      List of tags to add
     * @return array    Returns array of added tags
     * @throws RdsException
     */
    public function addTagsToResource($resourceName, $resourceType, TagsList $tagsList)
    {
        $result = [];

        $options = $tagsList->getQueryArray();
        $options['ResourceName'] = $this->rds->getResourceName($resourceName, $resourceType);

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = array_values($tagsList->getQueryArray());
        }

        return $result;
    }

    /**
     * Removes metadata tags from an Amazon RDS resource.
     *
     * @param string            $resourceName  Resource identifier for the Amazon RDS resource.
     * @param string            $resourceType  The type of Amazon RDS resource (db|es|og|pg|ri|secgrp|snapshot|subgrp)
     * @param ListDataType      $tagKeyList    Array of tag keys to remove
     * @return bool
     * @throws RdsException
     */
    public function removeTagsFromResource($resourceName, $resourceType, ListDataType $tagKeyList)
    {
        $result = false;

        $options = $tagKeyList->getQueryArray();
        $options['ResourceName'] = $this->rds->getResourceName($resourceName, $resourceType);

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = true;
        }

        return $result;
    }

    /**
     * CreateDBCluster action
     *
     * Creates a new DB Cluster.
     *
     * @param   CreateDBClusterRequestData  $request    Created DB Instance request object
     *
     * @return  DBInstanceData  Returns created DBInstance
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function createDBCluster(CreateDBClusterRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->CreateDBClusterResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'create DBCluster'));
            }

            $result = $this->_loadDBClusterData($sxml->CreateDBClusterResult->DBCluster);
        }

        return $result;
    }

    /**
     * Loads DBClusterData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBClusterData Returns DBClusterData
     */
    protected function _loadDBClusterData(\SimpleXMLElement $sxml)
    {
        $item = null;

        if ($this->exist($sxml)) {
            $dbClusterIdentifier = (string) $sxml->DBClusterIdentifier;
            $item = $this->rds->getEntityManagerEnabled() ? $this->rds->dbCluster->get($dbClusterIdentifier) : null;

            if ($item === null) {
                $item = new DBClusterData();
                $item->setRds($this->rds);
                $bAttach = true;
            } else {
                $item->resetObject();
                $bAttach = false;
            }

            $this->fill($item, $sxml, [
                'dBClusterIdentifier',
                'dBClusterParameterGroup',
                'allocatedStorage',
                'dBSubnetGroup',
                'backupRetentionPeriod',
                'backupRetentionPeriod',
                'characterSetName',
                'status',
                'databaseName',
                'engine',
                'engineVersion',
                'latestRestorableTime' => 'DateTime',
                'kmsKeyId',
                'masterUsername',
                'preferredBackupWindow',
                'preferredMaintenanceWindow',
                'port' => 'int',
                'endpoint',
                'storageEncrypted' => 'bool',
                'availabilityZones' => '_loadAvailabilityZonesList',
                'vpcSecurityGroups' => '_loadVpcSecurityGroupMembershipList',
                'dBClusterOptionGroupMemberships' => '_loadOptionGroupMembershipList',
                'dBClusterMembers' => '_loadDBClusterMembers'
            ]);

            if ($bAttach && $this->rds->getEntityManagerEnabled()) {
                $this->getEntityManager()->attach($item);
            }
        }

        return $item;
    }

    /**
     * Loads DBClusterMembers from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  Rds\DataType\ClusterMembersList Returns ClusterMembersList
     */
    protected function _loadDBClusterMembers(\SimpleXMLElement $sxml)
    {
        $list = new Rds\DataType\ClusterMembersList();
        $list->setRds($this->rds);

        if (!empty($sxml->DBClusterMember)) {
            foreach ($sxml->DBClusterMember as $member) {
                $item = new Rds\DataType\ClusterMemberData(
                    $this->get($member->DBInstanceIdentifier),
                    $this->get($member->IsClusterWriter, 'bool'),
                    $this->get($member->DBClusterParameterGroupStatus)
                );
                $item->setRds($this->rds);
                $list->append($item);
                unset($item);
            }
        }

        return $list;
    }

    /**
     * Loads AvailabilityZoneList from simple xml object
     *
     * @param \SimpleXMLElement $sxml
     * @return AvailabilityZoneList
     */
    protected function _loadAvailabilityZonesList(\SimpleXMLElement $sxml)
    {
        $result = new AvailabilityZoneList();
        $result->setRds($this->rds);

        if (isset($sxml->AvailabilityZone)) {
            foreach ($sxml->AvailabilityZone as $zone) {
                $item = new AvailabilityZoneData();
                $item->setRds($this->rds);

                $item->name = (string) $zone;

                $result->append($item);
                unset($item);
            }
        }

        return $result;
    }

    /**
     * DescribeDBClusters action
     *
     * Returns information about provisioned RDS clusters. This API supports pagination
     *
     * @param   string          $dbClusterIdentifier  optional The user-specified cluster identifier.
     * @param   string          $marker               optional The response includes only records beyond the marker.
     * @param   int             $maxRecords           optional The maximum number of records to include in the response.
     *
     * @return  DBClusterList  Returns the list of DB Clusters
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeDBClusters($dbClusterIdentifier = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = array();
        if ($dbClusterIdentifier !== null) {
            $options['DBClusterIdentifier'] = (string) $dbClusterIdentifier;
        }

        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }

        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = new DBClusterList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($sxml->DescribeDBClustersResult->Marker) ?
                (string) $sxml->DescribeDBClustersResult->Marker : null;
            if (isset($sxml->DescribeDBClustersResult->DBClusters->DBCluster)) {
                foreach ($sxml->DescribeDBClustersResult->DBClusters->DBCluster as $cluster) {
                    $item = $this->_loadDBClusterData($cluster);
                    $result->append($item);
                    unset($item);
                }
            }
        }

        return $result;
    }

    /**
     * DeleteDBCluster action
     *
     * The DeleteDBCluster action deletes a previously provisioned DB cluster.
     * A successful response from the web service indicates the request was
     * received correctly. When you delete a DB cluster, all automated backups
     * for that DB cluster are deleted and cannot be recovered. Manual DB cluster
     * snapshots of the DB cluster to be deleted are not deleted.
     *
     * @param   string       $dBClusterIdentifier                The DB Cluster identifier for the DB Instance to be deleted.
     * @param   bool         $skipFinalSnapshot         optional Determines whether a final DB Snapshot is created before the DB Cluster is deleted
     * @param   string       $finalDBSnapshotIdentifier optional The DBSnapshotIdentifier of the new DBSnapshot created when SkipFinalSnapshot is set to false
     *
     * @return  DBClusterData  Returns deleted DBCluster
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function deleteDBCluster($dBClusterIdentifier, $skipFinalSnapshot = true, $finalDBSnapshotIdentifier = null)
    {
        $result = null;
        $options = [ 'DBClusterIdentifier' => (string) $dBClusterIdentifier ];

        $options['SkipFinalSnapshot'] = $skipFinalSnapshot ? 'true' : 'false';

        if ($finalDBSnapshotIdentifier !== null) {
            $options['FinalDBSnapshotIdentifier'] = (string) $finalDBSnapshotIdentifier;
            if (isset($options['SkipFinalSnapshot']) && $options['SkipFinalSnapshot'] === 'true') {
                throw new \InvalidArgumentException(sprintf(
                    'Specifiying FinalDBSnapshotIdentifier and also setting the '
                    . 'SkipFinalSnapshot parameter to true is forbidden.'
                ));
            }
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);
        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->DeleteDBClusterResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'delete DBCluster'));
            }
            $result = $this->_loadDBClusterData($sxml->DeleteDBClusterResult->DBCluster);
        }
        return $result;
    }

    /**
     * CreateDBClusterSnapshot action
     *
     * Creates a new DB cluster snapshot.
     *
     * @param   string          $dbClusterIdentifier            The identifier of the DB cluster to create a snapshot for. This parameter is not case-sensitive.
     * @param   string          $dbClusterSnapshotIdentifier    The identifier of the DB cluster snapshot. This parameter is stored as a lowercase string.
     * @param   TagsList  $tags                                 optional The tags to be assigned to the DB cluster snapshot.
     *
     * @return  DBClusterSnapshotData  Returns created DB cluster snapshot
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function createDBClusterSnapshot($dbClusterIdentifier, $dbClusterSnapshotIdentifier, TagsList $tags = null)
    {
        $result = null;
        $options = [];

        if (count($tags) > 0) {
            $options = $tags->getQueryArray();
        }

        if ($dbClusterIdentifier !== null) {
            $options['DBClusterIdentifier'] = (string) $dbClusterIdentifier;
        }

        if ($dbClusterSnapshotIdentifier !== null) {
            $options['DBClusterSnapshotIdentifier'] = (string) $dbClusterSnapshotIdentifier;
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->CreateDBClusterSnapshotResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'create DBClusterSnapshot'));
            }

            $result = $this->_loadDBClusterSnapshotData($sxml->CreateDBClusterSnapshotResult->DBClusterSnapshot);
        }

        return $result;
    }

    /**
     * CopyDBClusterSnapshot action
     *
     * Creates a new DB cluster snapshot.
     *
     * @param   string          $sourceDbClusterSnapshotIdentifier  The identifier of the DB cluster snapshot to copy. This parameter is not case-sensitive.
     * @param   string          $targetDbClusterSnapshotIdentifier  The identifier of the new DB cluster snapshot to create from the source DB cluster snapshot. This parameter is not case-sensitive.
     * @param   TagsList  $tags                               optional A list of tags.
     *
     * @return  DBClusterSnapshotData  Returns created DB cluster snapshot
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function copyDBClusterSnapshot($sourceDbClusterSnapshotIdentifier, $targetDbClusterSnapshotIdentifier, TagsList $tags = null)
    {
        $result = null;
        $options = [];

        if (count($tags) > 0) {
            $options = $tags->getQueryArray();
        }

        if ($sourceDbClusterSnapshotIdentifier !== null) {
            $options['SourceDBClusterSnapshotIdentifier'] = (string) $sourceDbClusterSnapshotIdentifier;
        }

        if ($targetDbClusterSnapshotIdentifier !== null) {
            $options['TargetDBClusterSnapshotIdentifier'] = (string) $targetDbClusterSnapshotIdentifier;
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            if (!$this->exist($sxml->CopyDBClusterSnapshotResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'copy DBClusterSnapshot'));
            }

            $result = $this->_loadDBClusterSnapshotData($sxml->CopyDBClusterSnapshotResult->DBClusterSnapshot);
        }

        return $result;
    }

    /**
     * DescribeDBClusterSnapshots action
     *
     * Returns information about DB cluster snapshots. This API supports pagination
     *
     * @param   string          $dbClusterIdentifier            optional A DB cluster identifier to retrieve the list of DB cluster snapshots for.
     *                                                          This parameter cannot be used in conjunction with the DBClusterSnapshotIdentifier parameter.
     *                                                          This parameter is not case-sensitive.
     *
     * @param   string          $dbClusterSnapshotIdentifier    optional A specific DB cluster snapshot identifier to describe.
     *                                                          This parameter cannot be used in conjunction with the DBClusterIdentifier parameter.
     *                                                          This value is stored as a lowercase string.
     *
     * @param   string          $snapshotType                   optional The type of DB cluster snapshots that will be returned.
     *                                                          Values can be automated or manual.
     *                                                          If this parameter is not specified, the returned results will include all snapshot types.
     *
     * @param   string          $marker                         optional The response includes only records beyond the marker.
     * @param   int             $maxRecords                     optional The maximum number of records to include in the response.
     *
     * @return  DBClusterSnapshotList  Returns the list of DB Cluster Snapshots
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describeDBClusterSnapshots($dbClusterIdentifier = null, $dbClusterSnapshotIdentifier = null, $snapshotType = null, $marker = null, $maxRecords = null)
    {
        $result = null;
        $options = [];

        if ($dbClusterIdentifier !== null) {
            $options['DBClusterIdentifier'] = (string) $dbClusterIdentifier;
        }

        if ($dbClusterSnapshotIdentifier !== null) {
            $options['DBClusterSnapshotIdentifier'] = (string) $dbClusterSnapshotIdentifier;
        }

        if ($snapshotType !== null) {
            $options['SnapshotType'] = (string) $snapshotType;
        }

        if ($marker !== null) {
            $options['Marker'] = (string) $marker;
        }

        if ($maxRecords !== null) {
            $options['MaxRecords'] = (int) $maxRecords;
        }

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());
            $result = new DBClusterSnapshotList();
            $result->setRds($this->rds);
            $result->marker = $this->exist($sxml->DescribeDBClusterSnapshotsResult->Marker) ?
                (string) $sxml->DescribeDBClusterSnapshotsResult->Marker : null;

            if (isset($sxml->DescribeDBClusterSnapshotsResult->DBClusterSnapshots->DBClusterSnapshot)) {
                foreach ($sxml->DescribeDBClusterSnapshotsResult->DBClusterSnapshots->DBClusterSnapshot as $clusterSnapshot) {
                    $item = $this->_loadDBClusterSnapshotData($clusterSnapshot);
                    $result->append($item);
                    unset($item);
                }
            }
        }

        return $result;
    }

    /**
     * Loads DBClusterSnapshotData from simple xml object
     *
     * @param   \SimpleXMLElement $sxml
     * @return  DBClusterSnapshotData Returns DBClusterSnapshotData object
     */
    protected function _loadDBClusterSnapshotData(\SimpleXMLElement $sxml)
    {
        $item = null;

        if ($this->exist($sxml)) {
            $item = new DBClusterSnapshotData();
            $item->setRds($this->rds);

            $this->fill($item, $sxml, [
                'allocatedStorage' => 'int',
                'clusterCreateTime' => 'DateTime',
                'dBClusterIdentifier',
                'dBClusterSnapshotIdentifier',
                'engine',
                'engineVersion',
                'licenseModel',
                'masterUsername',
                'percentProgress' => 'int',
                'port' => 'int',
                'snapshotCreateTime' => 'DateTime',
                'snapshotType',
                'status',
                'vpcId',
                'availabilityZones' => '_loadAvailabilityZonesList',
            ]);
        }

        return $item;
    }

    /**
     * DeleteDBClusterSnapshot action
     *
     * Deletes a DB cluster snapshot. If the snapshot is being copied, the copy operation is terminated.
     * The DB cluster snapshot must be in the available state to be deleted.
     *
     * @param   string     $dbClusterSnapshotIdentifier     The identifier of the DB cluster snapshot to delete.
     *
     * @return  DBClusterSnapshotData  Returns deleted DB Cluster Snapshot
     *
     * @throws  ClientException
     * @throws  RdsException
     */
    public function deleteDBClusterSnapshot($dbClusterSnapshotIdentifier)
    {
        $result = null;
        $options = ['DBClusterSnapshotIdentifier' => (string) $dbClusterSnapshotIdentifier];

        $response = $this->client->call(ucfirst(__FUNCTION__), $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->DeleteDBClusterSnapshotResult)) {
                throw new RdsException(sprintf(self::UNEXPECTED, 'delete DBClusterSnapshot'));
            }

            $result = $this->_loadDBClusterSnapshotData($sxml->DeleteDBClusterSnapshotResult->DBClusterSnapshot);
        }

        return $result;
    }

    /**
     * RestoreDBClusterFromSnapshot action
     *
     * Creates a new DB cluster from a DB cluster snapshot.
     * The target DB cluster is created from the source DB cluster restore point with the same configuration as the original source DB cluster,
     * except that the new DB cluster is created with the default security group.
     *
     * @param   RestoreDBClusterFromSnapshotRequestData $request The request object.
     * @return  DBClusterData Returns DBClusterData on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function restoreDBClusterFromSnapshot(RestoreDBClusterFromSnapshotRequestData $request)
    {
        $result = null;
        $options = $request->getQueryArray();
        $action = ucfirst(__FUNCTION__);

        $response = $this->client->call($action, $options);

        if ($response->getError() === false) {
            $sxml = simplexml_load_string($response->getRawContent());

            if (!$this->exist($sxml->{$action . 'Result'})) {
                throw new RdsException(sprintf(self::UNEXPECTED, $action));
            }

            $ptr = $sxml->{$action . 'Result'};
            $result = $this->_loadDBClusterData($ptr->DBCluster);
        }

        return $result;
    }

}
