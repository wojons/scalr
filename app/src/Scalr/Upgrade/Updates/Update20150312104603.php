<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150312104603 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'a9ee0239-3c51-45b8-998e-dfe2d1fcc585';

    protected $depends = [];

    protected $description =  'Increase farm_role_scripting_targets.target size to match farm_roles.alias size';

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
        return $this->hasTableColumn('farm_role_scripting_targets', 'target');
    }

    protected function run1($stage)
    {
        $this->console->out('Increase farm_role_scripting_targets.target size');
        $this->db->Execute('ALTER TABLE `farm_role_scripting_targets` CHANGE COLUMN `target` `target` VARCHAR(50) DEFAULT NULL');
    }
}