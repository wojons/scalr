<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\DataType;

use Scalr\Service\CloudStack\DataType\JobStatusData;
use DateTime;
use Scalr\Service\CloudStack\DataType\ResponseTagsList;

/**
 * SnapshotResponseData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with volume
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class SnapshotResponseData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('tags');

    /**
     * ID of the snapshot
     *
     * @var string
     */
    public $id;

    /**
     * The account associated with the snapshot
     *
     * @var string
     */
    public $account;

    /**
     * The date the snapshot was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * The domain name of the snapshot's account
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID of the snapshot's account
     *
     * @var string
     */
    public $domainid;

    /**
     * Valid types are hourly, daily, weekly, monthy, template, and none.
     *
     * @var string
     */
    public $intervaltype;

    /**
     * Name of the snapshot
     *
     * @var string
     */
    public $name;

    /**
     * The project name of the snapshot
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the snapshot
     *
     * @var string
     */
    public $projectid;

    /**
     * Indicates whether the underlying storage supports reverting the volume to this snapshot
     *
     * @var string
     */
    public $revertable;

    /**
     * The type of the snapshot
     *
     * @var string
     */
    public $snapshottype;

    /**
     * The state of the snapshot.
     * BackedUp means that snapshot is ready to be used;
     * Creating - the snapshot is being allocated on the primary storage;
     * BackingUp - the snapshot is being backed up on secondary storage
     *
     * @var string
     */
    public $state;

    /**
     * ID of the disk volume
     *
     * @var string
     */
    public $volumeid;

    /**
     * Name of the disk volume
     *
     * @var string
     */
    public $volumename;

    /**
     * Type of the disk volume
     *
     * @var string
     */
    public $volumetype;

    /**
     * Id of the availability zone
     *
     * @var string
     */
    public $zoneid;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  SnapshotResponseData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }
}