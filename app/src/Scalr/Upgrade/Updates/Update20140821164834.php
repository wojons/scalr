<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140821164834 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'e163fffa-1f19-4e67-b761-5f6c100bbb04';

    protected $depends = [];

    protected $description = "Adds index idx_new_role_id to farm_roles table";

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
        return $this->hasTable('farm_roles') && $this->hasTableIndex('farm_roles', 'idx_new_role_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farm_roles') && $this->hasTableColumn('farm_roles', 'new_role_id');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `farm_roles` ADD INDEX `idx_new_role_id` (`new_role_id`)");
    }
}