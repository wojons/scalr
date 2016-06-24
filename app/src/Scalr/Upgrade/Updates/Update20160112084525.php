<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160112084525 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'd7909df2-2f1b-4fc4-89b9-63c8a210e285';

    protected $depends = [];

    protected $description = 'Rename instance type names for all clouds in farm_role_settings table.';

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farm_role_settings');
    }

    protected function run1($stage)
    {
        $this->console->out('Renaming instance type property names...');

        $this->db->Execute("
            UPDATE farm_role_settings fs
            SET fs.name = ?
            WHERE fs.name IN (?, ?, ?, ?, ?, ?)", [
                FarmRoleSetting::INSTANCE_TYPE,
                'rs.flavor-id',
                'azure.vm-size',
                'openstack.flavor-id',
                'gce.machine-type',
                'cloudstack.service_offering_id',
                'aws.instance_type',
        ]);
    }
}