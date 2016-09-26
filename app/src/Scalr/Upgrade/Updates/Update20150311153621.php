<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150311153621 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2d170fc8-42c3-4510-aa1a-d22f27498776';

    protected $depends = [];

    protected $description = 'Add field `name` to account_user_apikeys table';

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
        return $this->hasTable('account_user_apikeys') && $this->hasTableColumn('account_user_apikeys', 'name');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('account_user_apikeys');
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            ALTER TABLE `account_user_apikeys`
                ADD `name` VARCHAR(255) NOT NULL  DEFAULT ''  AFTER `key_id`
        ");
    }

}