<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140701120143 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '598caaf6-9860-4633-9081-b8adb6c8b5ed';

    protected $depends = [];

    protected $description = 'Add script_type to orchestration tables';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 6;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableColumn('farm_role_scripts', 'script_type');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Adding farm_role_scripts script_type");
        $this->db->Execute("ALTER TABLE `farm_role_scripts` ADD COLUMN `script_type` ENUM('local', 'scalr', 'chef') DEFAULT 'scalr'");
    }

    protected function isApplied2($stage)
    {
        return false;
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('farm_role_scripts', 'script_type');
    }

    protected function run2($stage)
    {
        $this->console->out("Updating farm_role_scripts script_type");
        $this->db->Execute("UPDATE `farm_role_scripts` SET `script_type` = 'local' WHERE script_path IS NOT NULL");
    }

    /*role_scripts*/
    protected function isApplied3($stage)
    {
        return $this->hasTableColumn('role_scripts', 'script_type');
    }

    protected function validateBefore3($stage)
    {
        return true;
    }

    protected function run3($stage)
    {
        $this->console->out("Adding role_scripts script_type");
        $this->db->Execute("ALTER TABLE `role_scripts` ADD COLUMN `script_type` ENUM('local', 'scalr', 'chef') DEFAULT 'scalr'");
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTableColumn('role_scripts', 'script_type');
    }
    protected function run4($stage)
    {
        $this->console->out("Updating role_scripts script_type");
        $this->db->Execute("UPDATE `role_scripts` SET `script_type` = 'local' WHERE script_path IS NOT NULL");
    }

    protected function isApplied5($stage)
    {
        return $this->hasTableColumn('account_scripts', 'script_type');
    }

    protected function validateBefore5($stage)
    {
        return true;
    }

    protected function run5($stage)
    {
        $this->console->out("Adding account_scripts script_type");
        $this->db->Execute("ALTER TABLE `account_scripts` ADD COLUMN `script_type` ENUM('local', 'scalr', 'chef') DEFAULT 'scalr'");
    }

    protected function isApplied6($stage)
    {
        return false;
    }

    protected function validateBefore6($stage)
    {
        return $this->hasTableColumn('account_scripts', 'script_type');
    }

    protected function run6($stage)
    {
        $this->console->out("Updating account_scripts script_type");
        $this->db->Execute("UPDATE `account_scripts` SET `script_type` = 'local' WHERE script_path IS NOT NULL");
    }
}