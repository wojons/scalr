<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListSnapshotData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListSnapshotData extends AbstractDataType
{

    /**
     * List resources by account. Must be used with the domainId parameter.
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
     * Lists snapshot by snapshot ID
     *
     * @var string
     */
    public $id;

    /**
     * Valid values are HOURLY, DAILY, WEEKLY, and MONTHLY.
     *
     * @var string
     */
    public $intervaltype;

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
     * Lists snapshot by snapshot name
     *
     * @var string
     */
    public $name;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

    /**
     * Valid values are MANUAL or RECURRING.
     *
     * @var string
     */
    public $snapshottype;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * The ID of the disk volume
     *
     * @var string
     */
    public $volumeid;

    /**
     * List snapshots by zone id
     *
     * @var string
     */
    public $zoneid;

}