<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150119194458 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '84c29cb1-50b0-4f47-ab01-67ca33b4154f';

    protected $depends = [];

    protected $description = "Update events table with statistical columns";

    protected $ignoreChanges = true;

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
        return $this->hasTableColumn('events', 'wh_total');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('events');
    }
    
    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('events', 'scripts_total');
    }
    
    protected function validateBefore2($stage)
    {
        return $this->hasTable('events');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `events` ADD `wh_total` INT(3) NULL DEFAULT '0' , ADD `wh_completed` INT(3) NULL DEFAULT '0' , ADD `wh_failed` INT(3) NULL DEFAULT '0' ;");
    }
    
    protected function run2($stage)
    {
        $this->db->Execute("ALTER TABLE `events` ADD `scripts_total` INT(3) NULL DEFAULT '0' , ADD `scripts_completed` INT(3) NULL DEFAULT '0' , ADD `scripts_failed` INT(3) NULL DEFAULT '0' ;");
        $this->db->Execute("ALTER TABLE `events` ADD `scripts_timedout` INT(3) NULL DEFAULT '0' ;");
        $this->db->Execute("ALTER TABLE `events` ENGINE = INNODB");
    }
}