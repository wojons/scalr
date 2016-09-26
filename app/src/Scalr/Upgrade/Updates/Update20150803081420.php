<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150803081420 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '7820a371-cd21-4d3a-8c0e-f76f0a0ba689';

    protected $depends = [];

    protected $description = 'Add is_quickstart field to roles';

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
        return $this->hasTableColumn('roles', 'is_quick_start');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `roles` ADD `is_quick_start` tinyint(1) NOT NULL DEFAULT '0' AFTER `is_deprecated`, ADD KEY `idx_is_quick_start` (`is_quick_start`)");
        $this->db->Execute("UPDATE `roles` SET `is_quick_start` = 1 WHERE `is_deprecated` = 0 AND `generation` = 2 AND env_id IS NULL");
        $this->console->out('Updated %d roles', $this->db->Affected_Rows());
    }
}
