<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151106121103 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '91437f5c-e3c8-44f8-8cb7-706e93d30045';

    protected $depends = [];

    protected $description = 'Separate ELB/EC2 security group governance';

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
        $affectedRows = 0;
        $query = "
            SELECT * FROM `governance`
            WHERE enabled = 1
            AND name = 'aws.additional_security_groups'
        ";
        foreach($this->db->GetAll($query) as $row) {
            $val = json_decode($row['value'], true);
            if (!empty($val['value'])) {
                if (isset($val['windows'])) {
                    unset($val['windows']);
                }
                $this->db->Execute("
                    INSERT IGNORE INTO `governance` (`env_id`, `category`, `name`, `enabled`, `value`)
                    VALUES(?, ?, 'aws.elb_additional_security_groups', 1, ?)
                ", [$row['env_id'], $row['category'], json_encode($val)]);
                $affectedRows += $this->db->Affected_Rows();
            }
        }
        $this->console->out('%d ELB SG governance policies have been created.', $affectedRows);
    }
}