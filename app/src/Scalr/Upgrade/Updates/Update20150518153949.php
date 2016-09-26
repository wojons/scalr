<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Stats\CostAnalytics\Entity\UsageTypeEntity;
use Scalr\Stats\CostAnalytics\Entity\UsageItemEntity;

class Update20150518153949 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2be402c1-5e1e-4c89-a1ed-241f55a34638';

    protected $depends = [];

    protected $description = "Cost analytics - AWS Detailed billing upgrade";

    protected $ignoreChanges = true;

    protected $dbservice = 'cadb';

    /**
     * Initialized Usage Items
     *
     * @var array
     */
    private $usageItems;

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
        return 11;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('usage_types');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `usage_types` (
                `id` BINARY(4) NOT NULL,
                `cost_distr_type` TINYINT(4) NOT NULL COMMENT 'Cost distribution type',
                `name` VARCHAR(255) NOT NULL COMMENT 'The type of the usage',
                `display_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Display name',
                PRIMARY KEY (`id`),
                UNIQUE INDEX `unique_key` (`cost_distr_type` ASC, `name` ASC)
            ) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8
            COMMENT = 'Usage types'
        ");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTable('usage_items');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('usage_types');
    }

    protected function run2($stage)
    {
        $this->db->Execute("
            CREATE TABLE IF NOT EXISTS `usage_items` (
                `id` BINARY(4) NOT NULL,
                `usage_type` BINARY(4) NOT NULL COMMENT 'usage_types.id ref',
                `name` VARCHAR(255) NOT NULL COMMENT 'Item name',
                `display_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Display name',
                PRIMARY KEY (`id`),
                UNIQUE INDEX `unique_key` (`usage_type` ASC, `name` ASC),
                INDEX `idx_usage_type` (`usage_type` ASC),
                CONSTRAINT `fk_2d27e26ab76a` FOREIGN KEY (`usage_type`) REFERENCES `usage_types` (`id`)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            ) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8
            COMMENT = 'Usage items'
        ");
    }

    protected function isApplied3($stage)
    {
        $this->loadUsageItems();

        return !$this->hasTableColumn('farm_usage_d', 'instance_type');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('usage_types') && $this->hasTable('usage_items');
    }

    protected function run3($stage)
    {
        $this->console->out("Querying existing instance types from statistics...");

        $rs = $this->db->Execute("
            SELECT DISTINCT instance_type FROM usage_h
            UNION
            SELECT DISTINCT instance_type FROM farm_usage_d
        ");

        $this->console->out("Initializing cost distribution items...");

        foreach ($rs as $row) {
            $this->initUsageItem($row['instance_type']);
        }
    }

    /**
     * Preloads existing Usage Items
     */
    private function loadUsageItems()
    {
        if (empty($this->usageItems)) {
            $this->usageItems = [];

            foreach (UsageTypeEntity::all() as $usageType) {
                /* @var $usageType UsageTypeEntity */
                $this->usageItems[$usageType->costDistrType][$usageType->name]['_type_'] = $usageType;
                foreach ($usageType->getUsageItems() as $usageItem) {
                    /* @var $usageItem UsageItemEntity */
                    $this->usageItems[$usageType->costDistrType][$usageType->name][$usageItem->name] = $usageItem;
                }
            }
        }
    }

    /**
     * Initializes UsageItem
     *
     * @param    string    $item         The name of the Usage Item
     * @param    string    $type         optional The name of the Usage Type
     * @param    int       $costDistType optional The cost distribution type
     */
    private function initUsageItem($item, $type = UsageTypeEntity::NAME_COMPUTE_BOX_USAGE, $costDistType = UsageTypeEntity::COST_DISTR_TYPE_COMPUTE)
    {
        if (!isset($this->usageItems[$costDistType][$type][$item])) {
            if (!isset($this->usageItems[$costDistType][$type]['_type_'])) {
                //Finds Usage Type entity by unique key
                $usageType = UsageTypeEntity::findOne([['costDistrType' => $costDistType], ['name' => $type]]);

                if ($usageType === null) {
                    $usageType = new UsageTypeEntity();
                    $usageType->costDistrType = $costDistType;
                    $usageType->name = $type;

                    if ($type == UsageTypeEntity::NAME_COMPUTE_BOX_USAGE) {
                        $usageType->displayName = 'Compute instances';
                    }

                    $usageType->save();
                }

                $this->usageItems[$costDistType][$type]['_type_'] = $usageType;
            }

            $usageType = $this->usageItems[$costDistType][$type]['_type_'];

            //Finds UsageItem entity by unique key
            $usageItem = UsageItemEntity::findOne([['usageType' => $usageType->id], ['name' => $item]]);

            if ($usageItem === null) {
                $usageItem = new UsageItemEntity();
                $usageItem->usageType = $usageType->id;
                $usageItem->name = $item;
                $usageItem->save();
            }

            $this->usageItems[$costDistType][$type][$item] = $usageItem;
        }
    }

    /**
     * Gets Usage Item by name
     *
     * It's actual only for BoxUsage Compute cost distribution type.
     * They should be initialized in previous step.
     *
     * @param    string    $name   The name of the Usage Item
     * @return   UsageItemEntity   Returns Usage Item Entity for the specified name
     */
    private function getUsageItem($name)
    {
        $this->initUsageItem($name);

        return $this->usageItems[UsageTypeEntity::COST_DISTR_TYPE_COMPUTE][UsageTypeEntity::NAME_COMPUTE_BOX_USAGE][$name];
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('usage_h') && $this->hasTableColumn('usage_h', 'cloud_location');
    }

    protected function run4($stage)
    {
        $changes = [];

        if (!$this->hasTableColumn('usage_h', 'usage_item')) {
            $this->console->out("Adding usage_item column to usage_h table...");
            $changes[] = "ADD COLUMN `usage_item` BINARY(4) NOT NULL COMMENT 'usage_items ref' AFTER `cloud_location`";
        }

        if (!$this->hasTableIndex('usage_h', 'idx_usage_item')) {
            $this->console->out("Adding idx_usage_item index to usage_h table...");
            $changes[] = "ADD INDEX `idx_usage_item` (`usage_item` ASC)";
        }

        if (!$this->hasTableColumnType('usage_h', 'num', 'decimal(8,2) unsigned')) {
            $this->console->out("Changing type of the num column of usage_h table to decimal(8,2) ...");
            $changes[] = "CHANGE COLUMN `num` `num` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT 'Usage quantity'";
        }

        if (!$this->hasTableColumnType('usage_h', 'cost', 'decimal(18,9)')) {
            $this->console->out("Changing type of the cost column of usage_h table to decimal(18,9) ...");
            $changes[] = "CHANGE COLUMN `cost` `cost` DECIMAL(18,9) NOT NULL DEFAULT 0.000000000 COMMENT 'Cost of usage'";
        }

        if (!empty($changes))
            $this->applyChanges('usage_h', $changes);
    }

    protected function isApplied5($stage)
    {
        return false;
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('farm_usage_d') && $this->hasTableColumn('farm_usage_d', 'farm_role_id');
    }

    protected function run5($stage)
    {
        $changes = [];

        if (!$this->hasTableColumn('farm_usage_d', 'usage_item')) {
            $this->console->out("Adding usage_item column to farm_usage_d table...");
            $changes[] = "ADD COLUMN `usage_item` BINARY(4) NOT NULL COMMENT 'usage_items.id ref' AFTER `farm_role_id`";
        }

        if (!$this->hasTableIndex('farm_usage_d', 'idx_usage_item')) {
            $this->console->out("Adding idx_usage_item index to farm_usage_d table...");
            $changes[] = "ADD INDEX `idx_usage_item` (`usage_item` ASC)";
        }

        if ($this->hasTableColumn('farm_usage_d', 'instance_hours') && !$this->hasTableColumnType('farm_usage_d', 'usage_hours', 'decimal(8,2) unsigned')) {
            $this->console->out("Renaming column name from instance_hours to usage_hours of the farm_usage_d table and changing its type to decimal(8,2) ...");
            $changes[] = "CHANGE COLUMN `instance_hours` `usage_hours` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT 'Total usage/hours for day'";
        }

        if ($this->hasTableColumn('farm_usage_d', 'min_instances') && !$this->hasTableColumnType('farm_usage_d', 'min_usage', 'decimal(8,2) unsigned')) {
            $this->console->out("Renaming column name from min_instances to min_usage of the farm_usage_d table and changing its type to decimal(8,2) ...");
            $changes[] = "CHANGE COLUMN `min_instances` `min_usage` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT 'min usage quantity'";
        }

        if ($this->hasTableColumn('farm_usage_d', 'max_instances') && !$this->hasTableColumnType('farm_usage_d', 'max_usage', 'decimal(8,2) unsigned')) {
            $this->console->out("Renaming column name from max_instances to max_usage of the farm_usage_d table and changing its type to decimal(8,2) ...");
            $changes[] = "CHANGE COLUMN `max_instances` `max_usage` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT 'max usage quantity'";
        }

        if (!$this->hasTableColumnType('farm_usage_d', 'cost', 'decimal(18,9)')) {
            $this->console->out("Changing type of the cost column of farm_usage_d table to decimal(18,9) ...");
            $changes[] = "CHANGE COLUMN `cost` `cost` DECIMAL(18,9) NOT NULL DEFAULT 0.000000000 COMMENT 'Total cost of the usage'";
        }

        if (!$this->hasTableColumnType('farm_usage_d', 'working_hours', 'tinyint(3) unsigned')) {
            $this->console->out("Changing type of the working_hours column of farm_usage_d table to tinyint(3) ...");
            $changes[] = "CHANGE COLUMN `working_hours` `working_hours` TINYINT(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'hours when farm is running'";
        }

        if (!empty($changes))
            $this->applyChanges('farm_usage_d', $changes);
    }

    protected function isApplied6($stage)
    {
        //If there is usage_h.instance_type column it's considered to haven't been produced yet.
        return !$this->hasTableColumn('usage_h', 'instance_type');
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTableColumn('usage_h', 'usage_item');
    }

    protected function run6($stage)
    {
        $this->console->out("Converting usage_h data initializing values for the new usage_item column...");

        foreach ($this->db->Execute("SELECT DISTINCT instance_type FROM usage_h") as $row) {
            //Gets related usage item
            $usageItem = $this->getUsageItem($row['instance_type']);

            //Sets usage_h.usage_item value accordingly
            $this->db->Execute("
                UPDATE `usage_h`
                SET `usage_item` = " . $usageItem->qstr('id') . "
                WHERE instance_type = ?
            ", [ $row['instance_type'] ]);
        }
    }

    protected function isApplied7($stage)
    {
        return !$this->hasTableColumn('farm_usage_d', 'instance_type');
    }

    protected function validateBefore7($stage)
    {
        return $this->hasTableColumn('farm_usage_d', 'usage_item');
    }

    protected function run7($stage)
    {
        $this->console->out("Converting farm_usage_d data initializing values for the new usage_item column...");

        foreach ($this->db->Execute("SELECT DISTINCT instance_type FROM farm_usage_d") as $row) {
            //Gets related usage item
            $usageItem = $this->getUsageItem($row['instance_type']);

            //Sets usage_h.usage_item value accordingly
            $this->db->Execute("
                UPDATE `farm_usage_d`
                SET `usage_item` = " . $usageItem->qstr('id') . "
                WHERE instance_type = ?
            ", [ $row['instance_type'] ]);
        }
    }

    protected function isApplied8($stage)
    {
        return $this->hasTableForeignKey('fk_75b88915ce5d', 'usage_h');
    }

    protected function validateBefore8($stage)
    {
        return $this->hasTable('usage_h') &&
               $this->hasTableColumn('usage_h', 'usage_item') &&
               $this->hasTableColumn('usage_items', 'id');
    }

    protected function run8($stage)
    {
        if (!$this->hasTableForeignKey('fk_75b88915ce5d', 'usage_h')) {
            $this->console->out("Adding foreign key for usage_h.usage_item column...");

            $this->db->Execute("
                ALTER TABLE `usage_h`
                ADD CONSTRAINT `fk_75b88915ce5d`
                  FOREIGN KEY (`usage_item`)
                  REFERENCES `usage_items` (`id`)
                  ON DELETE RESTRICT
                  ON UPDATE RESTRICT
            ");
        }
    }

    protected function isApplied9($stage)
    {
        return false !== $this->hasTableCompatibleIndex('farm_usage_d', ['account_id', 'farm_role_id', 'usage_item', 'cc_id', 'project_id', 'date'], true);
    }

    protected function validateBefore9($stage)
    {
        return $this->hasTable('farm_usage_d');
    }

    protected function run9($stage)
    {
        $this->console->out("Modifying primary key of farm_usage_d table...");
        $changes = ["DROP PRIMARY KEY, ADD PRIMARY KEY (`account_id`, `farm_role_id`, `usage_item`, `cc_id`, `project_id`, `date`)"];
        $this->applyChanges('farm_usage_d', $changes);
    }

    protected function isApplied10($stage)
    {
        return !$this->hasTableColumn('usage_h', 'instance_type') &&
               !$this->hasTableIndex('usage_h', 'idx_instance_type') &&
               !$this->hasTableColumn('farm_usage_d', 'instance_type') &&
               !$this->hasTableIndex('farm_usage_d', 'idx_instance_type');
    }

    protected function validateBefore10($stage)
    {
        return $this->hasTable('usage_h');
    }

    protected function run10($stage)
    {
        $changes = [];

        if ($this->hasTableColumn('usage_h', 'instance_type')) {
            $this->console->out("Removing instance_type column from usage_h table...");
            $changes[] = "DROP COLUMN `instance_type`";
        }

        if ($this->hasTableIndex('usage_h', 'idx_instance_type')) {
            $this->console->out("Removing idx_instance_type index from usage_h table...");
            $changes[] = "DROP INDEX `idx_instance_type`";
        }

        $this->applyChanges('usage_h', $changes);

        $changes = [];

        if ($this->hasTableColumn('farm_usage_d', 'instance_type')) {
            $this->console->out("Removing instance_type column from farm_usage_d table...");
            $changes[] = "DROP COLUMN `instance_type`";
        }

        if ($this->hasTableIndex('farm_usage_d', 'idx_instance_type')) {
            $this->console->out("Removing idx_instance_type index from farm_usage_d table...");
            $changes[] = "DROP INDEX `idx_instance_type`";
        }

        $this->applyChanges('farm_usage_d', $changes);
    }

    protected function isApplied11($stage)
    {
        return false;
    }

    protected function validateBefore11($stage)
    {
        return $this->hasTableColumn('usage_d', 'cost') &&
               $this->hasTableColumn('quarterly_budget', 'cumulativespend') &&
               $this->hasTableColumn('nm_usage_d', 'cost') &&
               $this->hasTableColumn('nm_usage_h', 'cost') &&
               $this->hasTableColumn('nm_usage_h', 'num');
    }

    protected function run11($stage)
    {
        if (!$this->hasTableColumnType('nm_usage_h', 'num', 'decimal(8,2) unsigned')) {
            $this->console->out("Changing type of the num column of nm_usage_h table to decimal(8,2) ...");
            $this->applyChanges('nm_usage_h', ["CHANGE COLUMN `num` `num` DECIMAL(8,2) UNSIGNED NOT NULL DEFAULT 0.00 COMMENT 'Usage quantity'"]);
        }

        if (!$this->hasTableColumnType('nm_usage_h', 'cost', 'decimal(18,9)')) {
            $this->console->out("Changing type of the cost column of nm_usage_h table to decimal(18,9) ...");
            $this->applyChanges('nm_usage_h', ["CHANGE COLUMN `cost` `cost` DECIMAL(18,9) NOT NULL DEFAULT 0.000000000 COMMENT 'The cost of the usage'"]);
        }

        if (!$this->hasTableColumnType('usage_d', 'cost', 'decimal(18,9)')) {
            $this->console->out("Changing type of the cost column of usage_d table to decimal(18,9) ...");
            $this->applyChanges('usage_d', ["CHANGE COLUMN `cost` `cost` DECIMAL(18,9) NOT NULL DEFAULT 0.000000000 COMMENT 'Daily usage'"]);
        }

        if (!$this->hasTableColumnType('quarterly_budget', 'cumulativespend', 'decimal(18,9)')) {
            $this->console->out("Changing type of the cumulativespend column of quarterly_budget table to decimal(18,9) ...");
            $this->applyChanges('quarterly_budget', ["CHANGE COLUMN `cumulativespend` `cumulativespend` DECIMAL(18,9) NOT NULL DEFAULT 0.000000000 COMMENT 'Cumulative spend'"]);
        }

        if (!$this->hasTableColumnType('nm_usage_d', 'cost', 'decimal(18,9)')) {
            $this->console->out("Changing type of the cost column of nm_usage_d table to decimal(18,9) ...");
            $this->applyChanges('nm_usage_d', ["CHANGE COLUMN `cost` `cost` DECIMAL(18,9) NOT NULL DEFAULT 0.000000000 COMMENT 'Daily usage'"]);
        }
    }
}