<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160125090650 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'a97eabe5-e50a-498b-87c3-eabceae36183';

    protected $depends = [];

    protected $description = 'Add env_id and farm_role_id fields for logentries';

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
        return $this->hasTable('logentries') &&
               $this->hasTableColumn('logentries', 'env_id') &&
               $this->hasTableColumn('logentries', 'farm_role_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('logentries', 'farmid');
    }

    protected function run1($stage)
    {
        $arr = [];

        if (!$this->hasTableColumn('logentries', 'env_id')) {
            $this->console->out("Adding env_id column to logentries table...");
            $arr[] = "ADD `env_id` INT DEFAULT NULL AFTER `farmid`";
            $arr[] = "ADD INDEX `idx_env_id` (`env_id`)";
        }

        if (!$this->hasTableColumn('logentries', 'farm_role_id')) {
            $this->console->out("Adding farm_role_id column to logentries table...");
            $arr[] = "ADD `farm_role_id` INT DEFAULT NULL AFTER `env_id`";
        }

        if (!empty($arr)) {
            $this->db->Execute("
                ALTER TABLE `logentries`
                " . join(', ', $arr) . "
            ");
        }
    }
}