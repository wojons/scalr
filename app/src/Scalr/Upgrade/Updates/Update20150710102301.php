<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150710102301 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'cf7585a0-506f-45d5-bf66-b3d8cca52796';

    protected $depends = [];

    protected $description = "Add compound index for 'autosnap_settings' to fetch data using both 'objectid' and 'object_type'";

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
        return $this->hasTableIndex("autosnap_settings", "idx_object");
    }

    protected function validateBefore1($stage)
    {
        return
            $this->hasTable("autosnap_settings") &&
            $this->hasTableColumn("autosnap_settings", "objectid") &&
            $this->hasTableColumn("autosnap_settings", "object_type");
    }

    protected function run1($stage)
    {
        $this->console->out('Adding compound index for objectid and object_type.');
        $this->db->Execute("ALTER TABLE `autosnap_settings` ADD INDEX `idx_object` (`objectid`, `object_type`)");
    }
}
