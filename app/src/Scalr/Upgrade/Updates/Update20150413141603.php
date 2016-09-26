<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150413141603 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'e90b03d0-a07a-4e6b-9573-52f3c2fea07d';

    protected $depends = [];

    protected $description = 'Add foreign key for `roles`.`env_id` and `roles`.`client_id';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private $sql = [];

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 11;
    }

    protected function isApplied1($stage)
    {
        return $this->getTableColumnDefinition('roles', 'env_id')->isNullable();
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('roles') && $this->hasTableColumn('roles', 'env_id');
    }

    protected function run1($stage)
    {
        $this->console->out("Set `roles`.`env_id` nullable");

        $this->sql[] = "CHANGE COLUMN `env_id` `env_id` INT(11) NULL";
    }

    protected function isApplied2($stage)
    {
        return $this->getTableColumnDefinition('roles', 'client_id')->isNullable();
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('roles') && $this->hasTableColumn('roles', 'client_id');
    }

    protected function run2($stage)
    {
        $this->console->out("Set `roles`.`client_id` nullable");

        $this->sql[] = "CHANGE COLUMN `client_id` `client_id` INT(11) NULL";
    }

    protected function isApplied3($stage)
    {
        return empty($this->sql);
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run3($stage)
    {
        $this->console->out("Apply changes");

        $this->applyChanges('roles', $this->sql);

        $this->sql = [];
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run4($stage)
    {
        $this->console->out("Updating `roles`.`env_id`...");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("UPDATE `roles` SET `env_id` = NULL WHERE `env_id` = 0");
            $affected = $this->db->Affected_Rows();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            $this->console->out("Transaction rolled back");
            throw $e;
        }

        $this->console->out("Updated {$affected} roles.");
    }

    protected function isApplied5($stage)
    {
        return false;
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run5($stage)
    {
        $this->console->out("Update `roles`.`client_id`");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("UPDATE `roles` SET `client_id` = NULL WHERE `client_id` = 0");
            $affected = $this->db->Affected_Rows();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            $this->console->out("Transaction rolled back");
            throw $e;
        }

        $this->console->out("Updated {$affected} roles.");
    }

    protected function isApplied6($stage)
    {
        return $this->hasTableForeignKey('fk_1326471b4f680eef', 'roles');
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTable('roles') && $this->hasTable('client_environments');
    }

    protected function run6($stage)
    {
        $this->console->out("Add foreign key for `roles`.`env_id` to `client_environments`.`id`");

        $this->sql[] = "ADD CONSTRAINT `fk_1326471b4f680eef`
                            FOREIGN KEY (`env_id`)
                            REFERENCES `client_environments` (`id`)
                            ON DELETE CASCADE
                            ON UPDATE CASCADE";
    }

    protected function isApplied7($stage)
    {
        return $this->hasTableForeignKey('fk_6ab3b53cbdfa0be8', 'roles');
    }

    protected function validateBefore7($stage)
    {
        return $this->hasTable('roles') && $this->hasTable('clients');
    }

    protected function run7($stage)
    {
        $this->console->out("Add foreign key for `roles`.`client_id` to `clients`.`id`");

        $this->sql[] = "ADD CONSTRAINT `fk_6ab3b53cbdfa0be8`
                            FOREIGN KEY (`client_id`)
                            REFERENCES `clients` (`id`)
                            ON DELETE CASCADE
                            ON UPDATE CASCADE";
    }

    protected function isApplied8($stage)
    {
        if (empty($this->sql)) {
            $this->console->out("Changes from stages 4-5 rolled back as unnecessary");

            $this->db->RollbackTrans();

            return true;
        }

        return false;
    }

    protected function validateBefore8($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run8($stage)
    {
        $this->console->out("Apply changes");

        $this->applyChanges('roles', $this->sql);

        $this->sql = [];
    }

    protected function isApplied9($stage)
    {
        return $this->getTableColumnDefinition('role_categories', 'env_id')->isNullable();
    }

    protected function validateBefore9($stage)
    {
        return $this->hasTable('role_categories') && $this->hasTableColumn('role_categories', 'env_id');
    }

    protected function run9($stage)
    {
        $this->console->out("Set `role_categories`.`env_id` nullable");

        $this->applyChanges('role_categories', ["CHANGE COLUMN `env_id` `env_id` INT(11) NULL"]);
    }

    protected function isApplied10($stage)
    {
        return false;
    }

    protected function validateBefore10($stage)
    {
        return $this->hasTable('role_categories');
    }

    protected function run10($stage)
    {
        $this->console->out("Updating `role_categories`.`env_id` ...");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("UPDATE `role_categories` SET `env_id` = NULL WHERE `env_id` = 0");
            $affected = $this->db->Affected_Rows();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            $this->console->out("Transaction rolled back");
            throw $e;
        }

        $this->console->out("Updated {$affected} role_categories.");
    }

    protected function isApplied11($stage)
    {
        if ($this->hasTableForeignKey('fk_d98efdec7207c239', 'role_categories')) {
            $this->console->out("Changes from stage 10 rolled back as unnecessary");

            $this->db->RollbackTrans();

            return true;
        }

        return false;
    }

    protected function validateBefore11($stage)
    {
        return $this->hasTable('role_categories') && $this->hasTable('client_environments');
    }

    protected function run11($stage)
    {
        $this->console->out("Add foreign key for `role_categories`.`env_id` to `client_environments`.`id`");

        $this->applyChanges('role_categories', [
            "ADD CONSTRAINT `fk_d98efdec7207c239`
                FOREIGN KEY (`env_id`)
                REFERENCES `client_environments` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE"
        ]);
    }
}