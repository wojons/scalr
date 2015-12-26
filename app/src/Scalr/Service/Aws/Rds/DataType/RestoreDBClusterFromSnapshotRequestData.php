<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * RestoreDBClusterFromSnapshotRequestData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     07.10.2015
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $availabilityZones
 *           Provides the list of EC2 Availability Zones that instances in the restored DB cluster can be created in.
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $vpcSecurityGroupIds
 *           A list of VPC security groups that the new DB cluster will belong to.
 *
 * @property \Scalr\Service\Aws\Rds\DataType\TagsList $tags
 *           The tags to be assigned to the restored DB cluster.
 *
 */
class RestoreDBClusterFromSnapshotRequestData extends AbstractRdsDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['availabilityZones', 'vpcSecurityGroupIds', 'tags'];

    /**
     * The database name for the restored DB cluster.
     *
     * @var string
     */
    public $databaseName;

    /**
     * The name of the DB cluster to create from the DB cluster snapshot. This parameter isn't case-sensitive.
     *
     * @var string
     */
    public $dBClusterIdentifier;

    /**
     * The name of the DB subnet group to use for the new DB cluster.
     *
     * @var string
     */
    public $dBSubnetGroupName;

    /**
     * The database engine to use for the new DB cluster. Must be compatible with the engine of the source
     *
     * @var string
     */
    public $engine;

    /**
     * The version of the database engine to use for the new DB cluster.
     *
     * @var string
     */
    public $engineVersion;

    /**
     * The name of the option group to use for the restored DB cluster.
     *
     * @var string
     */
    public $optionGroupName;

    /**
     * The port number on which the new DB cluster accepts connections.
     * Value must be 1150-65535
     *
     * @var string
     */
    public $port;

    /**
     * The identifier for the DB cluster snapshot to restore from.
     *
     * @var string
     */
    public $snapshotIdentifier;

    /**
     * Constructor
     *
     * @param   string     $dBClusterIdentifier The name of the DB cluster to create from the DB cluster snapshot. This parameter isn't case-sensitive.
     * @param   string     $snapshotIdentifier  The identifier for the DB cluster snapshot to restore from.
     */
    public function __construct($dBClusterIdentifier, $snapshotIdentifier)
    {
        parent::__construct();
        $this->dBClusterIdentifier = (string) $dBClusterIdentifier;
        $this->snapshotIdentifier = (string) $snapshotIdentifier;
    }

    /**
     * Sets AvailabilityZones list
     *
     * @param   ListDataType|array|string $availabilityZones
     *          Provides the list of EC2 Availability Zones that instances in the restored DB cluster can be created in.
     * @return  CreateDBInstanceRequestData
     */
    public function setAvailabilityZones($availabilityZones = null)
    {
        if ($availabilityZones !== null && !($availabilityZones instanceof ListDataType)) {
            $availabilityZones = new ListDataType($availabilityZones);
        }

        return $this->__call(__FUNCTION__, array($availabilityZones));
    }

    /**
     * Sets VpcSecurityGroupIds list
     *
     * @param   ListDataType|array|string $vpcSecurityGroupIds
     *          A list of EC2 VPC Security Groups to associate with this DB Snapshot.
     * @return  RestoreDBClusterFromSnapshotRequestData
     */
    public function setVpcSecurityGroupIds($vpcSecurityGroupIds = null)
    {
        if ($vpcSecurityGroupIds !== null && !($vpcSecurityGroupIds instanceof ListDataType)) {
            $vpcSecurityGroupIds = new ListDataType($vpcSecurityGroupIds);
        }
        return $this->__call(__FUNCTION__, array($vpcSecurityGroupIds));
    }

    /**
     * Sets Tags list
     *
     * @param   TagsList|array|string $tags
     *          A list of tags to associate with this DB Snapshot.
     * @return  RestoreDBClusterFromSnapshotRequestData
     */
    public function setTags($tags = null)
    {
        if ($tags !== null && !($tags instanceof TagsList)) {
            $tags = new TagsList($tags);
        }
        return $this->__call(__FUNCTION__, array($tags));
    }

}