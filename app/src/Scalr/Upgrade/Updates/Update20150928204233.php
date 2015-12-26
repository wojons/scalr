<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;

class Update20150928204233 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'b11f2e74-69ab-4f8c-9017-d88b5f462434';

    protected $depends = [];

    protected $description = 'Adds a new Roles and Images (account scope) ACL Resources; Adds accountId to table images';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 5;
    }

    protected function run1($stage)
    {
        $this->console->out('Initializing a new Roles (account scope) ACL Resource');

        $this->db->Execute("INSERT IGNORE acl_role_resources (`role_id`, `resource_id`, `granted`) VALUES(?, ?, 1)", [
            Acl::ROLE_ID_FULL_ACCESS, Acl::RESOURCE_ROLES_ACCOUNT
        ]);

        foreach ([Acl::PERM_ROLES_ACCOUNT_CLONE, Acl::PERM_ROLES_ACCOUNT_MANAGE] as $permission) {
            $this->db->Execute("
                INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
                VALUES (?, ?, ?, 1)
            ", array(
                Acl::ROLE_ID_FULL_ACCESS,
                Acl::RESOURCE_ROLES_ACCOUNT,
                $permission
            ));
        }
    }

    protected function run2($stage)
    {
        $this->console->out('Initializing a new Images (account scope) ACL Resource');

        $this->db->Execute("INSERT IGNORE acl_role_resources (`role_id`, `resource_id`, `granted`) VALUES(?, ?, 1)", [
            Acl::ROLE_ID_FULL_ACCESS, Acl::RESOURCE_IMAGES_ACCOUNT
        ]);

        foreach ([Acl::PERM_IMAGES_ACCOUNT_MANAGE] as $permission) {
            $this->db->Execute("
                INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
                VALUES (?, ?, ?, 1)
            ", array(
                Acl::ROLE_ID_FULL_ACCESS,
                Acl::RESOURCE_IMAGES_ACCOUNT,
                $permission
            ));
        }
    }

    protected function run3()
    {
        $this->console->out('Copying existing access permissions (Roles) from Environment scope to Account scope');

        $this->db->Execute("INSERT IGNORE `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`) " .
            "SELECT `account_role_id`, ? AS `resource_id`, `granted` FROM `acl_account_role_resources` WHERE resource_id = ?", [
            Acl::RESOURCE_ROLES_ACCOUNT,
            Acl::RESOURCE_ROLES_ENVIRONMENT
        ]);

        $this->db->Execute("INSERT IGNORE `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`) " .
            "SELECT `account_role_id`, ? AS `resource_id`, `perm_id`, `granted` FROM `acl_account_role_resource_permissions` WHERE resource_id = ? AND perm_id in(?,?)", [
            Acl::RESOURCE_ROLES_ACCOUNT,
            Acl::RESOURCE_ROLES_ENVIRONMENT,
            Acl::PERM_ROLES_ENVIRONMENT_CLONE,
            Acl::PERM_ROLES_ENVIRONMENT_MANAGE
        ]);
    }

    protected function run4()
    {
        $this->console->out('Copying existing access permissions (Images) from Environment scope to Account scope');

        $this->db->Execute("INSERT IGNORE `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`) " .
            "SELECT `account_role_id`, ? AS `resource_id`, `granted` FROM `acl_account_role_resources` WHERE resource_id = ?", [
            Acl::RESOURCE_IMAGES_ACCOUNT,
            Acl::RESOURCE_IMAGES_ENVIRONMENT
        ]);

        $this->db->Execute("INSERT IGNORE `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`) " .
            "SELECT `account_role_id`, ? AS `resource_id`, `perm_id`, `granted` FROM `acl_account_role_resource_permissions` WHERE resource_id = ? AND perm_id in(?)", [
            Acl::RESOURCE_IMAGES_ACCOUNT,
            Acl::RESOURCE_IMAGES_ENVIRONMENT,
            Acl::PERM_IMAGES_ENVIRONMENT_MANAGE
        ]);
    }

    protected function isApplied5()
    {
        return $this->hasTableColumn('images', 'account_id');
    }

    protected function run5()
    {
        $this->console->out('Adding field accountId to table images');

        $this->db->Execute("
            ALTER TABLE `images`
                ADD COLUMN `account_id` int(11) DEFAULT NULL AFTER `id`,
                ADD INDEX `idx_account_id` (account_id),
                DROP INDEX `idx_id`,
                ADD UNIQUE INDEX `idx_id` (`id`,`platform`,`cloud_location`,`account_id`,`env_id`),
                ADD CONSTRAINT `fk_images_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
        ");

        $this->db->Execute("
            UPDATE `images` AS `i`
            JOIN `client_environments` AS `ce` ON `i`.`env_id` = `ce`.`id`
            SET `i`.`account_id` = `ce`.`client_id`
            WHERE `i`.`env_id` IS NOT NULL;
        ");
    }
}
