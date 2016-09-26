<?php
namespace Scalr\Upgrade\Updates;

use Exception;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use SERVER_PROPERTIES;

class Update20151106115029 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '104cfeb4-9faa-4e85-9834-a96b6abd995f';

    protected $depends = [];

    protected $description = "Add os_type column to servers_history and initialize it. Add indexes";

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
        return $this->hasTable('servers_history') && $this->hasTableColumn('servers_history', 'os_type');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('servers_history');
    }

    protected function run1($stage)
    {
        $this->console->out("Creating servers_history_tmp table.");
        $this->db->Execute("CREATE TABLE servers_history_tmp LIKE servers_history");

        $this->console->out("Make changes to tmp table.");
        $this->db->Execute("
            ALTER TABLE servers_history_tmp
                ADD COLUMN `os_type` enum('linux','windows') DEFAULT NULL AFTER platform,
                ADD INDEX `idx_platform` (platform),
                ADD INDEX `idx_cloud_location` (cloud_location),
                ADD INDEX `idx_cloud_server_id` (cloud_server_id)
        ");

        $this->console->out("Remove foreign key from table servers_termination_data");
        $this->db->Execute("ALTER TABLE servers_termination_data DROP FOREIGN KEY fk_d2a5124e110b9c45");

        $this->console->out("Swap table names.");
        $this->db->Execute("RENAME TABLE servers_history TO servers_history_backup, servers_history_tmp TO servers_history");

        $this->db->BeginTrans();

        try {
            $this->console->out("Insert data from backup table to new servers_history.");
            $this->db->Execute("
                INSERT IGNORE INTO servers_history (
                    client_id, server_id, cloud_server_id, cloud_location, project_id,
                    cc_id, instance_type_name, dtlaunched, dtterminated, launch_reason_id, launch_reason,
                    terminate_reason_id, terminate_reason, platform, `type`,
                    env_id, role_id, farm_id, farm_roleid, farm_created_by_id, server_index,
                    scu_used,scu_reported, scu_updated, scu_collecting)
                SELECT client_id, server_id, cloud_server_id, cloud_location, project_id,
                       cc_id, instance_type_name, dtlaunched, dtterminated, launch_reason_id, launch_reason,
                       terminate_reason_id, terminate_reason, platform, `type`,
                       env_id, role_id, farm_id, farm_roleid, farm_created_by_id, server_index,
                       scu_used,scu_reported, scu_updated, scu_collecting
                FROM servers_history_backup
            ");

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }

        $this->console->out("Drop backup table.");
        $this->db->Execute("DROP TABLE IF EXISTS servers_history_backup");
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('servers_history') && $this->hasTableColumn('servers_history', 'os_type');
    }

    protected function run2($stage)
    {
        $this->console->out("Initialize new field with data from server_properties.");
        $this->db->Execute("
            UPDATE servers_history sh
            JOIN server_properties sp
                ON sp.server_id = sh.server_id AND sp.name = ?
            SET sh.os_type = sp.value
        ", [SERVER_PROPERTIES::OS_TYPE]);
    }

}