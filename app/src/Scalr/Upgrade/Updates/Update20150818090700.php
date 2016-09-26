<?php

namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150818090700 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'aed65931-0e23-4b11-8233-8051c54a3135';

    protected $depends = [];

    protected $description = "Add type field to servers table and initialize it.";

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
        return $this->hasTable('servers') && $this->hasTableColumn('servers', 'type');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('servers');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `servers` ADD COLUMN `type` VARCHAR(32) NULL DEFAULT NULL COMMENT 'Instance type' AFTER `platform`");
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('servers') && $this->hasTableColumn('servers', 'type');
    }

    protected function run2($stage)
    {
        $this->console->out("Initialize type field with data from server_properties.");
        $this->db->Execute("
            UPDATE servers s
            JOIN server_properties sp ON sp.server_id = s.server_id
                AND sp.name IN (?,?,?,?,?)
            SET s.type = sp.value
        ", ['azure.server-type', 'ec2.instance_type', 'gce.machine-type', 'openstack.flavor-id', 'rs.flavor-id']);
    }

}