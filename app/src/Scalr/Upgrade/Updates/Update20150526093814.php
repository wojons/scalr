<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150526093814 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '5a1d76fc-b1ef-4dac-9181-f91ede5ed8e0';

    protected $depends = [];

    protected $description = 'Add is_scalarized, has_cloud_init fields to images table; add is_scalarized to roles table';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('images', 'is_scalarized');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'is_scalarized' field to 'images'");
        $this->db->Execute("ALTER TABLE `images` ADD `is_scalarized` tinyint(1) NOT NULL DEFAULT '1'");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('images', 'has_cloud_init');
    }

    protected function validateBefore2($stage)
    {
        return true;
    }

    protected function run2($stage)
    {
        $this->console->out("Adding 'has_cloud_init' field to 'roles'");
        $this->db->Execute("ALTER TABLE `images` ADD `has_cloud_init` tinyint(1) NOT NULL DEFAULT '0'");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableColumn('roles', 'is_scalarized');
    }

    protected function validateBefore3($stage)
    {
        return true;
    }

    protected function run3($stage)
    {
        $this->console->out("Adding 'is_scalarized' field to 'roles'");
        $this->db->Execute("ALTER TABLE `roles` ADD `is_scalarized` tinyint(1) NOT NULL DEFAULT '1'");
    }

}