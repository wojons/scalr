<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140530114325 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '79b541c9-ce01-4015-a4b0-f20f61405a5f';

    protected $depends = array();

    protected $description = 'Add settings table to Scalr database.';

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
        return $this->hasTable('settings');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'settings' table to scalr database...");
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `settings` (
              `id` VARCHAR(64) NOT NULL COMMENT 'setting ID',
              `value` TEXT NULL COMMENT 'The value',
              PRIMARY KEY (`id`))
            ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'settings'
        ");
    }
}