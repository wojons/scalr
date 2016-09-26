<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160224140055 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '33ccd8ca-9add-4887-8a43-8e8c6ce5a4ab';

    protected $depends = [];

    protected $description = 'Add mount_options to farm_role_storage_config';

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
        return $this->hasTableColumn('farm_role_storage_config', 'mount_options');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farm_role_storage_config');
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            ALTER TABLE `farm_role_storage_config`
            ADD COLUMN `mount_options` text NULL DEFAULT NULL COMMENT 'Mount options (linux only)' AFTER `label`
        ");
    }
}