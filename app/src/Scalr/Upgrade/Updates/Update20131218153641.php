<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20131218153641 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '1a6723e8-0173-4f74-bd9c-c21dc97365aa';

    protected $depends = array(
        'f7f5b00a-b0a4-467b-940e-6e8d20304758'
    );

    protected $description = 'Creates tables for auditlog feature.';

    protected $type;

    protected $ignoreChanges = true;


    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 3;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::validateBefore()
     */
    public function validateBefore($stage = null)
    {
        return true;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('auditlog');
    }

    protected function run1($stage)
    {
        $this->console->out("Creating auditlog table");
        $this->db->Execute("
            CREATE TABLE `auditlog` (
              `id` varchar(36) NOT NULL,
              `sessionid` varchar(36) NOT NULL,
              `accountid` int(11) DEFAULT NULL,
              `userid` int(11) DEFAULT NULL,
              `email` varchar(100) DEFAULT NULL,
              `envid` int(11) DEFAULT NULL,
              `ip` int(10) unsigned DEFAULT NULL,
              `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `message` varchar(255) DEFAULT NULL,
              `datatype` varchar(255) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `sessionid` (`sessionid`),
              KEY `accountid` (`accountid`),
              KEY `userid` (`userid`),
              KEY `envid` (`envid`),
              KEY `time` (`time`)
            ) ENGINE=InnoDB
        ");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTable('auditlog_data');
    }

    protected function run2($stage)
    {
        $this->console->out("Creating auditlog_data table");
        $this->db->Execute("
            CREATE TABLE `auditlog_data` (
              `logid` varchar(36) NOT NULL,
              `key` varchar(255) NOT NULL,
              `old_value` text,
              `new_value` text,
              PRIMARY KEY (`logid`,`key`),
              KEY `key` (`key`),
              KEY `idx_old_value` (`old_value`(8)),
              KEY `idx_new_value` (`new_value`(8)),
              CONSTRAINT `FK_auditlog_data_logid` FOREIGN KEY (`logid`) REFERENCES `auditlog` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTable('auditlog_tags');
    }

    protected function run3($stage)
    {
        $this->console->out("Creating auditlog_tags table");
        $this->db->Execute("
            CREATE TABLE `auditlog_tags` (
              `logid` varchar(36) NOT NULL,
              `tag` varchar(36) NOT NULL,
              PRIMARY KEY (`logid`,`tag`),
              KEY `tag` (`tag`),
              CONSTRAINT `FK_auditlog_tags_logid` FOREIGN KEY (`logid`) REFERENCES `auditlog` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB
        ");
    }
}