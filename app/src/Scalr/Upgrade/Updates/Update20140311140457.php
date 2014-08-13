<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140311140457 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '123799d0-e0e5-4ccf-95d9-0d5eb22829fa';

    protected $depends = array();

    protected $description = 'Creating webhooks tables';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('webhook_configs') && $this->hasTable('webhook_history');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('farms') && $this->hasTable('client_environments') &&
               $this->hasTable('clients');
    }

    protected function run1($stage)
    {
        try {
            foreach (preg_split('/;/', $this->_sqlScript) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt == '') continue;
                $this->db->Execute($stmt);
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function isApplied2($stage)
    {
        return $this->hasTable('webhook_history') && $this->hasTableColumn('webhook_history', 'farm_id');
    }

    protected function validateBefore2($stage)
    {
        return true;
    }

    protected function run2($stage)
    {
        throw new \Exception("For some reason you have development alpha version of the webhook* tables. They should be re-created from the scratch.");
    }

    private $_sqlScript = <<<EOL
CREATE TABLE IF NOT EXISTS `webhook_configs` (
  `webhook_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `level` TINYINT NOT NULL COMMENT '1 - Scalr, 2 - Account, 4 - Env, 8 - Farm',
  `name` VARCHAR(50) NOT NULL,
  `account_id` INT(11) NULL,
  `env_id` INT(11) NULL,
  `post_data` TEXT NULL,
  PRIMARY KEY (`webhook_id`),
  INDEX `idx_level` (`level` ASC),
  INDEX `idx_account_id` (`account_id` ASC),
  INDEX `idx_env_id` (`env_id` ASC),
  INDEX `idx_name` (`name`(3) ASC),
  CONSTRAINT `fk_4d3039820abc2a41c`
    FOREIGN KEY (`account_id`)
    REFERENCES `clients` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_dbedab24d097d2a71`
    FOREIGN KEY (`env_id`)
    REFERENCES `client_environments` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `webhook_config_events` (
  `webhook_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `event_type` VARCHAR(128) NOT NULL,
  PRIMARY KEY (`webhook_id`, `event_type`),
  INDEX `idx_event_type` (`event_type` ASC),
  CONSTRAINT `fk_40db098cb4b5c6797`
    FOREIGN KEY (`webhook_id`)
    REFERENCES `webhook_configs` (`webhook_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `webhook_config_farms` (
  `webhook_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `farm_id` INT(11) NOT NULL,
  PRIMARY KEY (`webhook_id`, `farm_id`),
  INDEX `idx_farm_id` (`farm_id` ASC),
  CONSTRAINT `fk_24503b0f582804419`
    FOREIGN KEY (`webhook_id`)
    REFERENCES `webhook_configs` (`webhook_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `webhook_endpoints` (
  `endpoint_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `level` TINYINT NOT NULL COMMENT '1 - Scalr, 2 - Account, 4 - Env',
  `account_id` INT(11) NULL,
  `env_id` INT(11) NULL,
  `url` TEXT NULL,
  `validation_token` BINARY(16) NULL COMMENT 'UUID',
  `is_valid` TINYINT(1) NOT NULL DEFAULT 0,
  `security_key` VARCHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`endpoint_id`),
  CONSTRAINT `fk_660a12dca2e1dae8a`
    FOREIGN KEY (`account_id`)
    REFERENCES `clients` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_816aef24d6e3f9aac`
    FOREIGN KEY (`env_id`)
    REFERENCES `client_environments` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `webhook_config_endpoints` (
  `webhook_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `endpoint_id` BINARY(16) NOT NULL COMMENT 'UUID',
  PRIMARY KEY (`webhook_id`, `endpoint_id`),
  INDEX `idx_endpoint_id` (`endpoint_id` ASC),
  CONSTRAINT `fk_4d800abd81968700c`
    FOREIGN KEY (`webhook_id`)
    REFERENCES `webhook_configs` (`webhook_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_6d8c137f54109dcaa`
    FOREIGN KEY (`endpoint_id`)
    REFERENCES `webhook_endpoints` (`endpoint_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `webhook_history` (
  `history_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `webhook_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `endpoint_id` BINARY(16) NOT NULL COMMENT 'UUID',
  `event_id` CHAR(36) NOT NULL COMMENT 'UUID',
  `farm_id` INT(11) NOT NULL,
  `event_type` VARCHAR(128) NOT NULL,
  `status` TINYINT NOT NULL DEFAULT 0,
  `response_code` SMALLINT NULL,
  `payload` TEXT NULL,
  PRIMARY KEY (`history_id`),
  INDEX `idx_webhook_id` (`webhook_id` ASC),
  INDEX `idx_endpoint_id` (`endpoint_id` ASC),
  INDEX `idx_event_id` (`event_id` ASC),
  INDEX `idx_status` (`status` ASC),
  INDEX `idx_event_type` (`event_type` ASC),
  INDEX `idx_farm_id` (`farm_id` ASC),
  INDEX `idx_created` (`created` ASC),
  CONSTRAINT `fk_4572d5cd4d19cd8c1`
    FOREIGN KEY (`endpoint_id`)
    REFERENCES `webhook_endpoints` (`endpoint_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_2fa13e63bff387aa2`
    FOREIGN KEY (`webhook_id`)
    REFERENCES `webhook_configs` (`webhook_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_35d689dd5c9d257f3`
    FOREIGN KEY (`farm_id`)
    REFERENCES `farms` (`id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION
) ENGINE = InnoDB;
EOL;
}