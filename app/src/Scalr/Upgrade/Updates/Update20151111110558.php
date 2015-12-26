<?php
namespace Scalr\Upgrade\Updates;

use Exception;
use Scalr\Model\Entity\CloudInstanceType;
use Scalr\Model\Entity\CloudLocation;
use Scalr\Modules\PlatformFactory;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr_Environment;

class Update20151111110558 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '137d405d-8866-45e5-814c-b58f39b350c0';

    protected $depends = [];

    protected $description = 'Initialize instance_type_name, project_id in servers_history for running servers.';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('servers_history', 'instance_type_name');
    }

    protected function run1($stage)
    {
        $this->console->out("Initializing instance_type_name field in servers_history table");

        $result = $this->db->Execute("
            SELECT sh.* FROM servers_history sh
            JOIN servers s USING(server_id)
            WHERE sh.instance_type_name IS NULL
                AND sh.type IS NOT NULL
                AND sh.cloud_location IS NOT NULL
            ORDER BY sh.env_id, sh.platform DESC
        ");

        $env = null;
        $platform = null;

        $this->db->BeginTrans();

        try {
            $sql = "UPDATE servers_history sh SET sh.instance_type_name = ? WHERE sh.server_id = ?";

            while ($record = $result->FetchRow()) {
                if (!isset($env) || $env->id != $record['env_id']) {
                    $env = Scalr_Environment::init()->loadById($record['env_id']);
                    $platform = null;
                }

                if (in_array($record['platform'], [\SERVER_PLATFORMS::EC2, \SERVER_PLATFORMS::GCE])) {
                    $this->db->Execute($sql, [$record['type'], $record['server_id']]);
                    continue;
                }

                if (!isset($platform) || $platform != $record['platform']) {
                    $platform = $record['platform'];
                    $platformModule = PlatformFactory::NewPlatform($record['platform']);
                    $url = $platformModule->getEndpointUrl($env);
                }

                $cloudLocationId = CloudLocation::calculateCloudLocationId($record['platform'], $record['cloud_location'], $url);
                $instanceTypeEntity = CloudInstanceType::findPk($cloudLocationId, $record['type']);
                /* @var $instanceTypeEntity CloudInstanceType */
                if ($instanceTypeEntity) {
                    $this->db->Execute($sql, [$instanceTypeEntity->name, $record['server_id']]);
                }
            }

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
        }
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('servers_history', 'project_id');
    }

    protected function run2($stage)
    {
        $this->console->out("Initializing project_id field in servers_history table");

        $rows = $this->db->Execute("
            SELECT sh.server_id, fs.value AS project_id FROM servers_history sh
            JOIN servers s
                ON s.server_id = sh.server_id
            JOIN farm_settings fs
                ON fs.farmid = sh.farm_id AND fs.name = 'project_id'
            WHERE sh.project_id IS NULL AND fs.value IS NOT NULL AND LENGTH(fs.value) > 0
        ");

        $this->db->BeginTrans();

        try {
            while ($record = $rows->FetchRow()) {
                $dbServer = \DBServer::LoadByID($record['server_id']);
                $dbServer->SetProperty(\SERVER_PROPERTIES::FARM_PROJECT_ID, $record['project_id']);

                $this->db->Execute("
                    UPDATE servers_history sh
                    SET sh.project_id = ?
                    WHERE sh.servers_id = ?", [$record['project_id'], $record['server_id']]
                );
            }

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
        }
    }

    protected function isApplied3($stage)
    {
        return false;
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTableColumn('servers_history', 'type');
    }

    protected function run3($stage)
    {
        $this->console->out("Creating servers_history_tmp table.");
        $this->db->Execute("CREATE TABLE servers_history_tmp LIKE servers_history");

        $this->console->out("Make changes to tmp table.");
        $this->db->Execute("
            ALTER TABLE servers_history_tmp
                MODIFY `type` varchar(45) DEFAULT NULL
        ");

        $this->console->out("Swap table names.");
        $this->db->Execute("RENAME TABLE servers_history TO servers_history_backup, servers_history_tmp TO servers_history");

        $this->db->BeginTrans();

        try {
            $this->console->out("Insert data from backup table to new servers_history.");
            $this->db->Execute("INSERT IGNORE INTO servers_history SELECT * FROM servers_history_backup");

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }

        $this->console->out("Drop backup table.");
        $this->db->Execute("DROP TABLE IF EXISTS servers_history_backup");
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTableColumn('servers', 'type');
    }

    protected function run4($stage)
    {
        $this->console->out("Modifying type filed in servers table");

        $this->db->Execute("
            ALTER TABLE servers
                MODIFY `type` varchar(45) DEFAULT NULL
        ");
    }

}