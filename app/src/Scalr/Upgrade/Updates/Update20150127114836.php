<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150127114836 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '6fc46a48-0c96-4d5a-9ccf-f1813f7f5da8';

    protected $depends = [];

    protected $description = 'Move api.ip.whitelist from settings to vars';

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
        $ids = $this->db->GetCol('SELECT DISTINCT(us.user_id) FROM account_user_settings us LEFT JOIN account_users u ON u.id = us.user_id WHERE u.id IS NULL');
        if (count($ids))
            $this->db->Execute('DELETE FROM account_user_settings WHERE user_id IN(' . join($ids, ',') . ')');

        $this->db->Execute('INSERT INTO account_user_vars (SELECT * FROM account_user_settings WHERE name = ?)', [\Scalr_Account_User::VAR_API_IP_WHITELIST]);
        $this->db->Execute('DELETE FROM account_user_settings WHERE name = ?', [\Scalr_Account_User::VAR_API_IP_WHITELIST]);
    }
}