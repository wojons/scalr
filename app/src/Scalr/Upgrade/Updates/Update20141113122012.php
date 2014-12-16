<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141113122012 extends AbstractUpdate implements SequenceInterface
{

    const RESOURCE_FARMS_EVENTS_AND_NOTIFICATIONS = 0x102;
    
    protected $uuid = 'c80b4399-4661-4968-9c4b-d3f3dfe927e0';

    protected $depends = [];

    protected $description = 'Remove acl recource Events and Notifications(RESOURCE_FARMS_EVENTS_AND_NOTIFICATIONS)';

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
        $this->db->Execute("DELETE FROM `acl_role_resources` WHERE `resource_id` = ?", [self::RESOURCE_FARMS_EVENTS_AND_NOTIFICATIONS]);
        $this->console->out('%d record(s) have been removed from acl_role_resources', $this->db->Affected_Rows());
        
        $this->db->Execute("DELETE FROM `acl_account_role_resources` WHERE `resource_id` = ?", [self::RESOURCE_FARMS_EVENTS_AND_NOTIFICATIONS]);
        $this->console->out('%d record(s) have been removed from acl_account_role_resources', $this->db->Affected_Rows());

    }
}