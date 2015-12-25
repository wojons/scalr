<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150911071024 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '50891a6c-bae9-438d-8c72-8ef02d4a813a';

    protected $depends = [];

    protected $description = 'Add label to farm_role_storage_config';

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
        return $this->hasTableColumn('farm_role_storage_config', 'label');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farm_role_storage_config');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `farm_role_storage_config` ADD COLUMN `label` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Disk label (windows only)'  AFTER `mountpoint`");
    }
}