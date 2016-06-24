<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160304130735 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '6917cef6-4e26-450a-84f1-f04e531bda82';

    protected $depends = [];

    protected $description = 'Update cloud credentials property ssl_verifypeer';

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
        return $this->hasTable('cloud_credentials_properties');
    }

    protected function run1($stage)
    {
        $result = $this->db->Execute("
            UPDATE `cloud_credentials_properties`
            SET value = '1'
            WHERE name ='ssl_verifypeer'
            AND value IN (?, ?)
        ", [\Scalr::getContainer()->crypto->encrypt('0'), \Scalr::getContainer()->crypto->encrypt('1')]);

        $affected = $this->db->Affected_Rows();

        $result = $this->db->Execute("
            UPDATE `cloud_credentials_properties`
            SET value = '0'
            WHERE name ='ssl_verifypeer'
            AND value <> '1'
        ");

        $affected += $this->db->Affected_Rows();

        $this->console->out("Updated {$affected} ssl_verifypeer property records.");
    }
}
