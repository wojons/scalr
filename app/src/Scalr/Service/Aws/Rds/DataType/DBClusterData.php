<?php

namespace Scalr\Service\Aws\Rds\DataType;

use DateTime;
use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Rds;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;
use Scalr\Service\Aws\RdsException;

/**
 * DBClusterData
 *
 * @author N.V.
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $availabilityZones
 *           A list of EC2 Availability Zones that instances in the  DB Cluster is located in.
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $dBClusterMembers
 *           Specifies the name and status of the option group that this instance belongs to.
 *
 * @property \Scalr\Service\Aws\Rds\DataType\OptionGroupMembershipList $dBClusterOptionGroupMemberships
 *           Specifies the name and status of the option group that this instance belongs to.
 *
 * @property \Scalr\Service\Aws\Rds\DataType\VpcSecurityGroupMembershipList $vpcSecurityGroups
 *           Provides List of VPC security group elements that the DB Cluster belongs to.
 */
class DBClusterData extends AbstractRdsDataType
{

    const STATUS_AVAILABLE = 'available';

    const STATUS_DELETING = 'deleting';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = [ 'availabilityZones', 'dBClusterMembers', 'dBClusterOptionGroupMemberships', 'vpcSecurityGroups' ];

    /**
     * Specifies the allocated storage size specified in gigabytes.
     *
     * @var int
     */
    public $allocatedStorage;

    /**
     * Specifies the number of days for which automatic DB Snapshots are retained
     *
     * @var int
     */
    public $backupRetentionPeriod;

    /**
     * If present, specifies the name of the character set
     * that this instance is associated with
     *
     * @var string
     */
    public $characterSetName;

    /**
     * Contains a user-supplied database identifier.
     * This is the unique key that identifies a DB Instance
     *
     * @var string
     */
    public $dBClusterIdentifier;

    /**
     * Specifies the name of the DB cluster parameter group for the DB cluster.
     *
     * @var string
     */
    public $dBClusterParameterGroup;

    /**
     * Specifies information on the subnet group associated with the DB cluster, including the name, description, and subnets in the subnet group.
     *
     * @var string
     */
    public $dBSubnetGroup;

    /**
     * The meaning of this parameter differs according to the database engine you use.
     *
     * Contains the name of the initial database of this instance
     * that was provided at create time, if one was
     * specified when the DB Instance was created. This same name
     * is returned for the life of the DB Instance.
     *
     * @var string
     */
    public $databaseName;

    /**
     * Specifies the earliest time to which a database can be restored with point-in-time restore.
     *
     * @var DateTime
     */
    public $earliestRestorableTime;

    /**
     * Specifies the connection endpoint for the primary instance of the DB cluster.
     *
     * @var string
     */
    public $endpoint;

    /**
     * Provides the name of the database engine to be used for this DB Instance.
     *
     * @var string
     */
    public $engine;

    /**
     * Indicates the database engine version.
     *
     * @var string
     */
    public $engineVersion;

    /**
     * Specifies the ID that Amazon Route 53 assigns when you create a hosted zone.
     *
     * @var string
     */
    public $hostedZoneId;

    /**
     * Specifies the latest time to which a database
     * can be restored with point-in-time restore
     *
     * @var DateTime
     */
    public $latestRestorableTime;

    /**
     * If StorageEncrypted is true, the KMS key identifier for the encrypted DB cluster.
     *
     * @var string
     */
    public $kmsKeyId;

    /**
     * Contains the master username for the DB Instance
     *
     * @var string
     */
    public $masterUsername;

    /**
     * Specifies the progress of the operation as a percentage.
     *
     * @var string
     */
    public $percentageProgress;

    /**
     * Specifies the port that the database engine is listening on.
     *
     * @var int
     */
    public $port;

    /**
     * Specifies the daily time range during which automated
     * backups are created if automated backups are enabled,
     * as determined by the BackupRetentionPeriod
     *
     * @var string
     */
    public $preferredBackupWindow;

    /**
     * Specifies the weekly time range (in UTC)
     * during which system maintenance can occur
     *
     * @var string
     */
    public $preferredMaintenanceWindow;

