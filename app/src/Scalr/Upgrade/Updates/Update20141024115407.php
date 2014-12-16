<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141024115407 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '83b7615c-5d85-4416-9e89-1f4b97f424b7';

    protected $depends = [];

    protected $description = 'Fixes aws farm roles additional tags';

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
        $res = $this->db->Execute("SELECT * FROM `farm_role_settings` WHERE `name` = 'aws.additional_tags' AND (value like '%\\%7b%' OR  value like '%\\%20%')");

        while ($rec = $res->FetchRow()) {
            $value = urldecode($rec['value']);
            $this->db->Execute("UPDATE `farm_role_settings` SET value = ? WHERE farm_roleid = ? AND name = 'aws.additional_tags'", array($value, $rec['farm_roleid']));
        }
    }
}