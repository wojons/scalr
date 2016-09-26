<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150130064536 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '3a8901d8-5b47-4126-9ed9-c44616f89a51';

    protected $depends = [];

    protected $description = 'Ad last used date column to images and roles tables';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('images', 'dt_last_used');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('images');
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('roles', 'dt_last_used');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('roles');
    }

    protected function run2($stage)
    {
        $this->db->Execute("ALTER TABLE `roles` ADD `dt_last_used` DATETIME NULL AFTER `dtadded`");
    }
    
    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `images` ADD `dt_last_used` DATETIME NULL AFTER `dt_added`");
    }
}