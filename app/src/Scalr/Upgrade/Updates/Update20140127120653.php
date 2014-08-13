<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20140127120653 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'ea229cbb-c9df-46cb-9f6f-5e7e83f0a603';

    protected $depends = array(
        '9c9f6b83-f8e9-4c15-8514-543c276bfea0'
    );

    protected $description = 'Append two new ACL Resources: AWS S3 and OPENSTACK LBaaS';

    protected $ignoreChanges = true;

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
        return defined('Scalr\\Acl\\Acl::RESOURCE_OPENSTACK_ELB') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_OPENSTACK_ELB,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_OPENSTACK_ELB') &&
               Definition::has(Acl::RESOURCE_OPENSTACK_ELB);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding OpenStack Load Balancing ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_OPENSTACK_ELB
        ));
    }

    protected function isApplied2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_AWS_S3') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_AWS_S3,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_AWS_S3') &&
               Definition::has(Acl::RESOURCE_AWS_S3);
    }

    protected function run2($stage)
    {
        $this->console->out("Adding AWS S3 ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_AWS_S3
        ));
    }
}