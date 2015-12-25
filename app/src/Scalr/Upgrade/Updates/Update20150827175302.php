<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150827175302 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ec750d1c-61df-4119-8fa6-885844b8967c';

    protected $depends = [];

    protected $description = 'Fix farm_role_storage_config for windows Farm Roles';

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
        $this->db->Execute("
            UPDATE farm_role_storage_config
            SET mount = NULL, mountpoint = NULL
            WHERE farm_role_id IN
                (SELECT fr.id
                FROM farm_roles fr
                INNER JOIN roles r ON fr.role_id = r.id
                INNER JOIN os o ON r.os_id = o.id
                WHERE o.family = 'windows')
            AND mount = 1
            AND mountpoint = '/mnt'
        ");
        $this->console->out('%d windows Farm Role(s) have been fixed', $this->db->Affected_Rows());
    }
}