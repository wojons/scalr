<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150915122632 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '54444a55-093d-4473-8a55-907131202a32';

    protected $depends = [];

    protected $description = "Creating storage for server termination info";

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
        return $this->hasTable('servers_termination_data');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("
        CREATE TABLE `servers_termination_data` (
          `server_id` VARCHAR(36) NOT NULL,
          `request` LONGTEXT NULL,
          `request_url` TEXT NULL,
          `request_query` TEXT NULL,
          `response_code` INT(3) NULL,
          `response_status` VARCHAR(64) NULL,
          `response` LONGTEXT NULL,
          PRIMARY KEY (`server_id`),
          INDEX `idx_response_code` (`response_code` ASC),
          CONSTRAINT `fk_d2a5124e110b9c45`
            FOREIGN KEY (`server_id`)
            REFERENCES `servers_history` (`server_id`)
            ON DELETE CASCADE
            ON UPDATE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");
    }
}