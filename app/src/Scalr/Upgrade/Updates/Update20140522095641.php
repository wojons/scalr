<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140522095641 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'd96f839f-6f51-4b78-a3df-56fdb56b1241';

    protected $depends = array('4c1e857b-4559-4215-bedb-f663ad9c87b1');

    protected $description = 'Fix some invalid values in database (script_versions, farm_roles)';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return !$this->db->GetOne('SELECT COUNT(*) FROM script_versions WHERE `variables` = ?', array(serialize(NULL)));
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('script_versions');
    }

    protected function run1($stage)
    {
        // NULL - not valid value, should be empty array
        $this->db->Execute('UPDATE script_versions SET `variables` = ? WHERE `variables` = ?', array(serialize([]), serialize(NULL)));
        $this->console->out('Fixed %d records in script_versions', $this->db->Affected_Rows());
    }

    protected function isApplied2()
    {
        return !$this->db->GetOne('SELECT COUNT(*) FROM `farm_roles` WHERE alias IS NULL OR alias = ""');
    }

    protected function validateBefore2()
    {
        return $this->hasTable('farm_roles');
    }

    protected function run2()
    {
        // alias should be always non-empty, copy from roles
        $this->db->Execute('UPDATE `farm_roles` fr, `roles` r SET fr.alias = r.name WHERE fr.role_id = r.id AND (fr.alias IS NULL OR fr.alias = "")');
        $this->console->out('Fixed %d records in farm_roles', $this->db->Affected_Rows());
    }
}
