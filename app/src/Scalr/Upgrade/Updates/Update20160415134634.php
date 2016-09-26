<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160415134634 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '90532676-d5ae-413f-9b72-15a8e4362ae5';

    protected $depends = [];

    protected $description = "Add index on dtadded field in servers table.";

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
        return $this->hasTableIndex('servers', 'idx_dtadded');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('servers', 'dtadded');
    }

    protected function run1($stage)
    {
        $this->console->out('Adding index on dtadded field...');

        $this->db->BeginTrans();

        $this->db->Execute("ALTER TABLE `servers` ADD INDEX `idx_dtadded` (`dtadded`)");
    }

}