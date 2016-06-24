<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Definition;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151222172257 extends AbstractUpdate implements SequenceInterface
{

    const PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS = 'manage-projects';

    protected $uuid = '54ecbb71-c759-4cb1-94e7-1c75b3533cc2';

    protected $depends = [];

    protected $description = 'Adding new ACL Resources for Projects';

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 6;
    }

    protected function isApplied1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT') &&
               $this->db->GetOne("
                   SELECT `granted` FROM `acl_role_resources`
                   WHERE `resource_id` = ? AND `role_id` = ?
                   LIMIT 1
               ", [
                   Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
                   Acl::ROLE_ID_FULL_ACCESS,
               ]) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT') &&
               Definition::has(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding ACL resource to manage Projects (account scope)");

        $this->db->Execute("
            INSERT IGNORE INTO `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT
        ]);

        $this->db->Execute("
            INSERT IGNORE INTO `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`)
            SELECT `account_role_id`, ?, `granted` FROM `acl_account_role_resources`
            WHERE `resource_id` = ?
        ", [
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::RESOURCE_ANALYTICS_ACCOUNT
        ]);
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT') &&
               Definition::has(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT) &&
               defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_CREATE');
    }

    protected function run2($stage)
    {
        $this->console->out("Adding ACL permissions to create Projects (account scope)");

        $this->db->Execute("
            INSERT IGNORE INTO `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_CREATE
        ]);

        $this->db->Execute("
            INSERT IGNORE INTO `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`)
            SELECT `account_role_id`, ?, ?, `granted` FROM `acl_account_role_resource_permissions`
            WHERE `resource_id` = ? AND `perm_id` = ?
        ", [
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_CREATE,
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            static::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS
        ]);
    }

    protected function isApplied3($stage)
    {
        return false;
    }

    protected function validateBefore3($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT) &&
        defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_UPDATE');
    }

    protected function run3($stage)
    {
        $this->console->out("Adding ACL permissions to update Projects (account scope)");

        $this->db->Execute("
            INSERT IGNORE INTO `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_UPDATE
        ]);

        $this->db->Execute("
            INSERT IGNORE INTO `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`)
            SELECT `account_role_id`, ?, ?, `granted` FROM `acl_account_role_resource_permissions`
            WHERE `resource_id` = ? AND `perm_id` = ?
        ", [
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_UPDATE,
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            static::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS
        ]);
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT) &&
        defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_DELETE');
    }

    protected function run4($stage)
    {
        $this->console->out("Adding ACL permissions to delete Projects (account scope)");

        $this->db->Execute("
            INSERT IGNORE INTO `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_DELETE
        ]);

        $this->db->Execute("
            INSERT IGNORE INTO `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`)
            SELECT `account_role_id`, ?, ?, `granted` FROM `acl_account_role_resource_permissions`
            WHERE `resource_id` = ? AND `perm_id` = ?
        ", [
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ACCOUNT_DELETE,
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            static::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS
        ]);
    }

    protected function isApplied5($stage)
    {
        return false;

    }

    protected function validateBefore5($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT) &&
        defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_PROJECTS_ALLOCATE_BUDGET');
    }

    protected function run5($stage)
    {
        $this->console->out("Move ACL permission to allocate Projects budgets to the new resource");

        $this->db->Execute("
            UPDATE `acl_role_resource_permissions` SET `resource_id` = ?
            WHERE `perm_id` = ?
        ", [
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ALLOCATE_BUDGET
        ]);

        $this->db->Execute("
            UPDATE `acl_account_role_resource_permissions` SET `resource_id` = ?
            WHERE `perm_id` = ?
        ", [
            Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT,
            Acl::PERM_ANALYTICS_PROJECTS_ALLOCATE_BUDGET
        ]);
    }

    protected function isApplied6($stage)
    {
        return false;

    }

    protected function validateBefore6($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_FARMS') &&
               Definition::has(Acl::RESOURCE_FARMS) &&

               defined('Scalr\\Acl\\Acl::RESOURCE_TEAM_FARMS') &&
               Definition::has(Acl::RESOURCE_TEAM_FARMS) &&

               defined('Scalr\\Acl\\Acl::RESOURCE_OWN_FARMS') &&
               Definition::has(Acl::RESOURCE_OWN_FARMS) &&

               defined('Scalr\\Acl\\Acl::PERM_FARMS_PROJECTS');
    }

    protected function run6($stage)
    {
        $this->console->out("Add ACL permission to update Farms Projects");

        foreach ([Acl::RESOURCE_FARMS, Acl::RESOURCE_TEAM_FARMS, Acl::RESOURCE_OWN_FARMS] as $resourceId) {
            $this->db->Execute("
                INSERT IGNORE INTO `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
                VALUES (?, ?, ?, 1)
            ", [
                Acl::ROLE_ID_FULL_ACCESS,
                $resourceId,
                Acl::PERM_FARMS_PROJECTS
            ]);

            $this->db->Execute("
                INSERT IGNORE INTO `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`)
                SELECT `account_role_id`, `resource_id`, ?, `granted` FROM `acl_account_role_resource_permissions`
                WHERE `resource_id` = ? AND `perm_id` = ?
            ", [
                Acl::PERM_FARMS_PROJECTS,
                $resourceId,
                Acl::PERM_FARMS_UPDATE
            ]);
        }
    }
}