<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20141014063003 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'd602e069-1240-47dd-b43b-a94ee4345f86';

    protected $depends = [];

    protected $description = 'Creating Webhooks (account level) ACL resource';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_WEBHOOKS_ACCOUNT') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_WEBHOOKS_ACCOUNT,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_WEBHOOKS_ACCOUNT') &&
               Definition::has(Acl::RESOURCE_WEBHOOKS_ACCOUNT);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding Webhooks (account level) ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_WEBHOOKS_ACCOUNT
        ));
    }
}