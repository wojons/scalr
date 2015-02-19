<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * CreateDBInstanceReadReplicaData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     20.01.2015
 *
 */
class CreateDBInstanceReadReplicaData extends AbstractRdsDataType
{

    /**
     * Indicates that minor engine upgrades will be applied automatically to the Read Replica during the maintenance window.
     *
     * Default: Inherits from the source DB instance
     *
     * @var bool
     */
    public $autoMinorVersionUpgrade;

    /**
     * The Amazon EC2 Availability Zone that the Read Replica will be created in.
     *
     * Default: A random, system-chosen Availability Zone in the endpoint's region.
     *
     * @var string
     */
    public $availabilityZone;

    /**
     * The compute and memory capacity of the Read Replica.
     * Default: Inherits from the source DB instance.
     *
     * @var string
     */
    public $dBInstanceClass;

    /**
     * Contains a user-supplied database identifier.
     * This is the unique key that identifies a DB Instance
     * Default: Inherits from the source DB instance.
     *
     * @var string
     */
    public $dBInstanceIdentifier;

    /**
     * he DB instance identifier of the Read Replica.
     * This is the unique key that identifies a DB instance.
     * This parameter is stored as a lowercase string.
     *
     * @var string
     */
    public $dBSubnetGroupName;

    /**
     * Specifies the Provisioned IOPS (I/O operations per second) value
     *
     * Constraints: Must be an integer greater than 1000.
     *
     * @var int
     */
    public $iops;

    /**
     * Indicates that the DB Instance should be associated with the specified option group.
     *
     * @var string
     */
    public $optionGroupName;

    /**
     * The port number on which the database accepts connections.
     *
     * MySQL Default: 3306 Valid Values: 1150-65535
     * Oracle Default: 1521 Valid Values: 1150-65535
     * SQL Server Default: 1433 Valid Values: 1150-65535 except for 1434 and 3389.
     *
     * @var int
     */
    public $port;

    /**
     * publiclyAccessible
     *
     * @var bool
     */
    public $publiclyAccessible;

    /**
     * The identifier of the DB instance that will act as the source for the Read Replica.
     * Each DB instance can have up to five Read Replicas.
     *
     * @var string
     */
    public $sourceDBInstanceIdentifier;

    /**
     * Specifies the storage type to be associated with the DB instance.
     *
     * Valid values: standard | gp2 | io1
     *
     * If you specify io1, you must also include a value for the Iops parameter.
     *
     * Default: io1 if the Iops parameter is specified; otherwise standard
     *
     * @var string
     */
    public $storageType;

    /**
     * Constructor
     *
     * @param   string     $dBInstanceIdentifier            Contains a user-supplied database identifier.
     * @param   string     $sourceDBInstanceIdentifier      The identifier of the DB instance that will act as the source for the Read Replica.
     */
    public function __construct($dBInstanceIdentifier, $sourceDBInstanceIdentifier)
    {
        parent::__construct();
        $this->dBInstanceIdentifier = (string) $dBInstanceIdentifier;
        $this->sourceDBInstanceIdentifier = (string) $sourceDBInstanceIdentifier;
    }

}