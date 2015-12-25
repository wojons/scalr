<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150908094546 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '98d1d44a-b99d-4e12-8a40-d73e00e8b5de';

    protected $depends = [];

    protected $description = "Makes logentries.server_id nullable.";

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
        return $this->hasTableColumn('logentries', 'serverid') &&
               $this->getTableColumnDefinition('logentries', 'serverid')->isNullable();
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `logentries` MODIFY `serverid` VARCHAR(36) DEFAULT NULL");
    }
}