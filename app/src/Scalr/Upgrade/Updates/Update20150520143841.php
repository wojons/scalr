<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150520143841 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2412d6a5-d241-4bf9-942e-46761b96c8ae';

    protected $depends = [];

    protected $description = "Add 'cloud_location' field to servers_history table.";

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
        return $this->hasTableColumn('servers_history', 'cloud_location');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('servers_history');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding 'cloud_location' field to 'servers_history'");

        $this->db->Execute("
            ALTER TABLE `servers_history`
                ADD `cloud_location` VARCHAR(255) DEFAULT NULL AFTER `cloud_server_id`
        ");
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('servers_history', 'cloud_location');
    }

    protected function run2($stage)
    {
        $this->console->out("Initializing cloud_location for all existed servers");
        $this->db->Execute("
            UPDATE servers_history, servers
            SET servers_history.`cloud_location` = servers.`cloud_location`
            WHERE servers.`server_id` = servers_history.`server_id` AND servers_history.`cloud_location` IS NULL
        ");
    }

}