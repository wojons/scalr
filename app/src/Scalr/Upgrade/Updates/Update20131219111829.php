<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20131219111829 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '7b9bb8ed-02b6-46d8-8b06-438612541e3c';

    protected $depends = array(
        '1a6723e8-0173-4f74-bd9c-c21dc97365aa'
    );

    protected $description = 'Optimizes log rotation queries';

    protected $type;

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableIndex('scripting_log', 'idx_dtadded');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('scripting_log', 'dtadded');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding idx_dtadded index to scripting_log table");
        $this->db->Execute("
            ALTER TABLE `scripting_log` ADD INDEX `idx_dtadded` (`dtadded`)
        ");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableIndex('logentries', 'idx_time');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('logentries', 'time');
    }

    protected function run2($stage)
    {
        $this->console->out("Adding idx_time index to logentries table");
        $this->db->Execute("
            ALTER TABLE `logentries` ADD INDEX `idx_time` (`time`)
        ");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableIndex('messages', 'idx_type_status_dt');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTableColumn('messages', 'dtlasthandleattempt') &&
               $this->hasTableColumn('messages', 'status') &&
               $this->hasTableColumn('messages', 'type');
    }

    protected function run3($stage)
    {
        $this->console->out("Adding idx_type_status_dt index to messages table");
        $this->db->Execute("
            ALTER TABLE `messages` ADD INDEX `idx_type_status_dt` (`type`, `status`, `dtlasthandleattempt`)
        ");
    }
}