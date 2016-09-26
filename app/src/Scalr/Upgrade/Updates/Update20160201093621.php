<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160201093621 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '62a3ac8f-e619-4af0-9e99-f3c6d4e2d103';

    protected $depends = [];

    protected $description = "Create tables for Scalr health dashboard";

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
        return $this->hasTable('scalr_hosts');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Creating table of scalr hosts");

        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `scalr_hosts` (
                `host` VARCHAR(255) NOT NULL  COMMENT 'The name of the Scalr host',
                `version` VARCHAR(16) NOT NULL COMMENT 'Scalr version app/etc/version',
                `edition` VARCHAR(128) NOT NULL COMMENT 'Scalr edition',
                `git_commit` VARCHAR(64) DEFAULT NULL COMMENT 'Last git commit',
                `git_commit_added` DATETIME DEFAULT NULL COMMENT 'Date of last git commit',
                PRIMARY KEY (`host`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'Scalr hosts';
        ");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTable('scalr_services');
    }

    protected function validateBefore2($stage)
    {
        return true;
    }

    protected function run2($stage)
    {
        $this->console->out("Creating table of scalr_services");

        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `scalr_services` (
                `name` VARCHAR(64) NOT NULL COMMENT 'The unique name of the service',
                `num_workers` INT NOT NULL DEFAULT 0 COMMENT 'The last number of running workers',
                `num_tasks` INT NOT NULL DEFAULT 0 COMMENT 'The number of processed tasks on last run',
                `last_start` DATETIME DEFAULT NULL COMMENT 'Time of the last start',
                `last_finish` DATETIME DEFAULT NULL COMMENT 'Time of the last finish',
                `state` TINYINT UNSIGNED NULL COMMENT 'State of the service',
                PRIMARY KEY (`name`),
                KEY `idx_state` (`state`)
            ) ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'Scalr services';
        ");
    }
}
