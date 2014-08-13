<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

/**
 * Creates cost centre and project tables
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @version  5.0 (09.01.2014)
 */
class Update20140109132900 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'b6accbac-5201-4e59-ad67-2b2e907453d6';

    protected $depends = array();

    protected $description = 'Creating ccs and projects tables';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('projects') &&
               $this->hasTable('project_properties') &&
               $this->hasTable('ccs') &&
               $this->hasTable('cc_properties');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        try {
            foreach (preg_split('/;/', $this->sqlscript) as $stmt) {
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
        return $this->hasTableColumn('projects', 'shared') &&
               $this->hasTableColumn('projects', 'created_by_id') &&
               $this->hasTableColumn('projects', 'env_id') &&
               $this->hasTableIndex('projects', 'idx_env_id') &&
               $this->hasTableIndex('projects', 'idx_created_by_id') &&
               $this->hasTableIndex('projects', 'idx_shared');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('projects');
    }

    protected function run2($stage)
    {
        $this->console->out("Changing default charset for projects and ccs tables...");
        $this->db->Execute("ALTER TABLE `ccs` CONVERT TO CHARACTER SET utf8");
        $this->db->Execute("ALTER TABLE `projects` CONVERT TO CHARACTER SET utf8");
        $this->db->Execute("ALTER TABLE `cc_properties` CONVERT TO CHARACTER SET utf8");
        $this->db->Execute("ALTER TABLE `project_properties` CONVERT TO CHARACTER SET utf8");

        $this->console->out("Adding new columns to projects table...");
        $this->db->Execute("
            ALTER TABLE `projects`
                ADD COLUMN `shared` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Type of the share',
                ADD COLUMN `env_id` int(11) DEFAULT NULL COMMENT 'Associated environment',
                ADD COLUMN `created_by_id` int(11) DEFAULT NULL COMMENT 'Id of the creator',
                ADD INDEX `idx_env_id` (`env_id`),
                ADD INDEX `idx_created_by_id` (`created_by_id`, `shared`),
                ADD INDEX `idx_shared` (`shared`)
        ");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableColumn('ccs', 'archived') &&
               $this->hasTableColumn('projects', 'archived');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTableColumn('ccs', 'account_id') &&
               $this->hasTableColumn('projects', 'created_by_id');
    }

    protected function run3($stage)
    {
        $this->console->out("Adding archived column to both ccs and projects tables...");

        if (!$this->hasTableColumn('ccs', 'archived')) {
            $this->db->Execute("
                ALTER TABLE `ccs`
                    ADD COLUMN `archived` tinyint(1) NOT NULL DEFAULT '0'
                    COMMENT 'Whether it is archived'
                    AFTER `account_id`
            ");
        }

        if (!$this->hasTableColumn('projects', 'archived')) {
            $this->db->Execute("
                ALTER TABLE `projects`
                    ADD COLUMN `archived` tinyint(1) NOT NULL DEFAULT '0'
                    COMMENT 'Whether it is archived'
                    AFTER `created_by_id`
            ");
        }
    }

    protected function isApplied4($stage)
    {
        return $this->hasTableColumn('ccs', 'created_by_id') &&
               $this->hasTableColumn('ccs', 'created_by_email') &&
               $this->hasTableColumn('ccs', 'created') &&
               $this->hasTableIndex('ccs', 'idx_created_by_id') &&
               $this->hasTableColumn('projects', 'created_by_email') &&
               $this->hasTableColumn('projects', 'created')
        ;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('ccs') &&
               $this->hasTableColumn('ccs', 'account_id');
    }

    protected function run4($stage)
    {
        $stmt = '';

        if (!$this->hasTableColumn('ccs', 'created_by_id')) {
            $stmt .= ", ADD COLUMN `created_by_id` int(11) DEFAULT NULL COMMENT 'Id of the creator' AFTER `account_id`";
        }

        if (!$this->hasTableColumn('ccs', 'created_by_email')) {
            $stmt .= ", ADD COLUMN `created_by_email` varchar(255) DEFAULT NULL COMMENT 'Email of the creator' AFTER `created_by_id`";
        }

        if (!$this->hasTableColumn('ccs', 'created')) {
            $stmt .= ", ADD COLUMN `created` datetime NOT NULL COMMENT 'Creation timestamp (UTC)' AFTER `created_by_email`";
        }

        if (!$this->hasTableIndex('ccs', 'idx_created_by_id')) {
            $stmt .= ", ADD INDEX `idx_created_by_id` (`created_by_id`)";
        }

        if ($stmt !== '') {
            $this->console->out("Adding a new columns to ccs table...");

            $this->db->Execute("
                ALTER TABLE `ccs` " . ltrim($stmt, ',') . "
            ");
        }

        $stmt = '';

        if (!$this->hasTableColumn('projects', 'created_by_email')) {
            $stmt .= ", ADD COLUMN `created_by_email` varchar(255) DEFAULT NULL COMMENT 'Email of the creator' AFTER `created_by_id`";
        }

        if (!$this->hasTableColumn('projects', 'created')) {
            $stmt .= ", ADD COLUMN `created` datetime NOT NULL COMMENT 'Creation timestamp (UTC)' AFTER `created_by_email`";
        }

        if ($stmt !== '') {
            $this->console->out("Adding a new columns to projects table...");

            $this->db->Execute("
                ALTER TABLE `projects` " . ltrim($stmt, ',') . "
            ");
        }
    }

    private $sqlscript = <<<EOL
CREATE TABLE IF NOT EXISTS `ccs` (
  `cc_id` binary(16) NOT NULL COMMENT 'ID of the cost centre',
  `name` varchar(255) NOT NULL COMMENT 'The name',
  `account_id` int(11) DEFAULT NULL COMMENT 'clients.id reference',
  `created_by_id` int(11) DEFAULT NULL COMMENT 'Id of the creator',
  `created_by_email` varchar(255) DEFAULT NULL COMMENT 'Email of the creator',
  `created` datetime NOT NULL COMMENT 'Creation timestamp (UTC)',
  `archived` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether it is archived',
  PRIMARY KEY (`cc_id`),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_name` (`name`(3)),
  KEY `idx_created_by_id` (`created_by_id`),
  CONSTRAINT `fk_ccs_clients` FOREIGN KEY (`account_id`)
    REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Cost Centers';

CREATE TABLE IF NOT EXISTS `cc_properties` (
  `cc_id` binary(16) NOT NULL COMMENT 'ccs.cc_id reference',
  `name` varchar(64) NOT NULL COMMENT 'Name of the property',
  `value` text COMMENT 'The value',
  PRIMARY KEY (`cc_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='CC properties';

CREATE TABLE IF NOT EXISTS `projects` (
  `project_id` binary(16) NOT NULL COMMENT 'ID of the project',
  `cc_id` binary(16) NOT NULL COMMENT 'ccs.cc_id reference',
  `name` varchar(255) NOT NULL COMMENT 'The name',
  `account_id` int(11) DEFAULT NULL COMMENT 'clients.id reference',
  `shared` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Type of the share',
  `env_id` int(11) DEFAULT NULL COMMENT 'Associated environment',
  `created_by_id` int(11) DEFAULT NULL COMMENT 'Id of the creator',
  `created_by_email` varchar(255) DEFAULT NULL COMMENT 'Email of the creator',
  `created` datetime NOT NULL COMMENT 'Creation timestamp (UTC)',
  `archived` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether it is archived',
  PRIMARY KEY (`project_id`),
  KEY `idx_name` (`name`(3)),
  KEY `idx_account_id` (`account_id`),
  KEY `idx_cc_id` (`cc_id`),
  KEY `idx_env_id` (`env_id`),
  KEY `idx_created_by_id` (`created_by_id`, `shared`),
  KEY `idx_shared` (`shared`),
  CONSTRAINT `fk_projects_ccs` FOREIGN KEY (`cc_id`)
    REFERENCES `ccs` (`cc_id`),
  CONSTRAINT `fk_projects_clients` FOREIGN KEY (`account_id`)
    REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_projects_client_environments` FOREIGN KEY (`env_id`)
    REFERENCES `client_environments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Projects';

CREATE TABLE IF NOT EXISTS `project_properties` (
  `project_id` binary(16) NOT NULL COMMENT 'projects.project_id reference',
  `name` varchar(64) NOT NULL COMMENT 'Name of the property',
  `value` text COMMENT 'The value',
  PRIMARY KEY (`project_id`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Project properties';

EOL;
}