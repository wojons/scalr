<?php
namespace Scalr\Service\CloudStack\Services\Volume\DataType;

use Scalr\Service\CloudStack\DataType\JobStatusData;
use DateTime;
use Scalr\Service\CloudStack\DataType\ResponseTagsList;

/**
 * VolumeResponseData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with volume
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class VolumeResponseData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('tags');

    /**
     * ID of the disk volume
     *
     * @var string
     */
    public $id;

    /**
     * The account associated with the disk volume
     *
     * @var string
     */
    public $account;

    /**
     * The date the volume was attached to a VM instance
     *
     * @var DateTime
     */
    public $attached;

    /**
     * The date the disk volume was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * The boolean state of whether the volume is destroyed or not
     *
     * @var string
     */
    public $destroyed;

    /**
     * The ID of the device on user vm the volume is attahed to.
     * This tag is not returned when the volume is detached.
     *
     * @var string
     */
    public $deviceid;

    /**
     * Bytes read rate of the disk volume
     *
     * @var string
     */
    public $diskBytesReadRate;

    /**
     * Bytes write rate of the disk volume
     *
     * @var string
     */
    public $diskBytesWriteRate;

    /**
     * Io requests read rate of the disk volume
     *
     * @var string
     */
    public $diskIopsReadRate;

    /**
     * Io requests write rate of the disk volume
     *
     * @var string
     */
    public $diskIopsWriteRate;

    /**
     * The display text of the disk offering
     *
     * @var string
     */
    public $diskofferingdisplaytext;

    /**
     * ID of the disk offering
     *
     * @var string
     */
    public $diskofferingid;

    /**
     * Name of the disk offering
     *
     * @var string
     */
    public $diskofferingname;

    /**
     * An optional field whether to the display the volume to the end user or not.
     *
     * @var string
     */
    public $displayvolume;

    /**
     * The domain associated with the disk volume
     *
     * @var string
     */
    public $domain;

    /**
     * The ID of the domain associated with the disk volume
     *
     * @var string
     */
    public $domainid;

    /**
     * Hypervisor the volume belongs to
     *
     * @var string
     */
    public $hypervisor;

    /**
     * True if the volume is extractable, false otherwise
     *
     * @var string
     */
    public $isextractable;

    /**
     * Max iops of the disk volume
     *
     * @var string
     */
    public $maxiops;

    /**
     * Min iops of the disk volume
     *
     * @var string
     */
    public $miniops;

    /**
     * Name of the disk volume
     *
     * @var string
     */
    public $name;

    /**
     * The path of the volume
     *
     * @var string
     */
    public $path;

    /**
     * The project name of the vpn
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the vpn
     *
     * @var string
     */
    public $projectid;

    /**
     * Need quiesce vm or not when taking snapshot
     *
     * @var string
     */
    public $quiescevm;

    /**
     * The display text of the service offering for root disk
     *
     * @var string
     */
    public $serviceofferingdisplaytext;

    /**
     * ID of the service offering for root disk
     *
     * @var string
     */
    public $serviceofferingid;

    /**
     * Name of the service offering for root disk
     *
     * @var string
     */
    public $serviceofferingname;

    /**
     * Size of the disk volume
     *
     * @var string
     */
    public $size;

    /**
     * ID of the snapshot from which this volume was created
     *
     * @var string
     */
    public $snapshotid;

    /**
     * The state of the disk volume
     *
     * @var string
     */
    public $state;

    /**
     * The status of the volume
     *
     * @var string
     */
    public $status;

    /**
     * Name of the primary storage hosting the disk volume
     *
     * @var string
     */
    public $storage;

    /**
     * Id of the primary storage hosting the disk volume; returned to admin user only
     *
     * @var string
     */
    public $storageid;

    /**
     * Shared or local storage
     *
     * @var string
     */
    public $storagetype;

    /**
     * Type of the disk volume (ROOT or DATADISK)
     *
     * @var string
     */
    public $type;

    /**
     * Id of the virtual machine
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * Display name of the virtual machine
     *
     * @var string
     */
    public $vmdisplayname;

    /**
     * Name of the virtual machine
     *
     * @var string
     */
    public $vmname;

    /**
     * State of the virtual machine
     *
     * @var string
     */
    public $vmstate;

    /**
     * ID of the availability zone
     *
     * @var string
     */
    public $zoneid;

    /**
     * Name of the availability zone
     *
     * @var string
     */
    public $zonename;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  VolumeResponseData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }

}