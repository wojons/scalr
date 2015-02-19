<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Entity\TagEntity;

/**
 * Analytics 3 phase database updates
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @version  5.0 (25.08.2014)
 */
class Update20140825113229 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ab5a7b4d-4e49-4549-a13b-08d7e90a417b';

    protected $depends = ['2702d6db-faff-4fb2-8649-e90e4e700778'];

    protected $description = "Analytics database phase 3 upgrade";

    protected $ignoreChanges = true;

    protected $dbservice = 'cadb';

    private $envIdAfterCost;

    private $idxNameNoSubpart;

    private $usageReflection;

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
        return 6;
    }

    private function hasTagRole()
    {
        $tagRole = TagEntity::findPk(TagEntity::TAG_ID_ROLE);

        return $tagRole && strtolower($tagRole->name) == 'role';
    }

    private function hasTagRoleBehavior()
    {
        $tagRoleBehavior = TagEntity::findPk(TagEntity::TAG_ID_ROLE_BEHAVIOR);

        return $tagRoleBehavior && strtolower($tagRoleBehavior->name) == 'role behavior';
    }

    protected function isApplied1($stage)
    {
        $this->envIdAfterCost = ($this->getTableColumnDefinition('usage_d', 'env_id')->ordinalPosition -
                        $this->getTableColumnDefinition('usage_d', 'cost')->ordinalPosition) === 1;

        $this->idxNameNoSubpart = $this->db->getRow("SHOW INDEX FROM prices WHERE key_name = 'idx_name' AND sub_part IS NULL") ? true : false;

        return $this->hasTable('farm_usage_d') && $this->hasTableColumn('farm_usage_d', 'role_id') &&
               $this->hasTableColumn('usage_h', 'role_id') &&
               $this->hasTagRole() &&
               $this->hasTagRoleBehavior() &&
               $this->envIdAfterCost &&
               !$this->idxNameNoSubpart;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('usage_h');
    }

    protected function run1($stage)
    {
        if (!$this->hasTable('farm_usage_d')) {
            $this->console->out("Creating farm_usage_d table...");

            $this->db->Execute("
                CREATE TABLE IF NOT EXISTS `farm_usage_d` (
                  `account_id` INT(11) NOT NULL COMMENT 'scalr.clients.id ref',
                  `farm_role_id` INT(11) NOT NULL COMMENT 'scalr.farm_roles.id ref',
                  `instance_type` VARCHAR(45) NOT NULL COMMENT 'Type of the instance',
                  `cc_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'scalr.ccs.cc_id ref',
                  `project_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'scalr.projects.project_id ref',
                  `date` DATE NOT NULL COMMENT 'UTC Date',
                  `platform` VARCHAR(20) NOT NULL COMMENT 'cloud platform',
                  `cloud_location` VARCHAR(255) NOT NULL COMMENT 'cloud location',
                  `env_id` INT(11) NOT NULL COMMENT 'scalr.client_account_environments.id ref',
                  `farm_id` INT(11) NOT NULL COMMENT 'scalr.farms.id ref',
                  `role_id` INT(11) NOT NULL COMMENT 'scalr.roles.id ref',
                  `cost` DECIMAL(12,6) NOT NULL DEFAULT 0.000000 COMMENT 'total usage',
                  `min_instances` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'min instances count',
                  `max_instances` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'max instances count',
                  `instance_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'total instance hours',
                  `working_hours` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'hours when farm is running',
                  PRIMARY KEY (`account_id`, `farm_role_id`, `instance_type`, `cc_id`, `project_id`, `date`),
                  INDEX `idx_farm_role_id` (`farm_role_id` ASC),
                  INDEX `idx_instance_type` (`instance_type` ASC),
                  INDEX `idx_date` (`date` ASC),
                  INDEX `idx_farm_id` (`farm_id` ASC),
                  INDEX `idx_env_id` (`env_id` ASC),
                  INDEX `idx_cloud_location` (`cloud_location` ASC),
                  INDEX `idx_platform` (`platform` ASC),
                  INDEX `idx_role_id` (`role_id` ASC),
                  INDEX `idx_project_id` (`project_id` ASC))
                ENGINE = InnoDB
                COMMENT = 'Farm daily usage' PARTITION BY HASH(account_id) PARTITIONS 100
            ");
        }

        if ($this->idxNameNoSubpart) {
            $this->console->out("Modifying index idx_name of prices table...");

            $this->db->Execute("
                ALTER TABLE `prices`
                    DROP INDEX `idx_name` ,
                    ADD INDEX `idx_name` USING BTREE (`name`(3) ASC)
            ");
        }

        if (!$this->envIdAfterCost) {
            $this->console->out("Moving env_id column just after cost column for usage_d table...");

            $this->db->Execute("
                ALTER TABLE `usage_d`
                    CHANGE COLUMN `env_id` `env_id` INT(11) NOT NULL DEFAULT 0
                    COMMENT 'ID of the environment' AFTER `cost`
            ");
        }

        if (!$this->hasTableColumn('usage_h', 'role_id')) {
            $this->console->out("Adding role_id column to usage_h table...");

            $this->db->Execute("
                ALTER TABLE `usage_h`
                    ADD COLUMN `role_id` INT(11) NULL DEFAULT NULL COMMENT 'scalr.roles.id ref' AFTER `farm_role_id`,
                    ADD INDEX `idx_role` (`role_id` ASC)
            ");
        }

        if (!$this->hasTagRole()) {
            $this->console->out("Adding Role tag...");

            $tagRole = TagEntity::findPk(TagEntity::TAG_ID_ROLE) ?: new TagEntity();

            $tagRole->tagId = TagEntity::TAG_ID_ROLE;
            $tagRole->name = 'Role';

            $tagRole->save();
        }

        if (!$this->hasTagRoleBehavior()) {
            $this->console->out("Adding Role behavior tag...");

            $tagRoleBehavior = TagEntity::findPk(TagEntity::TAG_ID_ROLE_BEHAVIOR) ?: new TagEntity();

            $tagRoleBehavior->tagId = TagEntity::TAG_ID_ROLE_BEHAVIOR;
            $tagRoleBehavior->name = 'Role behavior';

            $tagRoleBehavior->save();
        }
    }


    protected function isApplied2($stage)
    {
        $db = \Scalr::getDb();

        $exists = $db->GetOne("
            SELECT s.server_id
            FROM servers s
            LEFT JOIN server_properties p ON p.server_id = s.server_id AND p.name = ?
            JOIN farm_roles fr ON s.farm_roleid = fr.id
            WHERE fr.role_id > 0 AND s.farm_roleid > 0
            AND (p.server_id IS NULL OR p.`value` IS NULL)
            LIMIT 1
        ", [\SERVER_PROPERTIES::ROLE_ID]);

        return !$exists;
    }

    protected function validateBefore2($stage)
    {
        $this->usageReflection = new \ReflectionClass('Scalr\\Stats\\CostAnalytics\\Usage');

        return $this->usageReflection->hasMethod('initServerProperties');
    }

    protected function run2($stage)
    {
        $this->console->out("Initializes farm.id and farm_role.id properties for all running servers...");

        $ref = $this->usageReflection->getMethod('initServerProperties');
        $ref->setAccessible(true);
        $ref->invoke(\Scalr::getContainer()->analytics->usage);
    }


    protected function isApplied3($stage)
    {
        $exists = $this->db->GetOne("SELECT EXISTS (SELECT 1 FROM account_tag_values WHERE tag_id = ?)", [TagEntity::TAG_ID_ROLE]);

        return $exists;
    }

    protected function run3($stage)
    {
        $this->console->out("Populating roles and theirs behaviors to the dictionary...");

        Update20140127154723::_updateRoleTags();
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function run4($stage)
    {
        $this->console->out("Populating farm roles to the dictionary...");

        Update20140127154723::_updateFarmRoleTags();
    }

    protected function isApplied5($stage)
    {
        return $this->hasTableColumn('farm_usage_d', 'project_id');
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('farm_usage_d') &&
               $this->hasTableColumn('farm_usage_d', 'instance_type');
    }

    protected function run5($stage)
    {
        $this->console->out("Adding project_id and cc_id columns to farm_usage_d table...");

        $this->db->Execute("
            ALTER TABLE `farm_usage_d`
                ADD COLUMN `cc_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'scalr.ccs.cc_id ref' AFTER `instance_type`,
                ADD COLUMN `project_id` BINARY(16) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0' COMMENT 'scalr.projects.project_id ref' AFTER `cc_id`,
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (`account_id`, `farm_role_id`, `instance_type`, `cc_id`, `project_id`, `date`),
                ADD INDEX `idx_project_id` (`project_id` ASC)
        ");


        //If there are no records does not need to initialize
        if (!$this->db->GetOne("SELECT EXISTS(SELECT 1 FROM `farm_usage_d`)"))
            return;

        // Initializing projects and cost centers where it is possible
        $db = \Scalr::getDb();

        $this->console->out("Initializing project identifiers...");

        $rs = $db->Execute("
            SELECT f.`clientid` `account_id`, s.`farmid` `farm_id`, s.`value` `project_id`
            FROM `farm_settings` s
            JOIN `farms` f ON f.`id` = s.`farmid`
            WHERE s.`name` = 'project_id'
        ");

        while ($row = $rs->FetchRow()) {
            if (empty($row['project_id']))
                continue;

            $this->db->Execute("
                UPDATE `farm_usage_d`
                SET project_id = UNHEX(?)
                WHERE `account_id` = ?
                AND `farm_id` = ?
                AND project_id = '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0'
            ", [
                str_replace('-', '', $row['project_id']),
                $row['account_id'],
                $row['farm_id'],
            ]);
        }

        unset($rs);

        $this->console->out("Initializing cost center identifiers...");

        $rs = $db->Execute("
            SELECT e.`client_id` `account_id`, p.`env_id`, p.`value` cc_id
            FROM `client_environment_properties` p
            JOIN `client_environments` e ON e.`id` = p.`env_id`
            WHERE p.`name` = 'cc_id'
        ");

        while ($row = $rs->FetchRow()) {
            if (empty($row['cc_id']))
                continue;

            $this->db->Execute("
                UPDATE `farm_usage_d`
                SET cc_id = UNHEX(?)
                WHERE `account_id` = ?
                AND env_id = ?
                AND cc_id = '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0'
            ", [
                str_replace('-', '', $row['cc_id']),
                $row['account_id'],
                $row['env_id'],
            ]);
        }
    }

    protected function isApplied6($stage)
    {
        $exists = $this->db->GetOne("SELECT EXISTS (SELECT 1 FROM account_tag_values WHERE tag_id = ?)", [TagEntity::TAG_ID_FARM_OWNER]);

        return $exists;
    }

    protected function run6($stage)
    {
        $this->console->out("Populating farm owners to the dictionary...");

        Update20140127154723::_updateFarmOwnerTags();
    }
}