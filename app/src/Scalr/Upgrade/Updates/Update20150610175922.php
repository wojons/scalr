<?php

namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150610175922 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'fcb693bc-d922-4ade-b5b9-2e1b65c385d4';

    protected $depends = [];

    protected $description = 'Update scaling_metrics table.';

    protected $ignoreChanges = false;

    protected $dbservice = 'adodb';

    private $sql = [];

    /**
     * {@inheritdoc}
     *
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 13;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableForeignKey('fk_612f4f62b80300e9', 'scaling_metrics');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('scaling_metrics');
    }

    protected function run1($stage)
    {
        $this->console->out('Updating `scaling_metrics`.`env_id`; Deleting broken records.');

        $this->db->Execute('UPDATE `scaling_metrics` SET `env_id` = NULL WHERE `env_id` = 0');
        $affected = $this->db->Affected_Rows();
        $this->console->out("Updated {$affected} metrics.");

        $this->db->Execute("
            DELETE FROM scaling_metrics
            WHERE env_id > 0 AND NOT EXISTS (
                SELECT 1 FROM client_environments
                WHERE client_environments.id = scaling_metrics.env_id
            )
        ");
        $affected = $this->db->Affected_Rows();
        $this->console->out("Deleted {$affected} outdated metrics.");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('scaling_metrics', 'account_id');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('scaling_metrics', 'client_id');
    }

    protected function run2($stage)
    {
        $this->console->out('Rename `scaling_metrics`.`client_id` to `scaling_metrics`.`account_id`');

        $this->db->Execute("ALTER TABLE `scaling_metrics` CHANGE `client_id` `account_id` INT(11)");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableForeignKey('fk_a50c892c22f74988', 'scaling_metrics');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('scaling_metrics');
    }

    protected function run3($stage)
    {
        $this->console->out('Updating `scaling_metrics`.`account_id`; Deleting broken records.');

        $this->db->Execute("UPDATE `scaling_metrics` SET `account_id` = NULL WHERE `account_id` = 0");
        $affected = $this->db->Affected_Rows();
        $this->console->out("Updated {$affected} metrics.");

        $this->db->Execute("
            DELETE FROM scaling_metrics
            WHERE account_id > 0 AND NOT EXISTS (
                SELECT 1 FROM clients
                WHERE clients.id = scaling_metrics.account_id
            )
        ");
        $affected = $this->db->Affected_Rows();
        $this->console->out("Deleted {$affected} outdated metrics.");
    }

    protected function isApplied4($stage)
    {
        return !$this->hasTableIndex('scaling_metrics', 'NewIndex1');
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('scaling_metrics');
    }

    protected function run4($stage)
    {
        $this->console->out('Drop index NewIndex1');

        $this->sql[] = "DROP KEY NewIndex1";
    }

    protected function isApplied5($stage)
    {
        return !$this->hasTableIndex('scaling_metrics', 'NewIndex2');
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('scaling_metrics');
    }

    protected function run5($stage)
    {
        $this->console->out('Drop index NewIndex2');

        $this->sql[] = "DROP KEY NewIndex2";
    }

    protected function isApplied6($stage)
    {
        return !$this->hasTableIndex('scaling_metrics', 'NewIndex3');
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTable('scaling_metrics');
    }

    protected function run6($stage)
    {
        $this->console->out('Drop index NewIndex3');

        $this->sql[] = "DROP KEY NewIndex3";
    }

    protected function isApplied7($stage)
    {
        return $this->hasTableIndex('scaling_metrics', 'idx_account_id');
    }

    protected function validateBefore7($stage)
    {
        return $this->hasTableColumn('scaling_metrics', 'account_id');
    }

    protected function run7($stage)
    {
        $this->console->out('Create index idx_account_id');

        $this->sql[] = "ADD INDEX `idx_account_id` (`account_id`)";
    }

    protected function isApplied8($stage)
    {
        return $this->hasTableIndex('scaling_metrics', 'idx_env_id');
    }

    protected function validateBefore8($stage)
    {
        return $this->hasTableColumn('scaling_metrics', 'env_id');
    }

    protected function run8($stage)
    {
        $this->console->out('Create index idx_env_id');

        $this->sql[] = "ADD INDEX `idx_env_id` (`env_id`)";
    }

    protected function isApplied9($stage)
    {
        return $this->hasTableIndex('scaling_metrics', 'idx_unique_name');
    }

    protected function validateBefore9($stage)
    {
        return $this->hasTableColumn('scaling_metrics', 'id') &&
            $this->hasTableColumn('scaling_metrics', 'name');
    }

    protected function run9($stage)
    {
        $this->console->out('Create index idx_env_id');

        $this->sql[] = "ADD INDEX `idx_unique_name` (`account_id`, `name`)";
    }

    protected function isApplied10($stage)
    {
        return $this->hasTableForeignKey('fk_612f4f62b80300e9', 'scaling_metrics');
    }

    protected function validateBefore10($stage)
    {
        return $this->hasTable('scaling_metrics') &&
            $this->hasTable('client_environments');
    }

    protected function run10($stage)
    {
        $this->console->out('Add foreign key for `scaling_metrics`.`env_id` to `client_environments`.`id`');

        $this->sql[] = "ADD CONSTRAINT `fk_612f4f62b80300e9`
            FOREIGN KEY (`env_id`)
            REFERENCES `client_environments` (`id`)
            ON DELETE CASCADE";
    }

    protected function isApplied11($stage)
    {
        return $this->hasTableForeignKey('fk_a50c892c22f74988', 'scaling_metrics');
    }

    protected function validateBefore11($stage)
    {
        return $this->hasTable('scaling_metrics') &&
            $this->hasTable('clients');
    }

    protected function run11($stage)
    {
        $this->console->out('Add foreign key for `scaling_metrics`.`account_id` to `clients`.`id`');

        $this->sql[] = "ADD CONSTRAINT `fk_a50c892c22f74988`
            FOREIGN KEY (`account_id`)
            REFERENCES `clients` (`id`)
            ON DELETE CASCADE";
    }

    protected function isApplied12($stage)
    {
        return 'NO' == $this->getTableColumnDefinition('scaling_metrics', 'name')->isNullable;
    }

    protected function validateBefore12($stage)
    {
        return $this->hasTableColumn('scaling_metrics', 'name');
    }

    protected function run12($stage)
    {
        $this->console->out('Change column `name` - set as not nullable.');
        $this->sql[] = "CHANGE `name` `name` VARCHAR(50) NOT NULL";
    }

    protected function isApplied13($stage)
    {
        return !count($this->sql);
    }

    protected function validateBefore13($stage)
    {
        return $this->hasTable('scaling_metrics') &&
            $this->hasTable('clients') &&
            $this->hasTable('client_environments');
    }

    protected function run13($stage)
    {
        $this->console->out('Applying changes.');

        $this->applyChanges('scaling_metrics', $this->sql);
    }
}
