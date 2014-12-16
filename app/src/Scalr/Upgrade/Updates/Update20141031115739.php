<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141031115739 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'caac9390-382c-4182-ab09-a8758c48b944';

    protected $depends = [];

    protected $description = 'Fix farm role with Chef Bootstrap attributes';

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
        $res = $this->db->Execute("
            SELECT fr.id
            FROM farm_roles fr
            INNER JOIN farm_role_settings frs ON fr.id = frs.farm_roleid AND frs.name = 'chef.attributes'
            INNER JOIN role_properties rp ON fr.role_id = rp.role_id AND rp.name = frs.name
            INNER JOIN role_properties rp1 ON fr.role_id = rp1.role_id AND rp1.name = 'chef.bootstrap' AND rp1.value = 1
            WHERE frs.value = rp.value
        ");

        $affectedRows = 0;

        while ($rec = $res->FetchRow()) {
            $this->db->Execute("
                UPDATE `farm_role_settings`
                SET value = ''
                WHERE farm_roleid = ?
                AND name = 'chef.attributes'
            ", array($rec['id']));

            $affectedRows += $this->db->Affected_Rows();
        }

        if ($affectedRows)
            $this->console->out('%d record(s) have been fixed', $affectedRows);
    }
}