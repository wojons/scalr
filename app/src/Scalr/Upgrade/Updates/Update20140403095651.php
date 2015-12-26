<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20140403095651 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '1b5f5a56-44d9-4aa7-9d1e-eadd6015ed6f';

    protected $depends = array('cc7f0f71-f771-4840-96ec-7d6c68da9e8a');

    protected $description = 'Creating Global variables ACL resource';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding Global variables ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_GLOBAL_VARIABLES_ACCOUNT
        ));
    }
}
