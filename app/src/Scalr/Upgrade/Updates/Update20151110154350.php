<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151110154350 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '5d4216c6-0f10-4733-a774-c5843393209b';

    protected $depends = [];

    protected $description = "Initialize missing fields in servers_history";

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
        $this->console->out("Initializing missing fields with data from server_properties.");
        $this->db->Execute("
            UPDATE servers_history sh
            JOIN server_properties sp
                ON sp.server_id = sh.server_id AND sp.name = ?
            SET sh.instance_type_name = sp.value
            WHERE sh.instance_type_name IS NULL AND sp.value IS NOT NULL AND sp.value <> ''
        ", ['info.instance_type_name']);

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN server_properties sp
                ON sp.server_id = sh.server_id AND sp.name = ?
            SET sh.role_id = sp.value
            WHERE sh.role_id IS NULL AND sp.value IS NOT NULL AND sp.value <> ''
        ", [\SERVER_PROPERTIES::ROLE_ID]);

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN server_properties sp
                ON sp.server_id = sh.server_id AND sp.name = ?
            SET sh.farm_created_by_id = sp.value
            WHERE sh.farm_created_by_id IS NULL AND sp.value IS NOT NULL AND sp.value <> ''
        ", [\SERVER_PROPERTIES::FARM_CREATED_BY_ID]);

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN server_properties sp
                ON sp.server_id = sh.server_id AND sp.name = ?
            SET sh.project_id = UNHEX(REPLACE(sp.value, '-', ''))
            WHERE sh.project_id IS NULL AND sp.value IS NOT NULL AND sp.value <> ''
        ", [\SERVER_PROPERTIES::FARM_PROJECT_ID]);

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN server_properties sp
                ON sp.server_id = sh.server_id AND sp.name = ?
            SET sh.cc_id = UNHEX(REPLACE(sp.value, '-', ''))
            WHERE sh.cc_id IS NULL AND sp.value IS NOT NULL AND sp.value <> ''
        ", [\SERVER_PROPERTIES::ENV_CC_ID]);

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN server_properties sp
                ON sp.server_id = sh.server_id AND sp.name = ?
            SET sh.os_type = sp.value
            WHERE sh.os_type IS NULL AND sp.value IS NOT NULL AND sp.value <> ''
        ", [\SERVER_PROPERTIES::OS_TYPE]);

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN servers s
                ON s.server_id = sh.server_id
            SET sh.env_id = s.env_id
            WHERE sh.env_id IS NULL AND s.env_id IS NOT NULL
        ");

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN servers s
                ON s.server_id = sh.server_id
            SET sh.farm_id = s.farm_id
            WHERE sh.farm_id IS NULL AND s.farm_id IS NOT NULL
        ");

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN servers s
                ON s.server_id = sh.server_id
            SET sh.farm_roleid = s.farm_roleid
            WHERE sh.farm_roleid IS NULL AND s.farm_roleid IS NOT NULL
        ");

        $this->db->Execute("
            UPDATE servers_history sh
            JOIN servers s
                ON s.server_id = sh.server_id
            SET sh.server_index = s.`index`
            WHERE sh.server_index IS NULL AND s.`index` IS NOT NULL
        ");
    }
}