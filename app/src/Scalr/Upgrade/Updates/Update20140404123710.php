<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140404123710 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '516287bf-8692-404b-9a8b-b024fac7f8e0';

    protected $depends = array('1b5f5a56-44d9-4aa7-9d1e-eadd6015ed6f');

    protected $description = 'Add key field to aggregate identical system log messages; Convert error field to TEXT in services_db_backups_history';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumnType('logentries', 'id', 'binary(16)');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('logentries');
    }

    protected function run1($stage)
    {
        $this->console->out('Truncate table logentries');
        $this->db->Execute('TRUNCATE TABLE logentries');

        if ($this->hasTableColumn('logentries', 'cnt_key')) {
            $this->console->out('Removing previous cnt_key');
            $this->db->Execute('ALTER TABLE logentries DROP cnt_key');
        }

        $this->db->Execute('ALTER TABLE logentries MODIFY `id` BINARY(16)');
    }

    protected function isApplied2()
    {
        return $this->hasTableColumnType('services_db_backups_history', 'error', 'text');
    }

    protected function validateBefore2()
    {
        return $this->hasTable('services_db_backups_history');
    }

    protected function run2()
    {
        $this->console->out('Expand field in services_db_backups_history');
        $this->db->Execute('ALTER TABLE `services_db_backups_history` CHANGE `error` `error` TEXT NULL DEFAULT NULL');
    }

    protected function isApplied3()
    {
        return $this->hasTableColumn('logentries', 'cnt');
    }

    protected function run3()
    {
        $this->console->out('Add field cnt to logentries');
        $this->db->Execute("ALTER TABLE logentries ADD `cnt` int(11) NOT NULL DEFAULT '1'");
    }
}
