<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150923141838 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'fc15fb3d-5bdb-47d2-9515-cd3a8e67f3dd';

    protected $depends = [];

    protected $description = "Convert `platform_usage` table charset to `latin1`";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    protected $sql;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    protected function isApplied1($stage)
    {
        return $this->db->GetOne("
            SELECT `CCSA`.`character_set_name`
                FROM `information_schema`.`TABLES` `T`
                LEFT JOIN `information_schema`.`COLLATION_CHARACTER_SET_APPLICABILITY` `CCSA`
                    ON `CCSA`.`collation_name` = `T`.`table_collation`
                WHERE `T`.`table_schema` = ? AND `T`.`table_name` = ?
        ", [$this->db->database, 'platform_usage']) == 'latin1';
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('platform_usage');
    }

    protected function run1($stage)
    {
        $this->console->out("Change `platform_usage` table default charset to 'latin1'");

        $this->sql[] = "CHARACTER SET = latin1";
    }

    protected function isApplied2($stage)
    {
        return $this->db->GetOne("
            SELECT `character_set_name`
                FROM `information_schema`.`COLUMNS`
                WHERE `table_schema` = ? AND `table_name` = ? AND `column_name` = ?
        ", [$this->db->database, 'platform_usage', 'platform']) == 'latin1';
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('platform_usage', 'platform');
    }

    protected function run2($stage)
    {
        $this->console->out("Change `platform_usage`.`platform` charset to 'latin1'");

        $this->sql[] = "CHANGE COLUMN `platform` `platform` VARCHAR(20) CHARACTER SET 'latin1' NOT NULL COMMENT 'Platform name'";
    }

    public function isApplied3($stage)
    {
        return empty($this->sql);
    }

    public function validateBefore3($stage)
    {
        return $this->hasTable('platform_usage');
    }

    public function run3($stage)
    {
        $this->console->out("Apply changes");
        $this->applyChanges('platform_usage', $this->sql);
    }
}