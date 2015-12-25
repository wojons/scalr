<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;

class Update20150911095701 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '1597d458-aa1e-4258-a416-a1cb9f13e8ef';

    protected $depends = [];

    protected $description = 'Adds a new Scripts (environment scope) ACL Resource';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function run1($stage)
    {
        $this->console->out('Initializing a new Scripts (environment scope) ACL Resource');

        $this->db->Execute("INSERT IGNORE acl_role_resources (`role_id`, `resource_id`, `granted`) VALUES(?, ?, 1)", [
            Acl::ROLE_ID_FULL_ACCESS, Acl::RESOURCE_SCRIPTS_ENVIRONMENT
        ]);

        foreach ([Acl::PERM_SCRIPTS_ENVIRONMENT_EXECUTE, Acl::PERM_SCRIPTS_ENVIRONMENT_FORK, Acl::PERM_SCRIPTS_ENVIRONMENT_MANAGE] as $permission) {
            $this->db->Execute("
                INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
                VALUES (?, ?, ?, 1)
            ", array(
                Acl::ROLE_ID_FULL_ACCESS,
                Acl::RESOURCE_SCRIPTS_ENVIRONMENT,
                $permission
            ));
        }
    }

    protected function run2()
    {
        $this->console->out('Copying existing access permissions from Account scope to Environment scope');

        $this->db->Execute("INSERT `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`) " .
            "SELECT `account_role_id`, ? AS `resource_id`, `granted` FROM `acl_account_role_resources` WHERE resource_id = ?", [
            Acl::RESOURCE_SCRIPTS_ENVIRONMENT,
            Acl::RESOURCE_SCRIPTS_ACCOUNT
        ]);

        $this->db->Execute("INSERT `acl_account_role_resource_permissions` (`account_role_id`, `resource_id`, `perm_id`, `granted`) " .
            "SELECT `account_role_id`, ? AS `resource_id`, `perm_id`, `granted` FROM `acl_account_role_resource_permissions` WHERE resource_id = ?", [
            Acl::RESOURCE_SCRIPTS_ENVIRONMENT,
            Acl::RESOURCE_SCRIPTS_ACCOUNT
        ]);
    }

    protected function run3()
    {
        $this->console->out('Removing old permission "execute" from acl roles');

        $this->db->Execute("DELETE FROM `acl_role_resource_permissions` WHERE `resource_id` = ? AND perm_id = ?",
            [Acl::RESOURCE_SCRIPTS_ACCOUNT, 'execute']);

        $this->db->Execute("DELETE FROM `acl_account_role_resource_permissions` WHERE `resource_id` = ? AND perm_id = ?",
            [Acl::RESOURCE_SCRIPTS_ACCOUNT, 'execute']);

    }
}