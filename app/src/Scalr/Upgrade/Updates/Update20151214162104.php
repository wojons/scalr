<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151214162104 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '7a45ecfb-c50b-4e84-b8af-de34819198f2';

    protected $depends = [];

    protected $description = "Add type field to primary key in farm_role_cloud_services table";

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
        return $this->hasTableColumn('farm_role_cloud_services', 'type');
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            ALTER TABLE `farm_role_cloud_services`
            DROP PRIMARY KEY,
            ADD PRIMARY KEY (`id`, `type`, `env_id`, `platform`, `cloud_location`)
        ");
    }

}