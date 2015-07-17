<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150529133039 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '27fef2c5-9a1b-44aa-b0f2-121b6124d34e';

    protected $depends = [];

    protected $description = 'Add category field to global variables';

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
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $tables = ['variables', 'account_variables', 'client_environment_variables', 'role_variables', 'farm_variables', 'farm_role_variables', 'server_variables'];
        foreach ($tables as $table) {
            if ($this->hasTable($table) && !$this->hasTableColumn($table, 'category')) {
                $this->console->out("Adding 'category' field to '{$table}'");
                $this->db->Execute("ALTER TABLE `{$table}` ADD COLUMN `category` VARCHAR(32) NOT NULL DEFAULT '' AFTER `value`");
            }
        }
    }
}
