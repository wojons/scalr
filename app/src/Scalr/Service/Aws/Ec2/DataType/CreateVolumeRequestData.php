<?php

namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2\AbstractEc2DataType;

/**
 * AWS Ec2 CreateVolumeRequestData
 *
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    22.01.2013
 *
 * @method   CreateVolumeRequestData setSize()               setSize(string $val)             Sets a size.
 * @method   CreateVolumeRequestData setSnapshotId()         setSnapshotId(string $val)       Sets a snapshotId.
 * @method   CreateVolumeRequestData setVolumeType()         setVolumeType(string $val)       Sets a volumeType.
 * @method   CreateVolumeRequestData setIops()               setIops(int $val)                Sets an iops.
 * @method   CreateVolumeRequestData setAvailabilityZone()   setAvailabilityZone(string $val) Sets an availabilityZone.
 */
class CreateVolumeRequestData extends AbstractEc2DataType
{

    const VOLUME_TYPE_STANDARD = 'standard';

    const VOLUME_TYPE_IO1 = 'io1';

    const VOLUME_TYPE_GP2 = 'gp2';
    
    const VOLUME_TYPE_SC1 = 'sc1';
    
    const VOLUME_TYPE_ST1 = 'st1';

    /**
     * The size of the volume, in GiBs.
     *
     * @var string
     */
    public $size;

    /**
     * The snapshot from which the volume was created (optional).
     *
     * @var string
     */
    public $snapshotId;

    /**
     * The Availability Zone in which the volume was created.
     *
     * @var string
     */
    public $availabilityZone;

    /**
     * The volume type
     * standard | io1 | gp2
     *
     * @var string
     */
    public $volumeType;

    /**
     * The number of I/O operations per second (IOPS) that the volume supports.
     * Valid values: Range is 100 to 2000.
     * Condition: Required when the volume type is io1; not used with
     * standard volumes.
     *
     * @var int
     */
    public $iops;

    /**
     * Specifies whether the volume should be encrypted.
     *
     * @var bool
     */
    public $encrypted;

    /**
     * The full ARN of the AWS Key Management Service (AWS KMS) customer master key (CMK) to use when creating the encrypted volume.
     * This parameter is only required if you want to use a non-default CMK;
     * if this parameter is not specified, the default CMK for EBS is used.
     *
     * @var string
     */
    public $kmsKeyId;

    /**
     * Convenient constructor
     *
     * @param   string|AvailabilityZoneData $availabilityZone The Availability Zone in which the volume was created.
     * @param   string                      $size             optional Size of the volume, in GiBs.
     * @param   bool                        $encrypted        optional Specifies whether the volume should be encrypted.
     */
    public function __construct($availabilityZone, $size = null, $encrypted = null)
    {
        parent::__construct();
        if ($availabilityZone instanceof AvailabilityZoneData) {
            $zoneName = $availabilityZone->zoneName;
        } else {
            $zoneName = (string)$availabilityZone;
        }
        $this->availabilityZone = $zoneName;
        $this->size = $size;
        $this->volumeType = self::VOLUME_TYPE_STANDARD;
        $this->encrypted = $encrypted;
    }
}