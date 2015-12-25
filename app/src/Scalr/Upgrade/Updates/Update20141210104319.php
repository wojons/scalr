<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Resource\Definition;
use Scalr\Acl\Acl;

class Update20141210104319 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '0354611e-b216-451f-a697-baba7e33bd4c';

    protected $depends = [];

    protected $description = 'Append two new ACL Resources: Analytics Administration and Analytics Envadministration';

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
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ACCOUNT') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_ANALYTICS_ACCOUNT,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_ANALYTICS_ACCOUNT);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding Analytics account level ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_ACCOUNT
        ));
    }

    protected function isApplied2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ENVIRONMENT') && $this->db->GetOne("
            SELECT `granted` FROM `acl_role_resources`
            WHERE `resource_id` = ? AND `role_id` = ?
            LIMIT 1
        ", array(
            Acl::RESOURCE_ANALYTICS_ENVIRONMENT,
            Acl::ROLE_ID_FULL_ACCESS,
        )) == 1;
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_ANALYTICS_ENVIRONMENT') &&
        Definition::has(Acl::RESOURCE_ANALYTICS_ENVIRONMENT);
    }

    protected function run2($stage)
    {
        $this->console->out("Adding Analytics environment level ACL resource");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_ANALYTICS_ENVIRONMENT
        ));
    }

}