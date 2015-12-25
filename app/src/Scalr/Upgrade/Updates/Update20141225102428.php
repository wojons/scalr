<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Definition;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141225102428 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'f00c516d-6c7e-4211-8b05-427b4fe97938';

    protected $depends = [];

    protected $description = 'Creating Analytics account scope permissions';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ACCOUNT') &&
                defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS') &&
                $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resource_permissions`
                    WHERE `resource_id` = ? AND `role_id` = ? AND `perm_id` = ?
                    LIMIT 1
                    ", array(
                    Acl::RESOURCE_ANALYTICS_ACCOUNT,
                    Acl::ROLE_ID_FULL_ACCESS,
                    Acl::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS
                )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ACCOUNT') &&
                defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS') &&
                Definition::has(Acl::RESOURCE_ANALYTICS_ACCOUNT) &&
                $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resources`
                    WHERE `resource_id` = ? AND `role_id` = ?
                    LIMIT 1
                ", array(
                    Acl::RESOURCE_ANALYTICS_ACCOUNT,
                    Acl::ROLE_ID_FULL_ACCESS,
                )) == 1;
    }

    protected function run1($stage)
    {
        $this->console->out('Creating Analytics account scope Manage projects permission');
        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            Acl::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS
        ));
    }

    protected function isApplied2($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ACCOUNT') &&
        defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_ACCOUNT_ALLOCATE_BUDGET') &&
        $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resource_permissions`
                    WHERE `resource_id` = ? AND `role_id` = ? AND `perm_id` = ?
                    LIMIT 1
                    ", array(
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::PERM_ANALYTICS_ACCOUNT_ALLOCATE_BUDGET
        )) == 1;
    }

    protected function validateBefore2($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ACCOUNT') &&
        defined('Scalr\\Acl\\Acl::PERM_ANALYTICS_ACCOUNT_ALLOCATE_BUDGET') &&
        Definition::has(Acl::RESOURCE_ANALYTICS_ACCOUNT) &&
        $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resources`
                    WHERE `resource_id` = ? AND `role_id` = ?
                    LIMIT 1
                ", array(
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function run2($stage)
    {
        $this->console->out('Creating Analytics account scope Allocate budget permission');
        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            Acl::PERM_ANALYTICS_ACCOUNT_ALLOCATE_BUDGET
        ));
    }

}
