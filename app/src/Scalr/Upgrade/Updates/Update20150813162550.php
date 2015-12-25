<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150813162550 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '66c421f4-87e8-406f-99e9-6f9c27698038';

    protected $depends = [];

    protected $description = "Add is_invert column to scaling_metrics table";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('scaling_metrics', 'is_invert');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('scaling_metrics');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding is_invert column to scaling_metrics table...");
        $this->db->Execute("ALTER TABLE `scaling_metrics` ADD `is_invert` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether it should invert logic' AFTER `alias`");

        if ($this->db->GetOne("SELECT is_invert FROM `scaling_metrics` WHERE id=2 AND name='FreeRam' LIMIT 1") != 1) {
            $this->console->out("Setting inverted logic for FreeRam metric as it is by design");
            $this->db->Execute("UPDATE `scaling_metrics` SET is_invert = 1 WHERE id=2 AND name = 'FreeRam'");
        }
    }
}