<?php
namespace Scalr\Upgrade\Updates;

use Exception;
use Scalr\Model\Entity\OrchestrationLog;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160212110643 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '7e7b851c-5854-4758-aa06-d55ec5bddbab';

    protected $depends = [];

    protected $description = "Scripting log refactoring";

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
        return $this->hasTable('orchestration_log_manual_scripts');
    }

    protected function run1($stage)
    {
        if ($this->hasTable('scripting_log')) {
            $this->db->Execute("RENAME TABLE scripting_log TO orchestration_log");
        }

        $this->console->out("Creating new table orchestration_log_manual_scripts ...");

        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS orchestration_log_manual_scripts (
                `id` INT(11) NOT NULL AUTO_INCREMENT COMMENT 'Id',
                `orchestration_log_id` INT(11) NULL COMMENT 'Orchestration log id',
                `execution_id` VARCHAR(75) NOT NULL COMMENT 'The Execution id',
                `server_id` VARCHAR(36) NOT NULL COMMENT 'The server id',
                `user_id` INT(11) NULL COMMENT 'The user id',
                `user_email` VARCHAR(100) NULL COMMENT 'The user email',
                `added` datetime DEFAULT NULL COMMENT 'The created date',
                PRIMARY KEY (`id`),
                INDEX `idx_orchestration_log_id` (`orchestration_log_id`),
                INDEX `idx_execution_id` (`execution_id`),
                INDEX `idx_server_id` (`server_id`),
                INDEX `idx_added` (`added`))
            ENGINE = InnoDB DEFAULT CHARSET=latin1
            COMMENT = 'User data for orchestration log'
        ");

        if ($this->container->config->defined('scalr.crontab.services.rotate.keep.scalr.scripting_log')) {
            $this->console->warning('Scripting log has been renamed to Orchestration log and config section scalr.crontab.services.rotate.keep.scalr.scripting_log should also be renamed.');
        }
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('orchestration_log', 'type') &&
               !$this->hasTableColumn('orchestration_log', 'event');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('orchestration_log');
    }

    protected function run2($stage)
    {
        $this->db->BeginTrans();

        $this->console->out("Creating and initializing new fields...");

        $regexp = "^Scheduler \(TaskID: [0-9]+\)( \(manual\))?$";

        try {
            $this->db->Execute("
                ALTER TABLE orchestration_log
                DROP INDEX `idx_event`,
                CHANGE COLUMN `event` `type` VARCHAR(32),
                ADD COLUMN task_id INT(11) NULL COMMENT 'Scheduler task id'
            ");

            $this->db->Execute("
                UPDATE orchestration_log
                SET task_id = REPLACE(SUBSTRING(`type`, 1, CHAR_LENGTH(`type`)-1), 'Scheduler (TaskID: ', '')
                WHERE `type` REGEXP ?
            ", [$regexp]);

            $this->db->Execute("
                UPDATE orchestration_log
                SET `type` = CASE
                    WHEN `type` = 'Manual' THEN ?
                    WHEN `type` REGEXP ? THEN ?
                    WHEN `type` = '' OR `type` IS NULL THEN `type`
                    ELSE ?
                END
            ", [OrchestrationLog::TYPE_MANUAL, $regexp, OrchestrationLog::TYPE_SCHEDULER, OrchestrationLog::TYPE_EVENT]);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }

}