<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150120201443 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'f0090f42-ace6-4b91-a319-1a05c6cc67ea';

    protected $depends = [];

    protected $description = 'Create cc_id index instead of account_id';

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
        return $this->hasTable('account_ccs') && $this->hasTableIndex('account_ccs', 'idx_cc_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('account_ccs');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding idx_cc_id index to account_ccs.cc_id");
        $drop = '';

        if ($this->hasTableIndex('account_ccs', 'idx_account_id')) {
            $drop .= "DROP INDEX `idx_account_id` , ";
        }

        $this->db->Execute("
            ALTER TABLE `account_ccs` "
            . $drop . "
            ADD INDEX `idx_cc_id` (`cc_id`)
        ");
    }
}