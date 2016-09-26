<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141218153354 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '7d722d61-529c-4181-ae26-83ef671cc9d4';

    protected $depends = [];

    protected $description = "Adds key to status column of clients table";

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
        return $this->hasTableIndex('clients', 'idx_status');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('clients') && $this->hasTableColumn('clients', 'status');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding idx_status index to clients.status");
        $this->db->Execute("ALTER TABLE `clients` ADD INDEX `idx_status` (`status`)");
    }
}