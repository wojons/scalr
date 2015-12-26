<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150904143320 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '98198ed0-3d38-406b-afdf-02437b481416';

    protected $depends = [];

    protected $description = 'Separate RDS/EC2 security group governance';

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
            INSERT IGNORE INTO `governance` (`env_id`, `category`, `name`, `enabled`, `value`)
            SELECT `env_id`, `category`, 'aws.rds_additional_security_groups', `enabled`, `value`
            FROM `governance`
            WHERE name = 'aws.additional_security_groups'
            AND enabled = 1;
        ");
        $this->console->out('%d RDS SG governance policies have been created.', $this->db->Affected_Rows());

    }
}