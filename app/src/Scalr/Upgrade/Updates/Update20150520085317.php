<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150520085317 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '1f4e73ca-a311-47ab-876d-e69f8d73836f';

    protected $depends = [];

    protected $description = "Increases size of the script_name column of scripting_log table";

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
        return $this->hasTableColumnType('scripting_log', 'script_name', 'varchar(255)');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('scripting_log', 'script_name');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `scripting_log` MODIFY `script_name` VARCHAR(255) DEFAULT NULL");
    }
}