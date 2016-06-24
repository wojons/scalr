<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150827140744 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'bb0fee8e-184a-494e-8085-71ccc45ff122';

    protected $depends = [];

    protected $description = 'Remove id field, make server_id primary unique key, add new fields and initialize them';

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
        return $this->hasTable('servers_history') && !$this->hasTableColumn('servers_history', 'id');
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
                DROP COLUMN id,
                DROP PRIMARY KEY,
                DROP INDEX server_id,
                ADD PRIMARY KEY (server_id),
                ADD COLUMN project_id binary(16) DEFAULT NULL AFTER cloud_location,
                ADD COLUMN cc_id binary(16) DEFAULT NULL AFTER project_id,
                ADD COLUMN instance_type_name VARCHAR(50) DEFAULT NULL AFTER cc_id,
                ADD COLUMN role_id INT(11) DEFAULT NULL AFTER env_id,
                ADD COLUMN farm_created_by_id INT(11) DEFAULT NULL AFTER farm_roleid,
                ADD INDEX `idx_project_id` (project_id),
                ADD INDEX `idx_cc_id` (cc_id),
                MODIFY `server_id` VARCHAR(36) NOT NULL
        ");

        $this->console->out("Swap table names.");
        $this->db->Execute("RENAME TABLE servers_history TO servers_history_backup, servers_history_tmp TO servers_history");

        $this->console->out("Initializing cloud_location in servers_history_backup table");
        $this->db->Execute("
            UPDATE servers_history_backup h, farm_roles r
            SET h.cloud_location = r.cloud_location
            WHERE r.id = h.farm_roleid AND h.cloud_location IS NULL AND r.id > 0;");

        $this->console->out("Insert data from backup table to new servers_history.");
        $this->db->Execute("
            INSERT IGNORE INTO servers_history (
                    client_id, server_id, cloud_server_id, cloud_location,
                    dtlaunched, dtterminated, launch_reason_id, launch_reason,
                    terminate_reason_id, terminate_reason, platform, `type`,
                    env_id, farm_id, farm_roleid, server_index,
                    scu_used,scu_reported, scu_updated, scu_collecting)
                SELECT client_id, server_id, cloud_server_id, cloud_location,
                       dtlaunched, dtterminated, launch_reason_id, launch_reason,
                       terminate_reason_id, terminate_reason, platform, `type`,
                       env_id, farm_id, farm_roleid, server_index,
                       scu_used, scu_reported, scu_updated, scu_collecting
                FROM servers_history_backup
                ORDER BY id DESC");

        $this->console->out("Drop backup table.");
        $this->db->Execute("DROP TABLE IF EXISTS servers_history_backup");
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('servers_history') && $this->hasTableColumn('servers_history', 'cc_id');
    }

    protected function run2($stage)
    {
        $this->console->out("Initialize new fields with data from server_properties.");
        $this->db->Execute("
            UPDATE servers_history sh
                LEFT JOIN server_properties sp1
                    ON sp1.server_id = sh.server_id AND sp1.name = ?
                LEFT JOIN server_properties sp2
                    ON sp2.server_id = sh.server_id AND sp2.name = ?
                LEFT JOIN server_properties sp3
                    ON sp3.server_id = sh.server_id AND sp3.name = ?
                LEFT JOIN server_properties sp4
                    ON sp4.server_id = sh.server_id AND sp4.name = ?
                LEFT JOIN server_properties sp5
                    ON sp5.server_id = sh.server_id AND sp5.name = ?
            SET sh.project_id = UNHEX(REPLACE(sp1.value, '-', '')),
                sh.cc_id = UNHEX(REPLACE(sp2.value, '-', '')),
                sh.instance_type_name = sp3.value,
                sh.role_id = sp4.value,
                sh.farm_created_by_id = sp5.value
        ", [
            \SERVER_PROPERTIES::FARM_PROJECT_ID,
            \SERVER_PROPERTIES::ENV_CC_ID,
            'info.instance_type_name',
            \SERVER_PROPERTIES::ROLE_ID,
            \SERVER_PROPERTIES::FARM_CREATED_BY_ID
        ]);
    }

}