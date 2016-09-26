<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150420140529 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2200f362-e149-47bd-9b59-2fd7ae827b0f';

    protected $depends = [];

    protected $description = "Add 'created' and 'last_used' columns to scalr.account_user_apikeys table.";

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
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        if (!$this->hasTableColumn('account_user_apikeys', 'created')) {
            $this->console->out("Adding scalr.account_user_apikeys.created column...");
            $this->db->Execute("ALTER TABLE account_user_apikeys ADD `created` DATETIME NOT NULL COMMENT 'Created at timestamp'");
        }

        if (!$this->hasTableColumn('account_user_apikeys', 'last_used')) {
            $this->console->out("Adding scalr.account_user_apikeys.last_used column...");
            $this->db->Execute("ALTER TABLE account_user_apikeys ADD `last_used` DATETIME DEFAULT NULL COMMENT 'Created at timestamp'");
        }

    }
}