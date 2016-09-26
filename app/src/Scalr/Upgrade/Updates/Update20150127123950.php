<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150127123950 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '04e1cea5-1384-436f-81d0-cdd56ad29476';

    protected $depends = [];

    protected $description = 'Add foreign key to account_user_settings';

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
        return $this->hasTableForeignKey('fk_account_users_id_user_settings', 'account_user_settings');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('account_user_settings');
    }

    protected function run1($stage)
    {
        $ids = $this->db->GetCol('SELECT DISTINCT(us.user_id) FROM account_user_settings us LEFT JOIN account_users u ON u.id = us.user_id WHERE u.id IS NULL');
        if (count($ids))
            $this->db->Execute('DELETE FROM account_user_settings WHERE user_id IN(' . join($ids, ',') . ')');

        $this->db->Execute('ALTER TABLE account_user_settings ADD CONSTRAINT `fk_account_users_id_user_settings` FOREIGN KEY (`user_id`) REFERENCES `account_users` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
    }
}