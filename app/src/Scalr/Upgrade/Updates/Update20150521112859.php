<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150521112859 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '6cf7b6db-08a1-4452-9a4e-6ed849ae4db6';

    protected $depends = [];

    protected $description = "Adding indexes to `scripts`, `role_scripts`, `script_versions` for filtering";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    protected $sql = [];

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 12;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableCompatibleIndex('scripts', ['dt_created']);
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('scripts') &&
               $this->hasTableColumn('scripts', 'dt_created') &&
               !$this->hasTableIndex('scripts', 'idx_dt_created');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding index `idx_dt_created` to `scripts` table");

        $this->sql[] = "ADD INDEX `idx_dt_created` (`dt_created` ASC)";
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableCompatibleIndex('scripts', ['name']);
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('scripts') &&
               $this->hasTableColumn('scripts', 'name') &&
               !$this->hasTableIndex('scripts', 'idx_name');
    }

    protected function run2($stage)
    {
        $this->console->out("Adding index `idx_name` to `scripts` table");

        $this->sql[] = "ADD INDEX `idx_name` (`name`(8) ASC)";
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableCompatibleIndex('scripts', ['os']);
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('scripts') &&
               $this->hasTableColumn('scripts', 'os') &&
               !$this->hasTableIndex('scripts', 'idx_os');
    }

    protected function run3($stage)
    {
        $this->console->out("Adding index `idx_os` to `scripts` table");

        $this->sql[] = "ADD INDEX `idx_os` (`os` ASC)";
    }

    protected function isApplied4($stage)
    {
        return $this->hasTableCompatibleIndex('scripts', ['is_sync']);
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('scripts') &&
               $this->hasTableColumn('scripts', 'is_sync') &&
               !$this->hasTableIndex('scripts', 'idx_blocking');
    }

    protected function run4($stage)
    {
        $this->console->out("Adding index `idx_blocking` to `scripts` table");

        $this->sql[] = "ADD INDEX `idx_blocking` (`is_sync` ASC)";
    }

    protected function isApplied5($stage)
    {
        return empty($this->sql);
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('scripts');
    }

    protected function run5($stage)
    {
        $this->console->out("Apply changes to `scripts` table");

        $this->applyChanges('scripts', $this->sql);

        $this->sql = [];
    }

    protected function isApplied6($stage)
    {
        return $this->hasTableCompatibleIndex('script_versions', ['dt_created']);
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTable('script_versions') &&
               $this->hasTableColumn('script_versions', 'dt_created') &&
               !$this->hasTableIndex('script_versions', 'idx_dt_created');
    }

    protected function run6($stage)
    {
        $this->console->out("Adding index `idx_dt_created` to `script_versions` table");

        $this->sql[] = "ADD INDEX `idx_dt_created` (`dt_created` ASC)";
    }

    protected function isApplied7($stage)
    {
        return empty($this->sql);
    }

    protected function validateBefore7($stage)
    {
        return $this->hasTable('script_versions');
    }

    protected function run7($stage)
    {
        $this->console->out("Apply changes to `script_versions` table");

        $this->applyChanges('script_versions', $this->sql);

        $this->sql = [];
    }

    protected function isApplied8($stage)
    {
        return $this->hasTableCompatibleIndex('role_scripts', ['event_name']);
    }

    protected function validateBefore8($stage)
    {
        return $this->hasTable('role_scripts') &&
               $this->hasTableColumn('role_scripts', 'event_name') &&
               !$this->hasTableIndex('role_scripts', 'idx_event_name');
    }

    protected function run8($stage)
    {
        $this->console->out("Adding index `idx_event_name` to `role_scripts` table");

        $this->sql[] = "ADD INDEX `idx_event_name` (`event_name`(8) ASC)";
    }

    protected function isApplied9($stage)
    {
        return $this->hasTableCompatibleIndex('role_scripts', ['target']);
    }

    protected function validateBefore9($stage)
    {
        return $this->hasTable('role_scripts') &&
               $this->hasTableColumn('role_scripts', 'target') &&
               !$this->hasTableIndex('role_scripts', 'idx_target');
    }

    protected function run9($stage)
    {
        $this->console->out("Adding index `idx_target` to `role_scripts` table");

        $this->sql[] = "ADD INDEX `idx_target` (`target`(4) ASC)";
    }

    protected function isApplied10($stage)
    {
        return $this->hasTableCompatibleIndex('role_scripts', ['issync']);
    }

    protected function validateBefore10($stage)
    {
        return $this->hasTable('role_scripts') &&
               $this->hasTableColumn('role_scripts', 'issync') &&
               !$this->hasTableIndex('role_scripts', 'idx_blocking');
    }

    protected function run10($stage)
    {
        $this->console->out("Adding index `idx_blocking` to `role_scripts` table");

        $this->sql[] = "ADD INDEX `idx_blocking` (`issync` ASC)";
    }

    protected function isApplied11($stage)
    {
        return $this->hasTableCompatibleIndex('role_scripts', ['order_index']);
    }

    protected function validateBefore11($stage)
    {
        return $this->hasTable('role_scripts') &&
               $this->hasTableColumn('role_scripts', 'order_index') &&
               !$this->hasTableIndex('role_scripts', 'idx_order');
    }

    protected function run11($stage)
    {
        $this->console->out("Adding index `idx_order` to `role_scripts` table");

        $this->sql[] = "ADD INDEX `idx_order` (`order_index` ASC)";
    }

    protected function isApplied12($stage)
    {
        return empty($this->sql);
    }

    protected function validateBefore12($stage)
    {
        return $this->hasTable('role_scripts');
    }

    protected function run12($stage)
    {
        $this->console->out("Apply changes to `role_scripts` table");

        $this->applyChanges('role_scripts', $this->sql);

        $this->sql = [];
    }
}