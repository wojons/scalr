<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20141027120948 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ccfa8636-2a39-4a95-824a-e45a6b1a0518';

    protected $depends = [];

    protected $description = 'Chef servers scopes';

    protected $ignoreChanges = true;

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
        return $this->hasTableColumn('services_chef_servers', 'account_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('services_chef_servers');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'account_id' to the services_chef_servers table...");
        $this->db->Execute("ALTER TABLE `services_chef_servers` ADD COLUMN `account_id` INT(11) NULL AFTER `id`");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('services_chef_servers', 'level');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('services_chef_servers');
    }

    protected function run2($stage)
    {
        $this->console->out("Adding 'level' to the services_chef_servers table...");
        $this->db->Execute("ALTER TABLE `services_chef_servers` ADD COLUMN `level` TINYINT NOT NULL COMMENT '1 - Scalr, 2 - Account, 4 - Env' AFTER `env_id`");
    }

    protected function isApplied3($stage)
    {
        return false;
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTableColumn('services_chef_servers', 'level') && $this->hasTableColumn('services_chef_servers', 'account_id');
    }

    protected function run3($stage)
    {
        $this->console->out("Filling 'level' and 'account_id' columns in the services_chef_servers table...");
        $res = $this->db->Execute("
            SELECT s.id, e.client_id AS accountId
            FROM services_chef_servers AS s
            INNER JOIN client_environments e ON s.env_id = e.id
        ");

        while ($rec = $res->FetchRow()) {
            $this->db->Execute("UPDATE `services_chef_servers` SET account_id = ?, level = 4 WHERE id = ?", array($rec['accountId'], $rec['id']));
        }
    }

    protected function isApplied4($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_SERVICES_ADMINISTRATION_CHEF') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_SERVICES_ADMINISTRATION_CHEF,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore4($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_SERVICES_ADMINISTRATION_CHEF') &&
               Definition::has(Acl::RESOURCE_SERVICES_ADMINISTRATION_CHEF);
    }

    protected function run4($stage)
    {
        $this->console->out("Adding Chef (account level) ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_SERVICES_ADMINISTRATION_CHEF
        ));
    }
}