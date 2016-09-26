<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;
use DateTime;

/**
 * SnapshotPolicyResponseData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class SnapshotPolicyResponseData extends AbstractDataType
{

    /**
     * The ID of the snapshot policy
     *
     * @var string
     */
    public $id;

    /**
     * The interval type of the snapshot policy
     *
     * @var string
     */
    public $intervaltype;

    /**
     * Maximum number of snapshots retained
     *
     * @var DateTime
     */
    public $maxsnaps;

    /**
     * Time the snapshot is scheduled to be taken.
     *
     * @var DateTime
     */
    public $schedule;

    /**
     * The time zone of the snapshot policy
     *
     * @var string
     */
    public $timezone;

    /**
     * The ID of the disk volume
     *
     * @var string
     */
    public $volumeid;

}