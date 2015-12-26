<?php


namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * CreateDBClusterRequestData
 *
 * @author N.V.
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $availabilityZones
 *           A list of EC2 Availability Zones that instances in the  DB Cluster is located in.
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $vpcSecurityGroupIds
 *           A list of EC2 VPC Security Groups to associate with this DB Cluster.
 *
 * @property \Scalr\Service\Aws\Rds\DataType\TagsList $tags
 *           A list of tags to associate with this DB Cluster.
 */
class CreateDBClusterRequestData extends AbstractRdsDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['availabilityZones', 'vpcSecurityGroupIds', 'tags'];

    /**
     * Specifies the number of days for which automatic DB Snapshots are retained
     *
     * Constraints:
     * Must be a value from 0 to 8
     * Cannot be set to 0 if the DB Instance is a master instance with read replicas
     * Default: 1
     *
     * @var int
     */
    public $backupRetentionPeriod;

    /**
     * For supported engines, indicates that the DB Cluster
     * should be associated with the specified CharacterSet.
     *
     * @var string
     */
    public $characterSetName;

    /**
     * Contains a user-supplied database cluster identifier.
     * This is the unique key that identifies a DB Cluster
     *
     * @var string
     */
    public $dBClusterIdentifier;

    /**
     * The name of the DB Parameter Group to associate with this DB cluster. If this argument is omitted,
     * the default DBCLusterParameterGroup for the specified engine will be used.
     *
     * Constraints:
     * Must be 1 to 255 alphanumeric characters
     * First character must be a letter
     * Cannot end with a hyphen or contain two consecutive hyphens
     *
     * @var string
     */
    public $dBClusterParameterGroupName;

    /**
     * A DB Subnet Group to associate with this DB Instance.
     * If there is no DB Subnet Group, then it is a non-VPC DB instance.
     *
     * @var string
     */
    public $dBSubnetGroupName;

    /**
     * The name of the database to create when the DB Cluster is created. If this parameter is not specified,
     * no database is created in the DB Instance.
     * Constraints:
     *     Must contain 1 to 64 alphanumeric characters
     *     Cannot be a word reserved by the specified database engine
     *
     * @var string
     */
    public $databaseName;

    /**
     * Provides the name of the database engine to be used for this DB Cluster.
     *
     * @var string
     */
    public $engine;

    /**
     * Indicates the database engine version.
     *
     * MySQL Example: 5.1.42
     *
     * @var string
     */
    public $engineVersion;

    /**
     * The password for the master database user. Can be any printable ASCII character except "/", "\", or "@".
     *
     * Constraints: Must contain from 8 to 41 alphanumeric characters.
     *
     * @var string
     */
    public $masterUserPassword;

    /**
     * Contains the master username for the DB Cluster
     *
     * Constraints:
     * Must be 1 to 16 alphanumeric characters.
     * First character must be a letter.
     * Cannot be a reserved word for the chosen database engine.
     *
     * @var string
     */
    public $masterUsername;

    /**
     * Indicates that the DB Cluster should be associated with the specified option group.
     *
     * @var string
     */
    public $optionGroupName;

    /**
     * The port number on which the database accepts connections.
     *
     * Default: 3306
     * Valid Values: 1150-65535
     *
     * @var int
     */
    public $port;

    /**
     * The daily time range during which automated backups
     * are created if automated backups are enabled,
     * using the BackupRetentionPeriod parameter.
     *
     * Default: A 30-minute window selected at random from an 8-hour block of time per region.
     * The following list shows the time blocks for each region from
     * which the default backup windows are assigned.
     *
     * US-East (Northern Virginia) Region: 03:00-11:00 UTC
     * US-West (Northern California) Region: 06:00-14:00 UTC
     * EU (Ireland) Region: 22:00-06:00 UTC
     * Asia Pacific (Singapore) Region: 14:00-22:00 UTC
     * Asia Pacific (Tokyo) Region: 17:00-03:00 UTC
     *
     * Constraints: Must be in the format hh24:mi-hh24:mi.
     * Times should be Universal Time Coordinated (UTC).
     * Must not conflict with the preferred maintenance window.
     * Must be at least 30 minutes.
     *
     * @var string
     */
    public $preferredBackupWindow;

    /**
     * The weekly time range (in UTC) during which system maintenance can occur.
     *
     * Format: ddd:hh24:mi-ddd:hh24:mi
     * Default: A 30-minute window selected at random from
     * an 8-hour block of time per region, occurring
     * on a random day of the week.
     * The following list shows the time blocks for each region from which
     * the default maintenance windows are assigned.
     *
     * US-East (Northern Virginia) Region: 03:00-11:00 UTC
     * US-West (Northern California) Region: 06:00-14:00 UTC
     * EU (Ireland) Region: 22:00-06:00 UTC
     * Asia Pacific (Singapore) Region: 14:00-22:00 UTC
     * Asia Pacific (Tokyo) Region: 17:00-03:00 UTC
     *
     * Valid Days: Mon, Tue, Wed, Thu, Fri, Sat, Sun
     * Constraints: Minimum 30-minute window.
     *
     * @var string
     */
    public $preferredMaintenanceWindow;

    /**
     * Constructor
     *
     * @param   string  $dBClusterIdentifier    A user-supplied cluster identifier
     * @param   string  $engine                 The name of the database engine to be used for this DB Cluster
     * @param   string  $masterUsername         The master username for database instances in this cluster
     * @param   string  $masterUserPassword     The password form database instances in this cluster
     */
    public function __construct($dBClusterIdentifier, $engine, $masterUsername, $masterUserPassword)
    {
        parent::__construct();
        $this->dBClusterIdentifier = (string) $dBClusterIdentifier;
        $this->engine = (string) $engine;
        $this->masterUsername = (string) $masterUsername;
        $this->masterUserPassword = (string) $masterUserPassword;
    }

    /**
     * Sets AvailabilityZones list
     *
     * @param   ListDataType|array|string $availabilityZones
     *          A list of Availability Zones that instances in the  DB Cluster is located in.
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
     *          A list of EC2 VPC Security Groups to associate with this DB Cluster.
     * @return  CreateDBInstanceRequestData
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
     *          A list of tags to associate with this DB Cluster.
     * @return  CreateDBInstanceRequestData
     */
    public function setTags($tags = null)
    {
        if ($tags !== null && !($tags instanceof TagsList)) {
            $tags = new TagsList($tags);
        }

        return $this->__call(__FUNCTION__, array($tags));
    }
}