<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150709044453 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '91168df7-a65a-41a4-9c29-ca8e6d65a078';

    protected $depends = [];

    protected $description = 'Fix openstack.ip-pool in farm_role_settings table.';

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
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            DELETE FROM `farm_role_settings` WHERE `name` = 'openstack.ip-pool' AND `value` LIKE 'extmodel%'
        ");
        $affected = $this->db->Affected_Rows();
        $this->console->out("{$affected} openstack.ip-pool records fixed.");
    }
}