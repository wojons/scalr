<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150423135003 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '937bf3f4-ba70-4b80-b4b3-d39ec5963142';

    protected $depends = [];

    protected $description = 'Add description field to global variables';

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
            if ($this->hasTable($table) && !$this->hasTableColumn($table, 'description')) {
                $this->console->out("Adding 'description' field to '{$table}'");
                $this->db->Execute("ALTER TABLE `{$table}` ADD COLUMN `description` TEXT NOT NULL DEFAULT ''");
            }
        }
        
    }
}