<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Entity\SettingEntity;

/**
 * Creating analytics database
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @version  5.0 (09.01.2014)
 */
class Update20140109132935 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '22dd3ef7-9431-4d27-bf23-07d7deb00777';

    protected $depends = ['22dd3ef7-9431-4d27-bf23-07d7deb00776'];

    protected $description = 'Creating analytics database schema';

    protected $ignoreChanges = true;

    /**
     * Cost analytics database service
     *
     * @var string
     */
    protected $dbservice = 'cadb';

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 15;
    }

    private function _disableChecks()
    {
        $this->db->Execute("SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0");
        $this->db->Execute("SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0");
        $this->db->Execute("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL,ALLOW_INVALID_DATES'");
    }

    private function _enableChecks()
    {
        $this->db->Execute("SET SQL_MODE=@OLD_SQL_MODE;");
        $this->db->Execute("SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;");
        $this->db->Execute("SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;");
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('tags') &&
               $this->hasTable('usage_d') &&
               $this->db->GetOne("SELECT name FROM tags WHERE tag_id = 6") == 'User';
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->_disableChecks();

        try {
            foreach (preg_split('/;/', $this->sqlscript) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt == '') continue;
                $this->db->Execute($stmt);
            }
        } catch (\Exception $e) {
            $this->_enableChecks();
            throw $e;
        }

        $this->_enableChecks();
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableIndex('usage_h', 'idx_project_id') &&
               $this->hasTableIndex('usage_h', 'idx_cc_id') &&
               $this->hasTableIndex('usage_h', 'idx_farm_id') &&
               $this->hasTableIndex('usage_h', 'idx_env_id') &&
               $this->hasTableIndex('usage_h', 'idx_farm_role_id');
    }

    protected function run2($stage)
    {
        $this->console->out("Adding indexes to usage_h table.");

        if (!$this->hasTableIndex('usage_h', 'idx_project_id')) {
            $this->db->Execute("ALTER TABLE `usage_h` ADD INDEX `idx_project_id` (`project_id`)");
        }

        if (!$this->hasTableIndex('usage_h', 'cc_id')) {
            $this->db->Execute("ALTER TABLE `usage_h` ADD INDEX `idx_cc_id` (`cc_id`)");
        }

        if (!$this->hasTableIndex('usage_h', 'farm_id')) {
            $this->db->Execute("ALTER TABLE `usage_h` ADD INDEX `idx_farm_id` (`farm_id`)");
        }

        if (!$this->hasTableIndex('usage_h', 'env_id')) {
            $this->db->Execute("ALTER TABLE `usage_h` ADD INDEX `idx_env_id` (`env_id`)");
        }

        if (!$this->hasTableIndex('usage_h', 'farm_role_id')) {
            $this->db->Execute("ALTER TABLE `usage_h` ADD INDEX `idx_farm_role_id` (`farm_role_id`)");
        }
    }

    protected function isApplied3($stage)
    {
        return $this->hasTable('settings');
    }

    protected function validateBefore3($stage)
    {
        return true;
    }

    protected function run3($stage)
    {
        $this->console->out("Adding 'settings' table to analytics database...");
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `settings` (
              `id` VARCHAR(64) NOT NULL COMMENT 'setting ID',
              `value` TEXT NULL COMMENT 'The value',
              PRIMARY KEY (`id`))
            ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'system settings'
        ");

        $this->console->out("Initializing 'settings' table with budget_days value...");
        $this->db->Execute("
            INSERT IGNORE INTO `settings` (`id`, `value`)
            VALUES (?, '[\"01-01\",\"04-01\",\"07-01\",\"10-01\"]')
        ", [SettingEntity::ID_BUDGET_DAYS]);
    }

    protected function isApplied4($stage)
    {
        return $this->hasTable('usage_h') &&
               $this->hasTableColumn('usage_h', 'dtime') &&
               !$this->hasTableColumn('usage_h', 'hour') &&
               !$this->hasTableColumn('usage_h', 'date') &&
               $this->hasTableColumn('nm_usage_h', 'dtime') &&
               !$this->hasTableColumn('nm_usage_h', 'hour') &&
               !$this->hasTableColumn('nm_usage_h', 'date');
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('usage_h');
    }

    protected function run4($stage)
    {
        if (!$this->hasTableColumn('usage_h', 'dtime')) {
            $this->console->out("Replacing date and hour with dtime column for usage_h table...");
            $this->db->Execute("
                ALTER TABLE `usage_h`
                    ADD COLUMN `dtime` DATETIME NOT NULL COMMENT 'Time in Y-m-d H:00:00' AFTER `account_id`,
                    DROP INDEX `idx_find`,
                    ADD INDEX `idx_find` (`account_id` ASC, `dtime` ASC),
                    ADD INDEX `idx_dtime` (`dtime` ASC),
                    DROP INDEX `idx_date`;
            ");

            $this->db->Execute("
                UPDATE `usage_h`
                SET `dtime` = CONCAT(`date`, ' ', LPAD(`hour`, 2, '0'), ':00:00')
                WHERE dtime IS NULL
            ");

        }

        if (!$this->hasTableColumn('nm_usage_h', 'dtime')) {
            $this->console->out("Replacing date and hour with dtime column for nm_usage_h table...");
            $this->db->Execute("
                ALTER TABLE `nm_usage_h`
                    ADD COLUMN `dtime` DATETIME NOT NULL COMMENT 'Time in Y-m-d H:00:00' AFTER `usage_id`,
                    ADD INDEX `idx_dtime` (`dtime` ASC),
                    DROP INDEX `idx_date`;
            ");

            $this->db->Execute("
                UPDATE `nm_usage_h`
                SET `dtime` = CONCAT(`date`, ' ', LPAD(`hour`, 2, '0'), ':00:00')
                WHERE dtime IS NULL
            ");

        }

        if ($this->hasTableColumn('usage_h', 'hour') || $this->hasTableColumn('usage_h', 'date')) {
            $this->db->Execute("
                ALTER TABLE `usage_h`
                    DROP COLUMN `hour`,
                    DROP COLUMN `date`;
            ");
        }

        if ($this->hasTableColumn('nm_usage_h', 'hour') || $this->hasTableColumn('nm_usage_h', 'date')) {
            $this->db->Execute("
                ALTER TABLE `nm_usage_h`
                    DROP COLUMN `hour`,
                    DROP COLUMN `date`;
            ");
        }
    }

    protected function isApplied5($stage)
    {
        return $this->hasTable('quarterly_budget') &&
               $this->hasTableColumn('quarterly_budget', 'cumulativespend');
    }

    protected function run5($stage)
    {
        if (!$this->hasTable('quarterly_budget')) {
            $this->console->out("Adding quarterly_budget table to analytics database...");
            $this->db->Execute("
                CREATE TABLE IF NOT EXISTS `quarterly_budget` (
                  `year` SMALLINT NOT NULL COMMENT 'The year [2014]',
                  `subject_type` TINYINT NOT NULL COMMENT '1 - CC, 2 - Project',
                  `subject_id` BINARY(16) NOT NULL COMMENT 'ID of the CC or Project',
                  `quarter` TINYINT NOT NULL COMMENT 'Quarter [1-4]',
                  `budget` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Budget dollar amount',
                  `final` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Final spent',
                  `spentondate` DATETIME NULL COMMENT 'Spent on date',
                  PRIMARY KEY (`year`, `subject_type`, `subject_id`, `quarter`),
                  INDEX `idx_year` (`year` ASC, `quarter` ASC),
                  INDEX `idx_quarter` (`quarter` ASC),
                  INDEX `idx_subject_type` (`subject_type` ASC, `subject_id` ASC),
                  INDEX `idx_subject_id` (`subject_id` ASC))
                ENGINE = InnoDB DEFAULT CHARSET=utf8
                COMMENT = 'Quarterly budget'
            ");
        }

        if (!$this->hasTableColumn('quarterly_budget', 'cumulativespend')) {
            $this->console->out("Adding cumulativespend column to quarterly_budget database table");
            $this->db->Execute("
                ALTER TABLE `quarterly_budget`
                    ADD COLUMN `cumulativespend` DECIMAL(12,6) NOT NULL DEFAULT 0.000000
                    COMMENT 'Cumulative spend' AFTER `spentondate`
            ");
        }
    }

    protected function isApplied6($stage)
    {
        $rule = $this->getTableConstraint('fk_nmusagesubjectsh_nmusageh', 'nm_usage_subjects_h');
        return isset($rule['DELETE_RULE']) && $rule['DELETE_RULE'] == 'CASCADE';
    }

    protected function run6($stage)
    {
        $this->console->out("Modifying fk_nmusagesubjectsh_nmusageh constraint for nm_usage_subjects_h table");

        if ($this->hasTableForeignKey('fk_nmusagesubjectsh_nmusageh', 'nm_usage_subjects_h')) {
            $this->db->Execute("ALTER TABLE `nm_usage_subjects_h` DROP FOREIGN KEY `fk_nmusagesubjectsh_nmusageh`");
        }

        $this->db->Execute("
            ALTER TABLE `nm_usage_subjects_h`
                ADD CONSTRAINT `fk_nmusagesubjectsh_nmusageh`
                FOREIGN KEY (`usage_id`)
                REFERENCES `nm_usage_h` (`usage_id`)
                ON DELETE CASCADE
        ");

    }

    protected function isApplied7($stage)
    {
        return $this->hasTable('nm_usage_d');
    }

    protected function run7($stage)
    {
        $this->console->out("Creating nm_usage_d table...");

        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `nm_usage_d` (
              `date` DATE NOT NULL COMMENT 'UTC Date',
              `platform` VARCHAR(20) NOT NULL COMMENT 'Cloud platform',
              `cc_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of Cost centre',
              `env_id` INT(11) NOT NULL COMMENT 'ID of Environment',
              `cost` DECIMAL(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'Daily usage',
              PRIMARY KEY (`date`, `platform`, `cc_id`, `env_id`),
              INDEX `idx_cc_id` (`cc_id` ASC),
              INDEX `idx_env_id` (`env_id` ASC),
              INDEX `idx_platform` (`platform` ASC))
            ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'Not managed daily usage'
        ");
    }

    protected function isApplied8($stage)
    {
        return $this->hasTable('notifications');
    }

    protected function run8($stage)
    {
        $this->console->out("Creating notifications table...");

        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `notifications` (
              `uuid` BINARY(16) NOT NULL COMMENT 'unique identifier',
              `subject_type` TINYINT NOT NULL COMMENT '1- CC, 2 - Project',
              `notification_type` TINYINT NOT NULL COMMENT 'Type of the notification',
              `threshold` DECIMAL(12,2) NOT NULL,
              `recipient_type` TINYINT NOT NULL DEFAULT 1 COMMENT '1 - Leads 2 - Emails',
              `emails` TEXT NULL COMMENT 'Comma separated recipients',
              PRIMARY KEY (`uuid`),
              INDEX `idx_notification_type` (`notification_type` ASC),
              INDEX `idx_subject_type` (`subject_type` ASC),
              INDEX `idx_recipient_type` (`recipient_type` ASC))
            ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'Notifications'
        ");
    }

    protected function isApplied9($stage)
    {
        return $this->hasTableColumnDefault('notifications', 'recipient_type', '1');
    }

    protected function run9($stage)
    {
        $this->console->out("Modifying recipient_type column of the notifications table...");

        $this->db->Execute("
            ALTER TABLE `notifications`
                MODIFY `recipient_type` TINYINT NOT NULL DEFAULT 1 COMMENT '1 - Leads 2 - Emails'
        ");
    }

    protected function isApplied10($stage)
    {
        return $this->hasTableColumn('usage_d', 'env_id') &&
               $this->hasTableIndex('usage_d', 'idx_env_id');
    }

    protected function validateBefore10($stage)
    {
        return $this->hasTableColumn('usage_d', 'project_id');
    }

    protected function run10($stage)
    {
        $sql = [];
        $this->console->out("Adding env_id column to usage_d table...");

        if (!$this->hasTableColumn('usage_d', 'env_id')) {
            $sql[] = "ADD COLUMN `env_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'ID of the environment' AFTER `project_id`";
            $bInitializeEnvironment = true;
        }

        if (!$this->hasTableIndex('usage_d', 'idx_env_id')) {
            $sql[] = "ADD INDEX `idx_env_id` (`env_id` ASC)";
        }

        if (!empty($sql)) {
            //Proceed with one sql query
            $this->db->Execute("ALTER TABLE `usage_d` " . join(', ', $sql));

            if (isset($bInitializeEnvironment)) {
                $this->console->out("Initializing env_id with values...");
                $db = \Scalr::getContainer()->adodb;

                $this->db->Execute("CREATE TEMPORARY TABLE `tmp_farms` (farm_id INT(11) NOT NULL, env_id INT(11), PRIMARY KEY (farm_id))");

                $stmtValues = '';

                $rs = $db->Execute("SELECT id, env_id FROM farms");

                while ($rec = $rs->FetchRow()) {
                    $stmtValues .= ", (" . intval($rec['id']) . ", " . intval($rec['env_id']) . ")";
                }

                if ($stmtValues != '') {
                    $this->db->Execute("INSERT IGNORE `tmp_farms` (farm_id, env_id) VALUES" . ltrim($stmtValues, ','));
                    $this->db->Execute("
                        UPDATE usage_d, tmp_farms
                        SET usage_d.env_id = tmp_farms.env_id
                        WHERE usage_d.farm_id = tmp_farms.farm_id
                        AND (usage_d.env_id = 0 OR usage_d.env_id IS NULL)
                    ");
                }

                $this->db->Execute("DROP TEMPORARY TABLE `tmp_farms`");
            }
        }
    }

    protected function isApplied11($stage)
    {
        return $this->hasTableColumnType('poller_sessions', 'dtime', 'datetime');
    }

    protected function run11($stage)
    {
        $this->console->out("Modifying poller_sessions.dtype column type to datetime...");
        $this->db->Execute("
            ALTER TABLE `poller_sessions`
                MODIFY `dtime` DATETIME NOT NULL COMMENT 'The timestamp retrieved from the response'
        ");
    }

    protected function isApplied12($stage)
    {
        return $this->hasTable('reports');
    }

    protected function run12($stage)
    {
        $this->console->out("Creating reports table in analytics database.");
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `reports` (
              `uuid` BINARY(16) NOT NULL COMMENT 'unique identifier',
              `subject_type` TINYINT NULL COMMENT '1- CC, 2 - Project, NULL - Summary',
              `subject_id` BINARY(16) NULL,
              `period` TINYINT NOT NULL COMMENT 'Period',
              `emails` TEXT NOT NULL COMMENT 'Comma separated recipients',
              PRIMARY KEY (`uuid`),
              INDEX `idx_subject_type` (`subject_type` ASC),
              INDEX `idx_period` (`period` ASC))
            ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'Reports'
        ");
    }

    protected function isApplied13($stage)
    {
        return $this->hasTable('timeline_events');
    }

    protected function run13($stage)
    {
        $this->console->out("Adding 'timeline_events' table to analytics database...");
        $this->db->Execute("
            CREATE  TABLE IF NOT EXISTS `timeline_events` (
              `uuid` BINARY(16) NOT NULL COMMENT 'UUID' ,
              `event_type` TINYINT UNSIGNED NOT NULL COMMENT 'The type of the event' ,
              `dtime` DATETIME NOT NULL COMMENT 'The time of the event' ,
              `user_id` INT(11) NULL COMMENT 'User who triggered this event' ,
              `account_id` int(11) NULL COMMENT 'Account that triggered this event' ,
              `env_id` int(11) NULL COMMENT 'Enviroment that triggered this event' ,
              `description` TEXT NOT NULL COMMENT 'Description' ,
              PRIMARY KEY (`uuid`) ,
              INDEX `idx_dtime` (`dtime` ASC) ,
              INDEX `idx_event_type` (`event_type` ASC) ,
              INDEX `idx_env_id` (`env_id` ASC) ,
              INDEX `idx_account_id` (`account_id` ASC) ,
              INDEX `idx_user_id` (`user_id` ASC))
            ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'Timeline events'
        ");
    }

    protected function isApplied14($stage)
    {
        return $this->hasTable('timeline_event_ccs') &&
               $this->hasTable('timeline_event_projects');
    }

    protected function validateBefore14($stage)
    {
        return $this->hasTable('timeline_events');
    }

    protected function run14($stage)
    {
        if (!$this->hasTable('timeline_event_ccs')) {
            $this->console->out("Adding 'timeline_event_css' table to analytics database...");
            $this->db->Execute("
                CREATE TABLE IF NOT EXISTS `timeline_event_ccs` (
                  `event_id` BINARY(16) NOT NULL COMMENT 'timeline_events.uuid reference',
                  `cc_id` BINARY(16) NOT NULL COMMENT 'scalr.ccs.cc_id reference',
                  PRIMARY KEY (`event_id`, `cc_id`),
                  INDEX `idx_cc_id` (`cc_id` ASC),
                  CONSTRAINT `fk_2af56955167b`
                    FOREIGN KEY (`event_id`)
                    REFERENCES `timeline_events` (`uuid`)
                    ON DELETE CASCADE)
                ENGINE = InnoDB DEFAULT CHARSET=utf8
            ");
        }

        if (!$this->hasTable('timeline_event_projects')) {
            $this->console->out("Adding 'timeline_event_projects' table to analytics database...");
            $this->db->Execute("
                CREATE TABLE IF NOT EXISTS `timeline_event_projects` (
                  `event_id` BINARY(16) NOT NULL COMMENT 'timeline_events.uuid ref',
                  `project_id` BINARY(16) NOT NULL COMMENT 'scalr.projects.project_id ref',
                  PRIMARY KEY (`event_id`, `project_id`),
                  INDEX `idx_project_id` (`project_id` ASC),
                  CONSTRAINT `fk_e0325ab740c9`
                    FOREIGN KEY (`event_id`)
                    REFERENCES `timeline_events` (`uuid`)
                    ON DELETE CASCADE)
                ENGINE = InnoDB DEFAULT CHARSET=utf8
            ");
        }
    }

    protected function isApplied15($stage)
    {
        return $this->hasTable('report_payloads');
    }

    protected function run15($stage)
    {
        $this->console->out("Adding 'report_payloads' table to analytics database...");
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `report_payloads` (
              `uuid` BINARY(16) NOT NULL COMMENT 'UUID',
              `created` DATETIME NOT NULL COMMENT 'Creation timestamp (UTC)',
              `secret` BINARY(20) NOT NULL COMMENT 'Secret hash (SHA1)',
              `payload` MEDIUMTEXT NOT NULL COMMENT 'Payload',
              PRIMARY KEY (`uuid`),
              INDEX `idx_created` (`created` ASC))
            ENGINE = InnoDB DEFAULT CHARSET=utf8
            COMMENT = 'Report payloads';
        ");
    }

    private $sqlscript = <<<EOL

CREATE TABLE IF NOT EXISTS `upgrades` (
  `uuid` BINARY(16) NOT NULL COMMENT 'Unique identifier of update',
  `released` DATETIME NOT NULL COMMENT 'The time when upgrade script is issued',
  `appears` DATETIME NOT NULL COMMENT 'The time when upgrade does appear',
  `applied` DATETIME DEFAULT NULL COMMENT 'The time when update is successfully applied',
  `status` TINYINT(4) NOT NULL COMMENT 'Upgrade status',
  `hash` VARBINARY(20) DEFAULT NULL COMMENT 'SHA1 hash of the upgrade file',
  PRIMARY KEY (`uuid`),
  KEY `idx_status` (`status`),
  KEY `idx_appears` (`appears`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `upgrade_messages` (
  `uuid` BINARY(16) NOT NULL COMMENT 'upgrades.uuid reference',
  `created` DATETIME NOT NULL COMMENT 'Creation timestamp',
  `message` TEXT COMMENT 'Error messages',
  KEY `idx_uuid` (`uuid`),
  CONSTRAINT `upgrade_messages_ibfk_1` FOREIGN KEY (`uuid`) REFERENCES `upgrades` (`uuid`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `poller_sessions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `poller_sessions` (
  `sid` BINARY(16) NOT NULL COMMENT 'The unique identifier of the poll session',
  `account_id` INT(11) NOT NULL COMMENT 'clients.id reference',
  `env_id` INT(11) NOT NULL COMMENT 'client_environments.id reference',
  `dtime` DATETIME NOT NULL COMMENT 'The timestamp retrieved from the response',
  `platform` VARCHAR(20) NOT NULL COMMENT 'The ID of the Platform',
  `url` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Keystone endpoint',
  `cloud_location` VARCHAR(255) NULL COMMENT 'Cloud location ID',
  `cloud_account` VARCHAR(32) NULL,
  PRIMARY KEY (`sid`),
  INDEX `idx_dtime` (`dtime` ASC),
  INDEX `idx_platform` (`platform` ASC, `url` ASC, `cloud_location` ASC),
  INDEX `idx_cloud_id` (`account_id` ASC),
  INDEX `idx_account` (`account_id` ASC, `env_id` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Poller sessions';


-- -----------------------------------------------------
-- Table `managed`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `managed` (
  `sid` BINARY(16) NOT NULL COMMENT 'The identifier of the poll session',
  `server_id` BINARY(16) NOT NULL COMMENT 'scalr.servers.server_id ref',
  `instance_type` VARCHAR(45) NOT NULL COMMENT 'The type of the instance',
  `os` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 - linux, 1 - windows',
  PRIMARY KEY (`sid`, `server_id`),
  INDEX `idx_server_id` (`server_id`),
  INDEX `idx_instance_type` (`instance_type`),
  CONSTRAINT `fk_managed_poller_sessions`
    FOREIGN KEY (`sid`)
    REFERENCES `poller_sessions` (`sid`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT)
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'The presence of the managed servers on cloud';


-- -----------------------------------------------------
-- Table `price_history`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `price_history` (
  `price_id` BINARY(16) NOT NULL COMMENT 'The ID of the price',
  `platform` VARCHAR(20) NOT NULL COMMENT 'Platform name',
  `url` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Keystone endpoint',
  `cloud_location` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'The cloud location',
  `account_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'The ID of the account',
  `applied` DATE NOT NULL COMMENT 'The date after which new prices are applied',
  `deny_override` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'It is used only with account_id = 0',
  PRIMARY KEY (`price_id`),
  UNIQUE INDEX `idx_unique` (`platform` ASC, `url` ASC, `cloud_location` ASC, `applied` ASC, `account_id` ASC),
  INDEX `idx_applied` (`applied` ASC),
  INDEX `idx_account_id` (`account_id` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'The price changes';

-- -----------------------------------------------------
-- Table `prices`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `prices` (
  `price_id` BINARY(16) NOT NULL COMMENT 'The ID of the revision',
  `instance_type` VARCHAR(45) NOT NULL COMMENT 'The type of the instance',
  `os` TINYINT(1) NOT NULL COMMENT '0 - linux, 1 - windows',
  `name` VARCHAR(45) NOT NULL DEFAULT '' COMMENT 'The display name',
  `cost` DECIMAL(9,6) UNSIGNED NOT NULL DEFAULT 0.0 COMMENT 'The hourly cost of usage (USD)',
  PRIMARY KEY (`price_id`, `instance_type`, `os`),
  INDEX `idx_instance_type` (`instance_type` ASC, `os` ASC),
  INDEX `idx_name` (`name`(3) ASC),
  CONSTRAINT `fk_prices_price_revisions`
    FOREIGN KEY (`price_id`)
    REFERENCES `price_history` (`price_id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'The Cloud prices for specific revision';



-- -----------------------------------------------------
-- Table `tags`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `tag_id` INT(11) UNSIGNED NOT NULL COMMENT 'The unique identifier of the tag',
  `name` VARCHAR(127) NOT NULL COMMENT 'The display name of the tag',
  PRIMARY KEY (`tag_id`))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Tags';

-- -----------------------------------------------------
-- Table `account_tag_values`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `account_tag_values` (
  `account_id` INT(11) NOT NULL COMMENT 'The ID of the account',
  `tag_id` INT(11) UNSIGNED NOT NULL COMMENT 'The ID of the tag',
  `value_id` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'The unique identifier of the value for the associated tag',
  `value_name` VARCHAR(255) NULL COMMENT 'Display name for the tag value may be omitted.',
  PRIMARY KEY (`account_id`, `tag_id`, `value_id`),
  INDEX `idx_tag` (`tag_id` ASC, `value_id` ASC),
  CONSTRAINT `fk_account_tag_values_tags`
    FOREIGN KEY (`tag_id`)
    REFERENCES `tags` (`tag_id`)
    ON DELETE RESTRICT
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Account level tag values';


-- -----------------------------------------------------
-- Table `usage_h`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `usage_h` (
  `usage_id` BINARY(16) NOT NULL COMMENT 'The unique identifier for the usage record',
  `account_id` INT(11) NOT NULL COMMENT 'clients.id reference',
  `dtime` DATETIME NOT NULL COMMENT 'Time in Y-m-d H:00:00',
  `platform` VARCHAR(20) NOT NULL COMMENT 'The cloud type',
  `url` VARCHAR(255) NOT NULL DEFAULT '',
  `cloud_location` VARCHAR(255) NOT NULL COMMENT 'The cloud location',
  `instance_type` VARCHAR(45) NOT NULL COMMENT 'The type of the instance',
  `os` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 - linux, 1 - windows',
  `cc_id` BINARY(16) NULL COMMENT 'ID of cost centre',
  `project_id` BINARY(16) NULL COMMENT 'ID of the project',
  `env_id` INT(11) NULL COMMENT 'client_environments.id reference',
  `farm_id` INT(11) NULL COMMENT 'farms.id reference',
  `farm_role_id` INT(11) NULL COMMENT 'farm_roles.id reference',
  `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'The number of the same instances',
  `cost` DECIMAL(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'Cost of usage',
  PRIMARY KEY (`usage_id`),
  INDEX `idx_find` (`account_id` ASC, `dtime` ASC),
  INDEX `idx_platform` (`platform` ASC, `url` ASC, `cloud_location` ASC),
  INDEX `idx_instance_type` (`instance_type` ASC),
  INDEX `idx_cc_id` (`cc_id` ASC),
  INDEX `idx_project_id` (`project_id` ASC),
  INDEX `idx_farm_id` (`farm_id` ASC),
  INDEX `idx_env_id` (`env_id` ASC),
  INDEX `idx_farm_role_id` (`farm_role_id` ASC),
  INDEX `idx_dtime` (`dtime` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Hourly usage';

-- -----------------------------------------------------
-- Table `notmanaged`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `notmanaged` (
  `sid` BINARY(16) NOT NULL COMMENT 'The ID of the poller session',
  `instance_id` VARCHAR(36) NOT NULL COMMENT 'The ID of the instance which is not managed by Scalr',
  `instance_type` VARCHAR(45) NOT NULL COMMENT 'The type of the instance',
  `os` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sid`, `instance_id`),
  INDEX `idx_instance_id` (`instance_id`),
  INDEX `idx_instance_type` (`instance_type`),
  CONSTRAINT `fk_notmanaged_poller_sessions`
    FOREIGN KEY (`sid`)
    REFERENCES `poller_sessions` (`sid`)
    ON DELETE CASCADE
    ON UPDATE RESTRICT)
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'The presence of the not managed nodes';


-- -----------------------------------------------------
-- Table `usage_h_tags`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `usage_h_tags` (
  `usage_id` BINARY(16) NOT NULL,
  `tag_id` INT(11) UNSIGNED NOT NULL,
  `value_id` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`usage_id`, `tag_id`, `value_id`),
  INDEX `idx_tag` (`tag_id` ASC, `value_id` ASC),
  CONSTRAINT `fk_usage_h_tags_usage_h`
    FOREIGN KEY (`usage_id`)
    REFERENCES `usage_h` (`usage_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_usage_h_tags_account_tag_values`
    FOREIGN KEY (`tag_id` , `value_id`)
    REFERENCES `account_tag_values` (`tag_id` , `value_id`)
    ON DELETE RESTRICT
    ON UPDATE RESTRICT)
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Hourly usage tags';


-- -----------------------------------------------------
-- Table `nm_usage_h`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `nm_usage_h` (
  `usage_id` BINARY(16) NOT NULL COMMENT 'ID of the usage',
  `dtime` DATETIME NOT NULL COMMENT 'Time in Y-m-d H:00:00',
  `platform` VARCHAR(20) NOT NULL COMMENT 'The type of the cloud',
  `url` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Keystone endpoint',
  `cloud_location` VARCHAR(255) NOT NULL COMMENT 'Cloud location',
  `instance_type` VARCHAR(45) NOT NULL COMMENT 'The type of the instance',
  `os` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 - linux, 1 - windows',
  `num` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of the same instances',
  `cost` DECIMAL(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'The cost of the usage',
  PRIMARY KEY (`usage_id`),
  INDEX `idx_platform` (`platform` ASC, `url` ASC, `cloud_location` ASC),
  INDEX `idx_dtime` (`dtime` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Not managed servers hourly usage';

-- -----------------------------------------------------
-- Table `nm_subjects_h`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `nm_subjects_h` (
  `subject_id` BINARY(16) NOT NULL COMMENT 'ID of the subject',
  `env_id` INT(11) NOT NULL COMMENT 'client_environments.id reference',
  `cc_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of cost centre',
  `account_id` INT(11) NOT NULL COMMENT 'clients.id reference',
  PRIMARY KEY (`subject_id`, `env_id`),
  UNIQUE INDEX `idx_unique` (`env_id` ASC, `cc_id` ASC),
  INDEX `idx_cc_id` (`cc_id` ASC),
  INDEX `idx_account_id` (`account_id` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Subjects to associate with usage';


-- -----------------------------------------------------
-- Table `nm_usage_subjects_h`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `nm_usage_subjects_h` (
  `usage_id` BINARY(16) NOT NULL COMMENT 'ID of the usage',
  `subject_id` BINARY(16) NOT NULL COMMENT 'ID of the subject',
  PRIMARY KEY (`usage_id`, `subject_id`),
  INDEX `idx_subject_id` (`subject_id` ASC),
  CONSTRAINT `fk_nmusagesubjectsh_nmusageh`
    FOREIGN KEY (`usage_id`)
    REFERENCES `nm_usage_h` (`usage_id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_nmusagesubjectsh_nmsubjectsh`
    FOREIGN KEY (`subject_id`)
    REFERENCES `nm_subjects_h` (`subject_id`))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Subjects - Usages';

-- -----------------------------------------------------
-- Table `usage_servers_h`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `usage_servers_h` (
  `usage_id` BINARY(16) NOT NULL,
  `server_id` BINARY(16) NOT NULL COMMENT 'scalr.servers.server_id ref',
  `instance_id` VARCHAR(36) NOT NULL COMMENT 'cloud server id',
  PRIMARY KEY (`usage_id`, `server_id`),
  INDEX `idx_server_id` (`server_id` ASC),
  INDEX `idx_instance_id` (`instance_id` ASC),
  CONSTRAINT `fk_26ff9423b1bc`
    FOREIGN KEY (`usage_id`)
    REFERENCES `usage_h` (`usage_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Servers associated with usage';

-- -----------------------------------------------------
-- Table `nm_usage_servers_h`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `nm_usage_servers_h` (
  `usage_id` BINARY(16) NOT NULL COMMENT 'nm_usage_h.usage_id ref',
  `instance_id` VARCHAR(36) NOT NULL COMMENT 'Instance ID',
  PRIMARY KEY (`usage_id`, `instance_id`),
  INDEX `idx_instance_id` (`instance_id` ASC),
  CONSTRAINT `fk_22300db65385`
    FOREIGN KEY (`usage_id`)
    REFERENCES `nm_usage_h` (`usage_id`)
    ON DELETE CASCADE
    ON UPDATE NO ACTION)
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT 'Instances associated with the usage';

-- -----------------------------------------------------
-- Table `usage_d`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `usage_d` (
  `date` DATE NOT NULL COMMENT 'UTC Date',
  `platform` VARCHAR(20) NOT NULL COMMENT 'Cloud platform',
  `cc_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of the CC',
  `project_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of the project',
  `env_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'ID of the environment',
  `farm_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'ID of the farm',
  `cost` DECIMAL(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'daily usage',
  PRIMARY KEY (`date`, `farm_id`, `platform`, `cc_id`, `project_id`),
  INDEX `idx_farm_id` (`farm_id` ASC),
  INDEX `idx_project_id` (`project_id` ASC),
  INDEX `idx_cc_id` (`cc_id` ASC),
  INDEX `idx_platform` (`platform` ASC),
  INDEX `idx_env_id` (`env_id` ASC)
) ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Daily usage';

-- -----------------------------------------------------
-- Table `settings`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` VARCHAR(64) NOT NULL COMMENT 'setting ID',
  `value` TEXT NULL COMMENT 'The value',
  PRIMARY KEY (`id`))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'system settings';

-- -----------------------------------------------------
-- Table `quarterly_budget`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `quarterly_budget` (
  `year` SMALLINT NOT NULL COMMENT 'The year [2014]',
  `subject_type` TINYINT NOT NULL COMMENT '1 - CC, 2 - Project',
  `subject_id` BINARY(16) NOT NULL COMMENT 'ID of the CC or Project',
  `quarter` TINYINT NOT NULL COMMENT 'Quarter [1-4]',
  `budget` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Budget dollar amount',
  `final` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Final spent',
  `spentondate` DATETIME NULL COMMENT 'Final spent on date',
  `cumulativespend` DECIMAL(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'Cumulative spend',
  PRIMARY KEY (`year`, `subject_type`, `subject_id`, `quarter`),
  INDEX `idx_year` (`year` ASC, `quarter` ASC),
  INDEX `idx_quarter` (`quarter` ASC),
  INDEX `idx_subject_type` (`subject_type` ASC, `subject_id` ASC),
  INDEX `idx_subject_id` (`subject_id` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Quarterly budget';

-- -----------------------------------------------------
-- Table `nm_usage_d`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `nm_usage_d` (
  `date` DATE NOT NULL COMMENT 'UTC Date',
  `platform` VARCHAR(20) NOT NULL COMMENT 'Cloud platform',
  `cc_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'ID of Cost centre',
  `env_id` INT(11) NOT NULL COMMENT 'ID of Environment',
  `cost` DECIMAL(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'Daily usage',
  PRIMARY KEY (`date`, `platform`, `cc_id`, `env_id`),
  INDEX `idx_cc_id` (`cc_id` ASC),
  INDEX `idx_env_id` (`env_id` ASC),
  INDEX `idx_platform` (`platform` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Not managed daily usage';

-- -----------------------------------------------------
-- Table `notifications`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `uuid` BINARY(16) NOT NULL COMMENT 'unique identifier',
  `subject_type` TINYINT NOT NULL COMMENT '1- CC, 2 - Project',
  `notification_type` TINYINT NOT NULL COMMENT 'Type of the notification',
  `threshold` DECIMAL(12,2) NOT NULL,
  `recipient_type` TINYINT NOT NULL DEFAULT 1 COMMENT '1 - Leads 2 - Emails',
  `emails` TEXT NULL COMMENT 'Comma separated recipients',
  PRIMARY KEY (`uuid`),
  INDEX `idx_notification_type` (`notification_type` ASC),
  INDEX `idx_subject_type` (`subject_type` ASC),
  INDEX `idx_recipient_type` (`recipient_type` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Notifications';

-- -----------------------------------------------------
-- Table `reports`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `reports` (
  `uuid` BINARY(16) NOT NULL COMMENT 'unique identifier',
  `subject_type` TINYINT NULL COMMENT '1- CC, 2 - Project, NULL - Summary',
  `subject_id` BINARY(16) NULL,
  `period` TINYINT NOT NULL COMMENT 'Period',
  `emails` TEXT NOT NULL COMMENT 'Comma separated recipients',
  PRIMARY KEY (`uuid`),
  INDEX `idx_subject_type` (`subject_type` ASC),
  INDEX `idx_period` (`period` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Reports';

-- -----------------------------------------------------
-- Table `timeline_events`
-- -----------------------------------------------------
CREATE  TABLE IF NOT EXISTS `timeline_events` (
  `uuid` BINARY(16) NOT NULL COMMENT 'UUID' ,
  `event_type` TINYINT UNSIGNED NOT NULL COMMENT 'The type of the event' ,
  `dtime` DATETIME NOT NULL COMMENT 'The time of the event' ,
  `user_id` INT(11) NULL COMMENT 'User who triggered this event' ,
  `account_id` int(11) NULL COMMENT 'Account that triggered this event' ,
  `env_id` int(11) NULL COMMENT 'Enviroment that triggered this event' ,
  `description` TEXT NOT NULL COMMENT 'Description' ,
  PRIMARY KEY (`uuid`) ,
  INDEX `idx_dtime` (`dtime` ASC) ,
  INDEX `idx_event_type` (`event_type` ASC) ,
  INDEX `idx_env_id` (`env_id` ASC) ,
  INDEX `idx_account_id` (`account_id` ASC) ,
  INDEX `idx_user_id` (`user_id` ASC)
) ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Timeline events';

-- -----------------------------------------------------
-- Table `timeline_event_ccs`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `timeline_event_ccs` (
  `event_id` BINARY(16) NOT NULL COMMENT 'timeline_events.uuid reference',
  `cc_id` BINARY(16) NOT NULL COMMENT 'scalr.ccs.cc_id reference',
  PRIMARY KEY (`event_id`, `cc_id`),
  INDEX `idx_cc_id` (`cc_id` ASC),
  CONSTRAINT `fk_2af56955167b`
    FOREIGN KEY (`event_id`)
    REFERENCES `timeline_events` (`uuid`)
    ON DELETE CASCADE)
ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `timeline_event_projects`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `timeline_event_projects` (
  `event_id` BINARY(16) NOT NULL COMMENT 'timeline_events.uuid ref',
  `project_id` BINARY(16) NOT NULL COMMENT 'scalr.projects.project_id ref',
  PRIMARY KEY (`event_id`, `project_id`),
  INDEX `idx_project_id` (`project_id` ASC),
  CONSTRAINT `fk_e0325ab740c9`
    FOREIGN KEY (`event_id`)
    REFERENCES `timeline_events` (`uuid`)
    ON DELETE CASCADE)
ENGINE = InnoDB DEFAULT CHARSET=utf8;

-- -----------------------------------------------------
-- Table `report_payloads`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `report_payloads` (
  `uuid` BINARY(16) NOT NULL COMMENT 'UUID',
  `created` DATETIME NOT NULL COMMENT 'Creation timestamp (UTC)',
  `secret` BINARY(20) NOT NULL COMMENT 'Secret hash (SHA1)',
  `payload` MEDIUMTEXT NOT NULL COMMENT 'Payload',
  PRIMARY KEY (`uuid`),
  INDEX `idx_created` (`created` ASC))
ENGINE = InnoDB DEFAULT CHARSET=utf8
COMMENT = 'Report payloads';

-- -----------------------------------------------------
-- Data for table `tags`
-- -----------------------------------------------------
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (1, 'Environment');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (2, 'Platform');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (3, 'Role');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (4, 'Farm');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (5, 'Farm role');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (6, 'User');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (7, 'Role behavior');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (8, 'Cost centre');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (9, 'Project');
INSERT IGNORE INTO `tags` (`tag_id`, `name`) VALUES (10, 'Farm owner');

INSERT IGNORE INTO `settings` (`id`, `value`) VALUES ('budget_days', '["01-01","04-01","07-01","10-01"]');

EOL;
}