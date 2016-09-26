<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150202122640 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '1d56e9b1-7767-4e58-866a-61f1b07862e9';

    protected $depends = [];

    protected $description = 'Update events table with new columns';

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
        return $this->hasTableColumn('events', 'is_suspend');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('events');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `events` ADD `is_suspend` TINYINT(1) NULL DEFAULT '0' ;");
    }
}