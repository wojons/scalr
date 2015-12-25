<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151008115445 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '73b12d26-3a06-48ce-aace-5400bc6e77f5';

    protected $depends = [];

    protected $description = "Migration to the new separate cloud credentials entity";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

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
        return $this->hasTable('cloud_credentials');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            CREATE TABLE `cloud_credentials` (
              `id` CHAR(12) NOT NULL,
              `account_id` INT NULL,
              `env_id` INT NULL,
              `name` VARCHAR(64) NOT NULL,
              `cloud` VARCHAR(20) NOT NULL,
              `status` TINYINT NULL DEFAULT 0,
              `description` VARCHAR(255) NULL,
              PRIMARY KEY (`id`),
              UNIQUE INDEX `idx_scope_name` (`name` ASC, `account_id` ASC, `env_id` ASC),
              INDEX `idx_account` (`account_id` ASC),
              INDEX `idx_env` (`env_id` ASC),
              INDEX `idx_cloud` (`cloud` ASC),
              INDEX `idx_ccid_cloud` (`id` ASC, `cloud` ASC))
            ENGINE = InnoDB
            DEFAULT CHARACTER SET = latin1
        ");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTable('cloud_credentials_properties');
    }

    protected function validateBefore2($stage)
    {
        return true;
    }

    protected function run2($stage)
    {
        $this->db->Execute("
            CREATE TABLE `cloud_credentials_properties` (
              `cloud_credentials_id` CHAR(12) NOT NULL,
              `name` VARCHAR(255) NOT NULL,
              `value` TEXT NULL,
              PRIMARY KEY (`cloud_credentials_id`, `name`),
              CONSTRAINT `fk_70cfb1f619cd7b1f`
                FOREIGN KEY (`cloud_credentials_id`)
                REFERENCES `cloud_credentials` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE)
            ENGINE = InnoDB
            DEFAULT CHARACTER SET = latin1
        ");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTable('environment_cloud_credentials');
    }

    protected function validateBefore3($stage)
    {
        return true;
    }

    protected function run3($stage)
    {
        $this->db->Execute("
            CREATE TABLE `environment_cloud_credentials` (
              `env_id` INT NOT NULL,
              `cloud` VARCHAR(20) NOT NULL,
              `cloud_credentials_id` CHAR(12) NOT NULL,
              PRIMARY KEY (`env_id`, `cloud`),
              INDEX `fk_939ecd9217a9244d_idx` (`cloud_credentials_id` ASC, `cloud` ASC),
              CONSTRAINT `fk_d25d9d49dedcc31a`
                FOREIGN KEY (`env_id`)
                REFERENCES `client_environments` (`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE,
              CONSTRAINT `fk_939ecd9217a9244d`
                FOREIGN KEY (`cloud_credentials_id`, `cloud`)
                REFERENCES `cloud_credentials` (`id`, `cloud`)
                ON DELETE RESTRICT
                ON UPDATE RESTRICT)
            ENGINE = InnoDB
            DEFAULT CHARACTER SET = latin1
        ");
    }
}