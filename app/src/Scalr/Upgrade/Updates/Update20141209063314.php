<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141209063314 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '3425caba-7729-43c5-a484-cc4d9876fc5a';

    protected $depends = [];

    protected $description = 'Adds server_id column to webhook_history table';

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
        return $this->hasTableColumn('webhook_history', 'server_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('webhook_history');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding server_id column to webhook_history table");
        $this->db->Execute("
            ALTER TABLE `webhook_history` ADD `server_id` VARCHAR(36) AFTER `farm_id`
        ");
    }
}