<?php
namespace Scalr\Service\CloudStack\DataType;

use \DateTime;

/**
 * ServiceOfferingData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ServiceOfferingData extends AbstractDataType
{

    /**
     * The id of the service offering
     *
     * @var string
     */
    public $id;

    /**
     * The number of CPU
     *
     * @var string
     */
    public $cpunumber;

    /**
     * The clock rate CPU speed in Mhz
     *
     * @var string
     */
    public $cpuspeed;

    /**
     * The date this service offering was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * Is this a default system vm offering
     *
     * @var string
     */
    public $defaultuse;

    /**
     * Deployment strategy used to deploy VM.
     *
     * @var string
     */
    public $deploymentplanner;

    /**
     * Bytes read rate of the service offering
     *
     * @var string
     */
    public $diskBytesReadRate;

    /**
     * Bytes write rate of the service offering
     *
     * @var string
     */
    public $diskBytesWriteRate;

    /**
     * Io requests read rate of the service offering
     *
     * @var string
     */
    public $diskIopsReadRate;

    /**
     * Io requests write rate of the service offering
     *
     * @var string
     */
    public $diskIopsWriteRate;

    /**
     * An alternate display text of the service offering.
     *
     * @var string
     */
    public $displaytext;

    /**
     * Domain name for the offering
     *
     * @var string
     */
    public $domain;

    /**
     * The domain id of the service offering
     *
     * @var string
     */
    public $domainid;

    /**
     * The host tag for the service offering
     *
     * @var string
     */
    public $hosttags;

    /**
     * Is true if the offering is customized
     *
     * @var string
     */
    public $iscustomized;

    /**
     * Is this a system vm offering
     *
     * @var string
     */
    public $issystem;

    /**
     * True if the vm needs to be volatile, i.e.,
     * on every reboot of vm from API root disk is discarded and creates a new root disk
     *
     * @var string
     */
    public $isvolatile;

    /**
     * Restrict the CPU usage to committed service offering
     *
     * @var string
     */
    public $limitcpuuse;

    /**
     * The memory in MB
     *
     * @var string
     */
    public $memory;

    /**
     * The name of the service offering
     *
     * @var string
     */
    public $name;

    /**
     * Data transfer rate in megabits per second allowed.
     *
     * @var string
     */
    public $networkrate;

    /**
     * The ha support in the service offering
     *
     * @var string
     */
    public $offerha;

    /**
     * Additional key/value details tied with this service offering
     *
     * @var string
     */
    public $serviceofferingdetails;

    /**
     * The storage type for this service offering
     *
     * @var string
     */
    public $storagetype;

    /**
     * Is this a the systemvm type for system vm offering
     *
     * @var string
     */
    public $systemvmtype;

    /**
     * The tags for the service offering
     *
     * @var string
     */
    public $tags;

}