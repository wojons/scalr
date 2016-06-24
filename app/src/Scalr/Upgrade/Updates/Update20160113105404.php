<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160113105404 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '83eedd66-0d08-4865-93b2-098c2a253e1c';

    protected $depends = [];

    protected $description = 'Rename instance type policies names';

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
        return $this->hasTable('governance');
    }

    protected function run1($stage)
    {
        $this->console->out('Renaming instance type policies names...');

        $this->db->Execute("
            UPDATE governance
            SET name = ?
            WHERE name IN (?, ?, ?, ?)", [
                FarmRoleSetting::INSTANCE_TYPE,
                'azure.vm-size',
                'openstack.flavor-id',
                'cloudstack.service_offering_id',
                'aws.instance_type',
        ]);
    }
}