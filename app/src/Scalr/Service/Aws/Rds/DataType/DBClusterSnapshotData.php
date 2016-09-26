<?php

namespace Scalr\Service\Aws\Rds\DataType;

use DateTime;
use Scalr\Service\Aws\Rds;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;
use Scalr\Service\Aws\RdsException;

/**
 * DBClusterSnapshotData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     07.10.2015
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $availabilityZones
 *           Provides the list of EC2 Availability Zones that instances in the DB cluster snapshot can be restored in.
 *
 */
class DBClusterSnapshotData extends AbstractRdsDataType
{
    const STATUS_AVAILABLE = 'available';

    const STATUS_DELETING = 'deleting';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['availabilityZones'];

    /**
     * Specifies the allocated storage size specified in gigabytes.
     *
     * @var int
     */
    public $allocatedStorage;

    /**
     * Specifies the allocated storage size specified in gigabytes.
     *
     * @var DateTime
     */
    public $clusterCreateTime;

    /**
     * Specifies the DB cluster identifier of the DB cluster that this DB cluster snapshot was created from.
     *
     * @var string
     */
    public $dBClusterIdentifier;

    /**
     * Specifies the identifier for the DB cluster snapshot.
     *
     * @var string
     */
    public $dBClusterSnapshotIdentifier;

    /**
     * Specifies the name of the database engine.
     *
     * @var string
     */
    public $engine;

    /**
     * Provides the version of the database engine for this DB cluster snapshot.
     *
     * @var string
     */
    public $engineVersion;

    /**
     * Provides the license model information for this DB cluster snapshot.
     *
     * @var string
     */
    public $licenseModel;

    /**
     * Provides the master username for the DB cluster snapshot.
     *
     * @var string
     */
    public $masterUsername;

    /**
     * Specifies the percentage of the estimated data that has been transferred.
     *
     * @var int
     */
    public $percentProgress;

    /**
     * Specifies the port that the DB cluster was listening on at the time of the snapshot.
     *
     * @var int
     */
    public $port;

    /**
     * Provides the time when the snapshot was taken, in Universal Coordinated Time (UTC).
     *
     * @var DateTime
     */
    public $snapshotCreateTime;

    /**
     * Provides the type of the DB cluster snapshot.
     *
     * @var string
     */
    public $snapshotType;

    /**
     * Specifies the status of this DB cluster snapshot.
     *
     * @var string
     */
    public $status;

    /**
     * Provides the VPC ID associated with the DB cluster snapshot.
     *
     * @var string
     */
    public $vpcId;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Rds.AbstractRdsDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        parent::throwExceptionIfNotInitialized();

        if ($this->dBClusterSnapshotIdentifier === null) {
            throw new RdsException(sprintf(
                'DBClusterSnapshotIdentifier has not been initialized for "%s" yet', get_class($this)
            ));
        }
    }

}