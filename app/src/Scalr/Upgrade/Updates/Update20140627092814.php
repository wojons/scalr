<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20140627092814 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2b336d25-d327-4c00-aaa2-e990aa8df7b8';

    protected $depends = [];

    protected $description = 'Creating Account orchestration resource';

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
        return defined('Scalr\\Acl\\Acl::RESOURCE_ORCHESTRATION_ACCOUNT') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_ORCHESTRATION_ACCOUNT,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function isApplied2($stage)
    {
        return $this->db->GetOne("
            SELECT 1 FROM `acl_account_role_resources`
            WHERE `resource_id` = ? AND `granted` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_ORCHESTRATION_ACCOUNT,
            0,
        )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ORCHESTRATION_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_ORCHESTRATION_ACCOUNT);
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ORCHESTRATION_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_ORCHESTRATION_ACCOUNT);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding Account orchestration resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ORCHESTRATION_ACCOUNT
        ));
    }

    protected function run2($stage)
    {
        $this->console->out("Update auto-created full access roles");
        $this->db->Execute("
            INSERT IGNORE `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`)
            SELECT `account_role_id`, ?, 0
            FROM `acl_account_roles`
            WHERE `role_id` = ?
            AND `is_automatic` = 1
        ", array(
            Acl::RESOURCE_ORCHESTRATION_ACCOUNT,
            Acl::ROLE_ID_FULL_ACCESS
        ));
    }

}