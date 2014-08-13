<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140108145520 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '6e0d5d0d-0461-44dc-9b99-e329ec7fade3';

    protected $depends = array('ae207d54-41f8-4840-99bf-8ee329cf37fa');

    protected $description = 'Add field timeout to table scripts; add indexes to table scripting_log';

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
        return $this->hasTable('scripts') && $this->hasTableColumn('scripts', 'timeout');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE `scripts` ADD `timeout` INT(11) NULL  DEFAULT NULL  AFTER `issync`');
    }

    protected function isApplied2()
    {
        return $this->hasTable('scripting_log') && $this->hasTableIndex('scripting_log', 'idx_script_name');
    }

    protected function run2()
    {
        $this->console->out('Creating index idx_script_name');
        $this->db->Execute('ALTER TABLE `scripting_log` ADD INDEX `idx_script_name` (`script_name`)');
    }

    protected function isApplied3()
    {
        return $this->hasTable('scripting_log') && $this->hasTableIndex('scripting_log', 'idx_event');
    }

    protected function run3()
    {
        $this->console->out('Creating index idx_event');
        $this->db->Execute('ALTER TABLE `scripting_log` ADD INDEX `idx_event` (`event`)');
    }
}
