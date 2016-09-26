<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\RdsException;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * OrderableDBInstanceOptionsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 *
 * @property \Scalr\Service\Aws\Rds\DataType\EC2SecurityGroupList $availabilityZones
 *           A list of EC2SecurityGroupData objects
 */
class OrderableDBInstanceOptionsData extends AbstractRdsDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['availabilityZones'];

    /**
     * The DB instance Class for the orderable DB instance
     *
     * @var string
     */
    public $dBInstanceClass;

    /**
     * The engine type of the orderable DB instance.
     *
     * @var string
     */
    public $engine;

    /**
     * The engine version of the orderable DB instance.
     *
     * @var string
     */
    public $engineVersion;

    /**
     * The license model for the orderable DB instance.
     *
     * @var string
     */
    public $licenseModel;

    /**
     * Indicates whether this orderable DB instance is multi-AZ capable.
     *
     * @var bool
     */
    public $multiAZCapable;

    /**
     * Indicates whether this orderable DB instance can have a Read Replica.
     *
     * @var bool
     */
    public $readReplicaCapable;

    /**
     * The storage type for this orderable DB instance.
     *
     * @var string
     */
    public $storageType;

    /**
     * Indicates whether this orderable DB instance supports provisioned IOPS.
     *
     * @var bool
     */
    public $supportsIops;

    /**
     * Indicates whether this orderable DB instance supports encrypted storage.
     *
     * @var bool
     */
    public $supportsStorageEncryption;

    /**
     * Indicates whether this is a VPC orderable DB instance.
     *
     * @var bool
     */
    public $vpc;

}