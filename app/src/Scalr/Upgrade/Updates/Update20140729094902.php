<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20140729094902 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ba1b82ac-040c-4a61-95c5-1cde1fd74b3f';

    protected $depends = [];

    protected $description = 'Creating SSH console permission';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        //provide the number of consecutive stages
        return 1;
    }

    protected function isApplied1($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_SERVERS') &&
                defined('Scalr\\Acl\\Acl::PERM_FARMS_SERVERS_SSH_CONSOLE') &&
                $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resource_permissions`
                    WHERE `resource_id` = ?
                    AND `role_id` = ?
                    AND `perm_id` = ?
                    LIMIT 1
                ", array(
                    Acl::RESOURCE_FARMS_SERVERS,
                    Acl::ROLE_ID_FULL_ACCESS,
                    Acl::PERM_FARMS_SERVERS_SSH_CONSOLE,
                )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_FARMS_SERVERS') &&
                defined('Scalr\\Acl\\Acl::PERM_FARMS_SERVERS_SSH_CONSOLE') &&
                Definition::has(Acl::RESOURCE_FARMS_SERVERS) &&
                $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resources`
                    WHERE `resource_id` = ? AND `role_id` = ?
                    LIMIT 1
                ", array(
                    Acl::RESOURCE_FARMS_SERVERS,
                    Acl::ROLE_ID_FULL_ACCESS,
                )) == 1;
    }

    protected function run1($stage)
    {
        $this->console->out('Creating SSH console permission');
        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_FARMS_SERVERS,
            Acl::PERM_FARMS_SERVERS_SSH_CONSOLE
        ));
    }
}