<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151023204201 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '3d218b3f-e24f-4618-bc7b-81aace2f5bd6';

    protected $depends = [];

    protected $description = 'Fix Security groups policies with empty SG list';

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
            AND name IN (
                'openstack.additional_security_groups',
                'cloudstack.additional_security_groups',
                'aws.additional_security_groups',
                'aws.rds_additional_security_groups'
            )
        ";
        foreach($this->db->GetAll($query) as $row) {
            $val = json_decode($row['value'], true);
            if (!isset($val['value']) || empty($val['value'])) {
                $this->db->Execute("
                    UPDATE `governance` SET enabled = 0
                    WHERE env_id = ? AND category = ? AND name = ?
                ", [$row['env_id'], $row['category'], $row['name']]);
                $affectedRows += $this->db->Affected_Rows();
            }
        }
        $this->console->out('%d record(s) have been fixed', $affectedRows);
    }
}