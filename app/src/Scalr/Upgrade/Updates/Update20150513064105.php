<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150513064105 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '08f2a861-18ff-4ed4-ba2d-bf4beb05bf1c';

    protected $depends = [];

    protected $description = 'Add is_scalarized field to servers table';

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
        return $this->hasTableColumn('servers', 'is_scalarized');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'is_scalarized' field to 'servers'");
        $this->db->Execute("
            ALTER TABLE `servers`
                ADD `is_scalarized` tinyint(1) NOT NULL DEFAULT '1',
                ADD INDEX `idx_is_scalarized` (`is_scalarized`)
        ");
    }
}