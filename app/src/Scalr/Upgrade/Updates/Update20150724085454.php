<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150724085454 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '0a586af2-f43e-4ffa-b181-5f204f038645';

    protected $depends = [];

    protected $description = "Drop tables that left after previous upgrades";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private   $tables = [];

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
        $this->tables = $this->showTables("^messages_backup_[0-9]{10}$");
        return empty($this->tables);
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->dropTables($this->tables);
    }
}
