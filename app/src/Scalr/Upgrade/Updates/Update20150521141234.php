<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150521141234 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'c4754289-02d9-4d09-8327-738891c6af9d';

    protected $depends = [];

    protected $description = 'Optimize scheduler table';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private $sql = [];
    private $origin = 'scheduler';

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
        return !$this->hasTableColumn('scheduler', 'order_index');
    }

    protected function run1($stage)
    {
        $this->console->out('Removing order_index');
        $this->sql[] = 'DROP COLUMN `order_index`';
    }

    protected function isApplied2()
    {
        return $this->hasTableColumnType('scheduler', 'comments', 'TEXT');
    }

    protected function run2()
    {
        $this->console->out('Converting comments to TEXT');
        $this->sql[] = 'MODIFY `comments` TEXT NOT NULL';
    }

    protected function isApplied3()
    {
        return empty($this->sql);
    }

    protected function validateBefore3()
    {
        return $this->hasTable($this->origin);
    }

    protected function run3()
    {
        $this->console->out("Apply changes on `{$this->origin}`");
        $this->applyChanges($this->origin, $this->sql);
    }
}
