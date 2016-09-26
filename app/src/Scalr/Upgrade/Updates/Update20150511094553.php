<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150511094553 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '1a4e9451-1492-4e51-b369-7a5db2860c9d';

    protected $depends = [];

    protected $description = "Add indexes to event_definitions table";

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
        return $this->hasTable('event_definitions');
    }

    protected function run1($stage)
    {
        $table = 'event_definitions';

        $sql = [];

        if ($this->hasTableColumn($table, 'name') && !$this->hasTableIndex($table, 'idx_name')) {
            $this->console->out('Add index by `name` to `event_definitions`');
            $sql[] = 'ADD INDEX `idx_name` (name(16))';
        }

        if ($this->hasTableColumn($table, 'account_id') && !$this->hasTableIndex($table, 'idx_account_id')) {
            $this->console->out('Add index by `account_id` to `event_definitions`');
            $sql[] = 'ADD INDEX `idx_account_id` (account_id)';
        }

        if ($this->hasTableColumn($table, 'env_id') && !$this->hasTableIndex($table, 'idx_env_id')) {
            $this->console->out('Add index by `env_id` to `event_definitions`');
            $sql[] = 'ADD INDEX `idx_env_id` (env_id)';
        }

        if (!empty($sql)) {
            $this->applyChanges($table, $sql);
        }
    }
}