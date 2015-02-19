<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20150113130008 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '9a48606d-dab3-4c82-be2c-ad0bdf6bba42';

    protected $depends = [];

    protected $description = 'Add Event Logs ACL resource';

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
        return defined('Scalr\\Acl\\Acl::RESOURCE_LOGS_EVENT_LOGS') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_LOGS_EVENT_LOGS,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_LOGS_EVENT_LOGS') &&
               Definition::has(Acl::RESOURCE_LOGS_EVENT_LOGS);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding Event Logs ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_LOGS_EVENT_LOGS
        ));
    }
}