<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Definition;

/**
 * Adding new ACL Resources
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0.0 (25.04.2014)
 */
class Update20140425101959 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'b8667b1c-8310-4a7e-b98c-47de9703a064';

    protected $depends = array();

    protected $description = 'Adding AWS_ROUTE53 and ANALYTICS_PROJECTS ACL resources';

    protected $ignoreChanges = true;

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
        return defined('Scalr\\Acl\\Acl::RESOURCE_AWS_ROUTE53') &&
               defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS') &&
               $this->db->GetOne("
                   SELECT SUM(`granted`) FROM `acl_role_resources`
                   WHERE `resource_id` IN (?, ?)
                   AND `role_id` = ?
               ", array(
                   Acl::RESOURCE_AWS_ROUTE53,
                   Acl::RESOURCE_ANALYTICS_PROJECTS,
                   Acl::ROLE_ID_FULL_ACCESS,
               )) == 2;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_AWS_ROUTE53') &&
               defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_PROJECTS') &&
               Definition::has(Acl::RESOURCE_AWS_ROUTE53) &&
               Definition::has(Acl::RESOURCE_ANALYTICS_PROJECTS);
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1), (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_AWS_ROUTE53,

            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_PROJECTS,
        ));
    }
}