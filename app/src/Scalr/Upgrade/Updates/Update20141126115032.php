<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141126115032 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2971a4e1-2793-400a-bd71-5e4754602136';

    protected $depends = [];

    protected $description = "Creates index on farms.status column";

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
        return $this->hasTableIndex('farms', 'idx_status');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farms') && $this->hasTableColumn('farms', 'status');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding idx_status index to farms table...");
        $this->db->Execute("ALTER TABLE `farms` ADD INDEX `idx_status` (`status` ASC)");
    }
}