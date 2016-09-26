<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use SERVER_STATUS;
use FARM_STATUS;

class Update20151110224843 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'f0eb9469-67f5-403e-8806-bca514cfb4e0';

    protected $depends = [];

    protected $description = 'Add and initialize farm_index column in servers table';

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
        return $this->hasTable('servers') && $this->hasTableColumn('servers', 'farm_index');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('servers');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding farm_index field to servers table");

        // The maximum number of the servers in the farm is limited by 16777215
        $this->db->Execute("
            ALTER TABLE `servers`
                ADD COLUMN `farm_index` MEDIUMINT UNSIGNED DEFAULT NULL
                    COMMENT 'Instance index in the farm' AFTER `index`,
                ADD INDEX `idx_farm_index` (`farm_index`)
        ");

        // Initializes user defined variables which should represent farm index and identifier accordingly.
        $this->db->Execute("SET @farm_index := 1, @farm := NULL");

        // Initializes farm index for the servers table.
        // Subquery sub is necessary here because of the ordering.
        $this->db->Execute("
            UPDATE servers, (
            SELECT @farm_index := IF(@farm <> sub.farm_id, 1, @farm_index + 1) AS farm_index,
                   @farm := sub.farm_id AS farm_id,
                   sub.server_id
            FROM (
                SELECT s.farm_id, s.server_id
                FROM servers s
                JOIN farms f ON f.id = s.farm_id
                WHERE f.status = ? AND s.status NOT IN (?, ?)
                ORDER BY f.id, s.server_id
            ) sub) t
            SET servers.farm_index = t.farm_index
            WHERE t.server_id = servers.server_id AND servers.farm_index IS NULL
        ", [
            FARM_STATUS::RUNNING,
            SERVER_STATUS::TERMINATED,
            SERVER_STATUS::PENDING_TERMINATE
        ]);
    }
}