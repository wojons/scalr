<?php
namespace Scalr\Service\CloudStack\Services\Snapshot\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateSnapshotPolicyData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateSnapshotPolicyData extends AbstractDataType
{

    /**
     * Required
     * The ID of the disk volume
     *
     * @var string
     */
    public $volumeid;

    /**
     * Required
     * Valid values are HOURLY, DAILY, WEEKLY, and MONTHLY
     *
     * @var string
     */
    public $intervaltype;

    /**
     * Required
     * Maximum number of snapshots to retain
     *
     * @var string
     */
    public $maxsnaps;

    /**
     * Required
     * Time the snapshot is scheduled to be taken.
     * Format is:* if HOURLY, MM* if DAILY, MM:HH* if WEEKLY, MM:HH:DD (1-7)* if MONTHLY, MM:HH:DD (1-28)
     *
     * @var string
     */
    public $schedule;

    /**
     * Required
     * Specifies a timezone for this command.
     * For more information on the timezone parameter, see Time Zone Format.
     *
     * @var string
     */
    public $timezone;

    /**
     * Constructor
     *
     * @param   string  $intervaltype    Valid values are HOURLY, DAILY, WEEKLY, and MONTHLY
     * @param   string  $maxsnaps    Maximum number of snapshots to retain
     * @param   string  $schedule    Time the snapshot is scheduled to be taken.
     *                               Format is:* if HOURLY, MM* if DAILY, MM:HH* if WEEKLY, MM:HH:DD (1-7)* if MONTHLY, MM:HH:DD (1-28)
     * @param   string  $timezone    Specifies a timezone for this command.
     *                               For more information on the timezone parameter, see Time Zone Format.
     * @param   string  $volumeid    The ID of the disk volume
     */
    public function __construct($intervaltype, $maxsnaps, $schedule, $timezone, $volumeid)
    {
        $this->intervaltype = $intervaltype;
        $this->maxsnaps = $maxsnaps;
        $this->schedule = $schedule;
        $this->timezone = $timezone;
        $this->volumeid = $volumeid;
    }

}