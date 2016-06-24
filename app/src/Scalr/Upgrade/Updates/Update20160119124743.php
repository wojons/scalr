<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr_SchedulerTask;

/**
 * Class Update20160119124743
 *
 * SCALRCORE-2509 Remove scheduler task endTime
 *  - stage1: DML change `scheduler`.`status` 'Finished' to 'Suspended'
 *  - stage2: DDL drop column `scheduler`.`end_time`
 *
 * @namespace Scalr\Upgrade\Updates
 */
class Update20160119124743 extends AbstractUpdate implements SequenceInterface
{
    const TABLE_SCHEDULER = 'scheduler';

    const COLUMN_STATUS   = 'status';

    const COLUMN_END_TIME = 'end_time';

    const STATUS_FINISHED = 'Finished';

    protected $uuid = 'b4e1e943-5487-4300-8d27-90ad4462a347';

    protected $depends = [];

    protected $description = 'Refactor table scheduler.';

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('scheduler', 'status')
            && !($this->db->GetOne("SELECT 1 FROM `scheduler` WHERE `status` = ? LIMIT 1", [self::STATUS_FINISHED]) == 1);
    }

    protected function validateBefore1($stage)
    {
        return !defined('Scalr_SchedulerTask::STATUS_FINISHED')
            && defined('Scalr_SchedulerTask::STATUS_SUSPENDED')
            && $this->hasTableColumn('scheduler', 'status');
    }


    protected function run1($stage)
    {
        $this->console->out("Change scheduler.status values from 'Finished' to 'Suspended'.");

        $this->db->Execute("
            UPDATE `scheduler`
            SET `status` = ?
            WHERE `status` = ?
        ", [
            Scalr_SchedulerTask::STATUS_SUSPENDED,
            self::STATUS_FINISHED
        ]);
    }

    protected function isApplied2($stage)
    {
        return !$this->hasTableColumn('scheduler', 'end_time');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('scheduler', 'end_time');
    }

    protected function run2($stage)
    {
        $this->console->out("Drop scheduler.end_time column.");

        $this->db->Execute("ALTER TABLE `scheduler` DROP COLUMN `end_time`");
    }
}
