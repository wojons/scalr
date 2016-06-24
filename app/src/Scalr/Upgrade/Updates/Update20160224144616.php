<?php
namespace Scalr\Upgrade\Updates;

use Exception;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160224144616 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'c1653f26-bf96-4c75-834a-3c36459e192c';

    protected $depends = [];

    protected $description = 'Add account scope to analytics notifications and reports';

    protected $dbservice = 'cadb';

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
        return $this->hasTableColumn('notifications', 'accountId') && $this->hasTableColumn('reports', 'accountId');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('notifications') && $this->hasTable('reports');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding account_id column to reports and notifications tables...");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("
                ALTER TABLE `notifications`
                ADD COLUMN `account_id` int(11) DEFAULT NULL COMMENT 'ID of Account',
                ADD INDEX `idx_account_id` (`account_id`)
            ");

            $this->db->Execute("
                ALTER TABLE `reports`
                ADD COLUMN `account_id` int(11) DEFAULT NULL COMMENT 'ID of Account',
                ADD INDEX `idx_account_id` (`account_id`)
            ");
            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }
}
