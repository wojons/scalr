<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140516070312 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '3c69a101-abe6-4212-9290-976818aaa49a';

    protected $depends = array('b8667b1c-8310-4a7e-b98c-47de9703a064');

    protected $description = 'Add missing fields to role_scripts (script_path, run_as)';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('role_scripts') && $this->hasTableColumn('role_scripts', 'script_path');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('role_scripts');
    }

    protected function run1($stage)
    {
        // remove with scriptId = NULL, it is invalid records
        $this->db->Execute('DELETE FROM `role_scripts` WHERE ISNULL(script_id)');
        $this->db->Execute('ALTER TABLE `role_scripts` ADD `script_path` varchar(255) DEFAULT NULL, ADD `run_as` varchar(15) DEFAULT NULL');
    }
}
