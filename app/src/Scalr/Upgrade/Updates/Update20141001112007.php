<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

/**
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.0  (01.10.2014)
 */
class Update20141001112007 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'bb01127f-99bb-40d7-8a32-d1ce44619a86';

    protected $depends = [];

    protected $description = "Create a new tables to store instance types";

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

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isApplied()
     */
    public function isApplied($stage = null)
    {
        $ret = false;

        switch ($stage) {
            case 1:
                $ret = $this->hasTable('cloud_locations') &&
                       $this->hasTable('cloud_instance_types');
                break;

            default:
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::validateBefore()
     */
    public function validateBefore($stage = null)
    {
        $ret = true;

        switch ($stage) {
            case 1:
            default:
                break;
        }

        return $ret;
    }

    protected function run1($stage)
    {
        if (!$this->hasTable('cloud_locations')) {
            $this->console->out("Creating cloud_locations table...");

            $this->db->Execute("
                CREATE TABLE IF NOT EXISTS `cloud_locations` (
                  `cloud_location_id` BINARY(16) NOT NULL COMMENT 'UUID',
                  `platform` VARCHAR(20) NOT NULL COMMENT 'Cloud platform',
                  `url` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Normalized endpoint url',
                  `cloud_location` VARCHAR(255) NOT NULL COMMENT 'Cloud location',
                  `updated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`cloud_location_id`),
                  UNIQUE INDEX `idx_unique` (`platform`, `url`, `cloud_location`)
                ) ENGINE=InnoDb
                DEFAULT CHARSET=utf8
                COMMENT = 'Known cloud locations for each platform'
            ");
        }

        if (!$this->hasTable('cloud_instance_types')) {
            $this->console->out("Creating cloud_instance_types table...");

            $this->db->Execute("
                CREATE TABLE IF NOT EXISTS `cloud_instance_types` (
                  `cloud_location_id` BINARY(16) NOT NULL COMMENT 'cloud_locations.cloud_location_id ref',
                  `instance_type_id` VARCHAR(45) NOT NULL COMMENT 'ID of the instance type',
                  `name` VARCHAR(255) NOT NULL COMMENT 'Display name',
                  `ram` VARCHAR(255) NOT NULL COMMENT 'Memory info',
                  `vcpus` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'CPU info',
                  `disk` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Disk info',
                  `type` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Storage type info',
                  `note` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Notes',
                  `options` TEXT NOT NULL COMMENT 'Json encoded options',
                  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '0-inactive, 1-active, 2-obsolete',
                  PRIMARY KEY (`cloud_location_id`, `instance_type_id`),
                  INDEX `idx_instance_type_id` (`instance_type_id`),
                  INDEX `idx_status` (`status`),
                  CONSTRAINT `fk_e3824ee2da38`
                    FOREIGN KEY (`cloud_location_id`)
                    REFERENCES `cloud_locations` (`cloud_location_id`)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
                ) ENGINE=InnoDb
                DEFAULT CHARSET=utf8
                COMMENT = 'Instance types for each cloud location'
            ");
        }
    }
}