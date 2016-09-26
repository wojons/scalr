<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150223105839 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '497eef5f-d3d4-4c92-ae4e-db6982630339';

    protected $depends = [];

    protected $description = 'Convert ui_errors and comments tables to InnoDB';

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
        $tableInfo = $this->getTableDefinition('ui_errors');

        return !empty($tableInfo) && ($tableInfo->engine == 'InnoDB') ? true : false;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('ui_errors');
    }

    protected function run1($stage)
    {
        $this->console->out("Altering ui_errors table");
        $this->db->Execute("ALTER TABLE `ui_errors` ENGINE=INNODB");
    }

    protected function isApplied2($stage)
    {
        $tableInfo = $this->getTableDefinition('comments');

        return !empty($tableInfo) && ($tableInfo->engine == 'InnoDB') ? true : false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('comments');
    }

    protected function run2($stage)
    {
        $this->console->out("Altering comments table");
        $this->db->Execute("ALTER TABLE `comments` ENGINE=INNODB");
    }

}