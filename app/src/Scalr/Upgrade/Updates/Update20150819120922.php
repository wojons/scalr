<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150819120922 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '0327040d-0519-4fb9-8f32-5a2b723049f4';

    protected $depends = [];

    protected $description = 'Modify loginattempts column from account_users table';

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
        return $this->hasTableColumnDefault('account_users', 'loginattempts', 0);
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE account_users MODIFY loginattempts INT(4) NOT NULL DEFAULT 0");
    }
}
