<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150828115905 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '599893ae-befa-4fbc-a948-42aefe2da62e';

    protected $depends = [];

    protected $description = 'Change env to environment in variables and account_variables tables; replace empty string in enum with off';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 7;
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `variables` MODIFY COLUMN `flag_required` varchar(32) NOT NULL DEFAULT ''");
        $this->db->Execute("UPDATE `variables` SET flag_required = ? WHERE flag_required = ?", ['environment', 'env']);
        $this->db->Execute("UPDATE `variables` SET flag_required = ? WHERE flag_required = ?", ['off', '']);
        $this->db->Execute("ALTER TABLE `variables` MODIFY COLUMN `flag_required` enum('off','account','environment','role','farm','farmrole') NOT NULL DEFAULT 'off'");
    }

    protected function run2($stage)
    {
        $this->db->Execute("ALTER TABLE `account_variables` MODIFY COLUMN `flag_required` varchar(32) NOT NULL DEFAULT ''");
        $this->db->Execute("UPDATE `account_variables` SET flag_required = ? WHERE flag_required = ?", ['environment', 'env']);
        $this->db->Execute("UPDATE `account_variables` SET flag_required = ? WHERE flag_required = ?", ['off', '']);
        $this->db->Execute("ALTER TABLE `account_variables` MODIFY COLUMN `flag_required` enum('off','environment','role','farm','farmrole') NOT NULL DEFAULT 'off'");
    }

    protected function run3()
    {
        $this->db->Execute("ALTER TABLE `client_environment_variables` MODIFY COLUMN `flag_required` varchar(32) NOT NULL DEFAULT ''");
        $this->db->Execute("UPDATE `client_environment_variables` SET flag_required = ? WHERE flag_required = ?", ['off', '']);
        $this->db->Execute("ALTER TABLE `client_environment_variables` MODIFY COLUMN `flag_required` enum('off','role','farm','farmrole') NOT NULL DEFAULT 'off'");
    }

    protected function run4()
    {
        $this->db->Execute("ALTER TABLE `farm_variables` MODIFY COLUMN `flag_required` varchar(32) NOT NULL DEFAULT ''");
        $this->db->Execute("UPDATE `farm_variables` SET flag_required = ? WHERE flag_required = ?", ['off', '']);
        $this->db->Execute("ALTER TABLE `farm_variables` MODIFY COLUMN `flag_required` enum('off','farmrole') NOT NULL DEFAULT 'off'");
    }

    protected function run5()
    {
        $this->db->Execute("ALTER TABLE `role_variables` MODIFY COLUMN `flag_required` varchar(32) NOT NULL DEFAULT ''");
        $this->db->Execute("UPDATE `role_variables` SET flag_required = ? WHERE flag_required = ?", ['off', '']);
        $this->db->Execute("ALTER TABLE `role_variables` MODIFY COLUMN `flag_required` enum('off','farmrole') NOT NULL DEFAULT 'off'");
    }

    protected function run6()
    {
        $this->db->Execute("ALTER TABLE `farm_role_variables` MODIFY COLUMN `flag_required` varchar(32) NOT NULL DEFAULT ''");
        $this->db->Execute("UPDATE `farm_role_variables` SET flag_required = ? WHERE flag_required = ?", ['off', '']);
        $this->db->Execute("ALTER TABLE `farm_role_variables` MODIFY COLUMN `flag_required` enum('off') NOT NULL DEFAULT 'off'");
    }

    protected function run7()
    {
        $this->db->Execute("ALTER TABLE `server_variables` MODIFY COLUMN `flag_required` varchar(32) NOT NULL DEFAULT ''");
        $this->db->Execute("UPDATE `server_variables` SET flag_required = ? WHERE flag_required = ?", ['off', '']);
        $this->db->Execute("ALTER TABLE `server_variables` MODIFY COLUMN `flag_required` enum('off') NOT NULL DEFAULT 'off'");
    }
}
