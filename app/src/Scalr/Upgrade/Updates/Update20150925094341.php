<?php
namespace Scalr\Upgrade\Updates;

use Exception;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150925094341 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '46638b91-e3ee-4346-81d3-48499428930d';

    protected $depends = [];

    protected $description = "Restore missing `accountId` for environment-scoped scripts";

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
        $this->db->BeginTrans();

        try {
            $this->db->Execute("
            UPDATE `scripts` AS `s`
                JOIN `client_environments` AS `ce` ON `s`.`env_id` = `ce`.`id`
                SET `s`.`account_id` = `ce`.`client_id`
                WHERE `s`.`env_id` IS NOT NULL;
            ");
            $affectedRows = $this->db->Affected_Rows();
            $this->db->CommitTrans();
            $this->console->out("Updated records: {$affectedRows}");
        } catch (Exception $e) {
            $this->db->RollbackTrans();

            throw $e;
        }
    }
}