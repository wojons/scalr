<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160126125015 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '36b2dbd4-f919-4d7e-b84c-9c0ea077c7f8';

    protected $depends = [];

    protected $description = 'Add account_id to role_categories';

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('role_categories', 'account_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('role_categories');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'account_id' to the role_categories table...");
        $this->db->Execute("ALTER TABLE `role_categories` ADD COLUMN `account_id` INT(11) NULL AFTER `id`");
        
        $this->console->out("Set account_id where env_id is not null");
        $this->db->Execute("
            UPDATE `role_categories` rc
            INNER JOIN `client_environments` ce ON rc.env_id = ce.id
            SET rc.account_id = ce.client_id");
        
    }
    
    protected function isApplied2($stage)
    {
        return $this->hasTableForeignKey('fk_90eb45b25a4bddd0', 'role_categories');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('role_categories') && $this->hasTable('clients');
    }

    protected function run2($stage)
    {
        $this->console->out("Add foreign key for `role_categories`.`account_id` to `clients`.`id`");

        $this->db->Execute("
            ALTER TABLE `role_categories` 
            ADD CONSTRAINT `fk_90eb45b25a4bddd0`
            FOREIGN KEY (`account_id`)
            REFERENCES `clients` (`id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE");
    }
  
    protected function isApplied3($stage)
    {
        return false;
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('roles') && $this->hasTable('role_categories');
    }

    protected function run3($stage)
    {
        $this->console->out("Update roles. Replace ghost category roles IDs with NULLs");

        $this->db->Execute("
            UPDATE `roles` r
            LEFT JOIN `role_categories` rc ON r.cat_id = rc.id
            SET cat_id = NULL
            WHERE rc.id IS NULL");
    }
    
    protected function isApplied4($stage)
    {
        return $this->hasTableForeignKey('fk_fk_bc65c689039a0d77', 'roles');
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('roles') && $this->hasTable('role_categories');
    }

    protected function run4($stage)
    {
        $this->console->out("Add foreign key for `role`.`cat_id` to `role_categories`.`id`");

        $this->db->Execute("
            ALTER TABLE `roles` 
            ADD CONSTRAINT `fk_bc65c689039a0d77`
            FOREIGN KEY (`cat_id`)
            REFERENCES `role_categories` (`id`)
            ON DELETE RESTRICT
            ON UPDATE CASCADE");
    }
    
}