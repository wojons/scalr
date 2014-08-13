<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140128130324 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '13d25487-f467-471b-adbb-e2b0a6c966b8';

    protected $depends = array();

    protected $description = 'Adding index on os_family column to roles table';

    protected $ignoreChanges = true;

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
        return $this->hasTable('roles') && $this->hasTableIndex('roles', 'idx_os_family');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('roles') && $this->hasTableColumn('roles', 'os_family');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `roles` ADD INDEX `idx_os_family` (`os_family`)");
    }
}