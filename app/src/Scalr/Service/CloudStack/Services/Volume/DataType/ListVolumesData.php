<?php
namespace Scalr\Service\CloudStack\Services\Volume\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListVolumesData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListVolumesData extends AbstractDataType
{

    /**
     * List resources by account.
     * Must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * List only resources belonging to the domain specified
     *
     * @var string
     */
    public $domainid;

    /**
     * List volumes on specified host
     *
     * @var string
     */
    public $hostid;

    /**
     * The ID of the disk volume
     *
     * @var string
     */
    public $id;

    /**
     * Defaults to false, but if true, lists all resources from the parent specified by the domainId till leaves.
     *
     * @var string
     */
    public $isrecursive;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * If set to false, list only resources belonging to the command's caller;
     * if set to true - list resources that the caller is authorized to see.
     * Default value is false
     *
     * @var string
     */
    public $listall;

    /**
     * The name of the disk volume
     *
     * @var string
     */
    public $name;

    /**
     * The pod id the disk volume belongs to
     *
     * @var string
     */
    public $podid;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

    /**
     * The ID of the storage pool, available to ROOT admin only
     *
     * @var string
     */
    public $storageid;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * The type of disk volume
     *
     * @var string
     */
    public $type;

    /**
     * The ID of the virtual machine
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

}