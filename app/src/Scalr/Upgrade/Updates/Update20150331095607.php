<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150331095607 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '835ecc3c-3114-4502-9791-8e8c1bb01001';

    protected $depends = [];

    protected $description = 'Add support for account dashboard';

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
        return $this->hasTableColumn('account_user_dashboard', 'env_id');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE `account_user_dashboard` CHANGE `env_id` `env_id` INT(11) NULL');
    }
}