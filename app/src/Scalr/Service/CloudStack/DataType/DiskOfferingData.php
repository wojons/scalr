<?php
namespace Scalr\Service\CloudStack\DataType;

use DateTime;

/**
 * DiskOfferingData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class DiskOfferingData extends AbstractDataType
{

    /**
     * The id of the disk offering
     *
     * @var string
     */
    public $id;

    /**
     * The date this disk offering was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * Bytes read rate of the disk offering
     *
     * @var string
     */
    public $diskBytesReadRate;

    /**
     * Bytes write rate of the disk offering
     *
     * @var string
     */
    public $diskBytesWriteRate;

    /**
     * Io requests read rate of the disk offering
     *
     * @var string
     */
    public $diskIopsReadRate;

    /**
     * Io requests write rate of the disk offering
     *
     * @var string
     */
    public $diskIopsWriteRate;

    /**
     * The size of the disk offering in GB
     *
     * @var string
     */
    public $disksize;

    /**
     * Whether to display the offering to the end user or not.
     *
     * @var string
     */
    public $displayoffering;

    /**
     * An alternate display text of the disk offering.
     *
     * @var string
     */
    public $displaytext;

    /**
     * The domain name this disk offering belongs to.
     * Ignore this information as it is not currently applicable.
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID this disk offering belongs to.
     * Ignore this information as it is not currently applicable.
     *
     * @var string
     */
    public $domainid;

    /**
     * True if disk offering uses custom size, false otherwise
     *
     * @var string
     */
    public $iscustomized;

    /**
     * True if disk offering uses custom iops, false otherwise
     *
     * @var string
     */
    public $iscustomizediops;

    /**
     * The max iops of the disk offering
     *
     * @var string
     */
    public $maxiops;

    /**
     * The min iops of the disk offering
     *
     * @var string
     */
    public $miniops;

    /**
     * The name of the disk offering
     *
     * @var string
     */
    public $name;

    /**
     * The storage type for this disk offering
     *
     * @var string
     */
    public $storagetype;

    /**
     * The tags for the disk offering
     *
     * @var string
     */
    public $tags;

}