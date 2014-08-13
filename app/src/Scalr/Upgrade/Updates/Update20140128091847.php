<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20140128091847 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '8f464a28-e725-491b-bfa8-61cf68f21087';

    protected $depends = array();

    protected $description = 'Create ACL Resources: GCE static IPs, GCE persistent disks, GCE snapshots';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GCE_STATIC_IPS') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_GCE_STATIC_IPS,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GCE_STATIC_IPS') &&
               Definition::has(Acl::RESOURCE_GCE_STATIC_IPS);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding GCE static IPs ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_GCE_STATIC_IPS
        ));
    }

    protected function isApplied2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GCE_PERSISTENT_DISKS') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_GCE_PERSISTENT_DISKS,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GCE_PERSISTENT_DISKS') &&
               Definition::has(Acl::RESOURCE_GCE_PERSISTENT_DISKS);
    }

    protected function run2($stage)
    {
        $this->console->out("Adding GCE pesristent disks ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_GCE_PERSISTENT_DISKS
        ));
    }

    protected function isApplied3($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GCE_SNAPSHOTS') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_GCE_SNAPSHOTS,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore3($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_GCE_SNAPSHOTS') &&
               Definition::has(Acl::RESOURCE_GCE_SNAPSHOTS);
    }

    protected function run3($stage)
    {
        $this->console->out("Adding GCE snapshots ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_GCE_SNAPSHOTS
        ));
    }

}