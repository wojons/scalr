<?php
namespace Scalr\Service\CloudStack\Services\Volume\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateVolumeData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateVolumeData extends AbstractDataType
{

    /**
     * Required
     * The name of the disk volume
     *
     * @var string
     */
    public $name;

    /**
     * The account associated with the disk volume.
     * Must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * The ID of the disk offering.
     * Either diskOfferingId or snapshotId must be passed in.
     *
     * @var string
     */
    public $diskofferingid;

    /**
     * An optional field, whether to display the volume to the end user or not.
     *
     * @var string
     */
    public $displayvolume;

    /**
     * The domain ID associated with the disk offering.
     * If used with the account parameter returns the disk volume associated with the account for the specified domain.
     *
     * @var string
     */
    public $domainid;

    /**
     * Max iops
     *
     * @var string
     */
    public $maxiops;

    /**
     * Min iops
     *
     * @var string
     */
    public $miniops;

    /**
     * The project associated with the volume.
     * Mutually exclusive with account parameter
     *
     * @var string
     */
    public $projectid;

    /**
     * Arbitrary volume size
     *
     * @var string
     */
    public $size;

    /**
     * The snapshot ID for the disk volume.
     * Either diskOfferingId or snapshotId must be passed in.
     *
     * @var string
     */
    public $snapshotid;

    /**
     * The ID of the virtual machine;
     * to be used with snapshot Id, VM to which the volume gets attached after creation
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * The ID of the availability zone
     *
     * @var string
     */
    public $zoneid;

    /**
     * Constructor
     *
     * @param   string  $name        The name of the disk volume
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

}
