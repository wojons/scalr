<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20141021103250 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '6a704473-acb2-4be9-b765-da5f99197d06';

    protected $depends = [];

    protected $description = 'Creating Fire custom events permission';

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
        return  defined('Scalr\\Acl\\Acl::RESOURCE_GENERAL_CUSTOM_EVENTS') &&
                defined('Scalr\\Acl\\Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE') &&
                $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resource_permissions`
                    WHERE `resource_id` = ?
                    AND `role_id` = ?
                    AND `perm_id` = ?
                    LIMIT 1
                ", array(
                    Acl::RESOURCE_GENERAL_CUSTOM_EVENTS,
                    Acl::ROLE_ID_FULL_ACCESS,
                    Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE,
                )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return  defined('Scalr\\Acl\\Acl::RESOURCE_GENERAL_CUSTOM_EVENTS') &&
                defined('Scalr\\Acl\\Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE') &&
                Definition::has(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS) &&
                $this->db->GetOne("
                    SELECT `granted` FROM `acl_role_resources`
                    WHERE `resource_id` = ? AND `role_id` = ?
                    LIMIT 1
                ", array(
                    Acl::RESOURCE_GENERAL_CUSTOM_EVENTS,
                    Acl::ROLE_ID_FULL_ACCESS,
                )) == 1;
    }

    protected function run1($stage)
    {
        $this->console->out('Creating Fire custom events permission');
        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_GENERAL_CUSTOM_EVENTS,
            Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE
        ));
    }
}