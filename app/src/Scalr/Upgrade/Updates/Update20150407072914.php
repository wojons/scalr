<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150407072914 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '89d8a0b0-162c-4d74-b113-d7c2b4155110';

    protected $depends = [];

    protected $description = 'Reset account dashboard settings';

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
        $this->db->Execute('DELETE FROM `account_user_dashboard` WHERE env_id IS NULL');
    }
}