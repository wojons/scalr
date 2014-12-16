<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141119142404 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'f8a06c72-f0e4-4408-9885-9cbafb59814d';

    protected $depends = [];

    protected $description = 'Adding server_termination_errors table';

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
        return $this->hasTable('server_termination_errors');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('servers');
    }

    protected function run1($stage)
    {
        if (!$this->hasTable('server_termination_errors')) {
            $this->console->out('Executing create table server_termination_errors statement...');

            $this->db->Execute("
                CREATE TABLE IF NOT EXISTS `server_termination_errors`(
                    `server_id` VARCHAR(36) NOT NULL COMMENT 'servers.server_id ref',
                    `retry_after` DATETIME NOT NULL COMMENT 'After what time it should be revalidated',
                    `attempts` INT UNSIGNED NOT NULL DEFAULT '1' COMMENT 'The number of unsuccessful attempts',
                    `last_error` TEXT COMMENT 'Error message',
                    PRIMARY KEY (`server_id`),
                    INDEX `idx_retry_after` (`retry_after` ASC)
                ) ENGINE=InnoDB
                COMMENT='Server termination process errors'
            ");
        }
    }
}