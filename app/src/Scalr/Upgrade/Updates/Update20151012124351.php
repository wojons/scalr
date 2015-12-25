<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151012124351 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '238b59ed-d01f-445e-9f2d-ff2504ac38a7';

    protected $depends = [];

    protected $description = 'Add and initialize instance_type_name field in servers table';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('servers') && $this->hasTableColumn('servers', 'instance_type_name');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('servers', 'type');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `servers` ADD COLUMN `instance_type_name` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Instance type name' AFTER `type`");
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('servers') && $this->hasTableColumn('servers', 'instance_type_name');
    }

    protected function run2($stage)
    {
        $this->console->out("Initialize instance_type_name field with data from server_properties.");
        $this->db->Execute("
            UPDATE servers s
            JOIN server_properties sp ON sp.server_id = s.server_id
                AND sp.name='info.instance_type_name'
            SET s.instance_type_name = sp.value
        ");
    }

}