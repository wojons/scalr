<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140804164748 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '3390da80-4daa-4f94-ae89-a4cd4b926835';

    protected $depends = [];

    protected $description = 'Webhooks & Bundle task structure changes';

    protected $ignoreChanges = false;

    protected $dbservice = 'adodb';

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
        return $this->hasTable('webhook_history') && $this->hasTableColumn('webhook_history', 'error_msg');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `webhook_history` ADD `error_msg` TEXT NULL");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTable('bundle_tasks') && $this->hasTableColumn('bundle_tasks', 'generation');
    }

    protected function run2($stage)
    {
        $this->db->Execute("ALTER TABLE `bundle_tasks` ADD `generation` TINYINT(1) NULL DEFAULT '1'");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTable('bundle_tasks') && $this->hasTableColumn('bundle_tasks', 'object');
    }

    protected function run3($stage)
    {
        $this->db->Execute("ALTER TABLE `bundle_tasks` ADD `object` VARCHAR(20) NULL DEFAULT 'role'");
    }
}