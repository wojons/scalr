<?php
namespace Scalr\Upgrade\Updates;

use Scalr\DataType\ScopeInterface;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160201095707 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'f83486aa-8694-48e1-be8f-c1744d5b3273';

    protected $depends = [];

    protected $description = 'Adds object_scope to bundle_tasks table';

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
        return $this->hasTableColumn('bundle_tasks', 'object_scope');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('bundle_tasks');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `bundle_tasks` ADD COLUMN `object_scope` VARCHAR(16) DEFAULT 'environment' AFTER `object`");
    }
}