    /**
     * Specifies the current state of this database
     *
     * @var string
     */
    public $status;

    /**
     * Specifies whether the DB cluster is encrypted.
     *
     * @var bool
     */
    public $storageEncrypted;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Rds.AbstractRdsDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();
        if ($this->dBClusterIdentifier === null) {
            throw new RdsException(sprintf(
                'DBClusterIdentifier has not been initialized for "%s" yet', get_class($this)
            ));
        }
    }

    /**
     * Adds metadata tags to an Amazon RDS resource.
     *
     * @param array|TagsList $tagsList  List of tags to add
     * @return array    Returns array of added tags
     * @throws RdsException
     */
    public function addTags($tagsList)
    {
        $this->throwExceptionIfNotInitialized();

        return $this->getRds()->tag->add($this->dBClusterIdentifier, Rds::DB_INSTANCE_RESOURCE_TYPE, $tagsList);
    }

    /**
     * Removes metadata tags from an Amazon RDS resource.
     *
     * @param  array|ListDataType  $tagsKeys      Array of tag keys to remove
     * @return bool
     * @throws RdsException
     */
    public function removeTags($tagsKeys)
    {
        $this->throwExceptionIfNotInitialized();

        return $this->getRds()->tag->remove($this->dBClusterIdentifier, Rds::DB_INSTANCE_RESOURCE_TYPE, $tagsKeys);
    }

    /**
     * Lists all tags on an Amazon RDS resource.
     *
     * @return TagsList
     * @throws RdsException
     */
    public function describeTags()
    {
        $this->throwExceptionIfNotInitialized();

        return $this->getRds()->tag->describe($this->dBClusterIdentifier, Rds::DB_INSTANCE_RESOURCE_TYPE);
    }

    /**
     * DescribeDBClusters action
     *
     * Refreshes description of the object using request to Amazon.
     * NOTE! It refreshes object itself only when EntityManager is enabled.
     * If not, solution is to use $object = object->refresh() instead.
     *
     * @return  DBClusterData  Return refreshed object
     *
     * @throws  RdsException
     */
    public function refresh()
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getRds()->dbCluster->describe($this->dBClusterIdentifier)->get(0);
    }

    /**
     * DeleteDBCluster action
     *
     * The DeleteDBCluster API deletes a previously provisioned RDS cluster.
     * A successful response from the web service indicates the request
     * was received correctly. If a final DBSnapshot is requested the status
     * of the RDS instance will be "deleting" until the DBSnapshot is created.
     * DescribeDBInstance is used to monitor the status of this operation.
     * This cannot be canceled or reverted once submitted
     *
     * @param   bool         $skipFinalSnapshot         optional Determines whether a final DB Snapshot is created before the DB Instance is deleted
     * @param   string       $finalDBSnapshotIdentifier optional The DBSnapshotIdentifier of the new DBSnapshot created when SkipFinalSnapshot is set to false
     *
     * @return  DBClusterData  Returns created DBCluster
     *
     * @throws  RdsException
     */
    public function delete($skipFinalSnapshot = null, $finalDBSnapshotIdentifier = null)
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getRds()->dbCluster->delete($this->dBClusterIdentifier, $skipFinalSnapshot, $finalDBSnapshotIdentifier);
    }

    /**
     * ModifyDBCluster action
     *
     * Modify settings for a DB Cluster.
     * You can change one or more database configuration parameters by
     * specifying these parameters and the new values in the request.
     *
     * @param   ModifyDBClusterRequestData $request Modify DB Cluster request object
     *
     * @return  DBClusterData  Returns modified DBCluster
     *
     * @throws  RdsException
     */
    public function modify(ModifyDBClusterRequestData $request)
    {
        $this->throwExceptionIfNotInitialized();
        return $this->getRds()->dbCluster->modify($request);
    }

    /**
     * Creates and returns new ModifyDBClusterRequestData object for this DBCluster
     *
     * @return ModifyDBClusterRequestData Returns new ModifyDBClusterRequestData object for this DBCluster
     */
    public function getModifyRequest()
    {
        $this->throwExceptionIfNotInitialized();
        return new ModifyDBClusterRequestData($this->dBClusterIdentifier);
    }
}