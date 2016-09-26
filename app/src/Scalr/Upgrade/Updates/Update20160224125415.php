<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Exception;

class Update20160224125415 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'b1c38e37-a6c5-4a11-9248-df4b07e8cf36';

    protected $depends = [];

    protected $description = 'Deleting deployment application, sources and tasks ACL resources';

    protected $dbservice = 'adodb';

    const RESOURCE_DEPLOYMENTS_APPLICATIONS = 0x180;
    const RESOURCE_DEPLOYMENTS_SOURCES = 0x181;
    const RESOURCE_DEPLOYMENTS_TASKS = 0x182;


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
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->BeginTrans();

        try {
            $in = implode("','", [self::RESOURCE_DEPLOYMENTS_APPLICATIONS, self::RESOURCE_DEPLOYMENTS_SOURCES, self::RESOURCE_DEPLOYMENTS_TASKS]);

            $this->db->Execute("DELETE FROM `acl_role_resources` WHERE `resource_id` IN ('{$in}')");

            $this->db->Execute("DELETE FROM `acl_account_role_resources` WHERE `resource_id` IN ('{$in}')");

            $this->db->Execute("DELETE FROM `farm_role_settings` WHERE `name` LIKE 'dm.%'");

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }
}