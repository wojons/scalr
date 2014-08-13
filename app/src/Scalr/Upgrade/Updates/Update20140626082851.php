<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140626082851 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '7913f6f5-0276-48ee-b688-3c93151e1663';

    protected $depends = [];

    protected $description = 'Global orchestration db';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 5;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('account_scripts');
    }

    protected function run1($stage)
    {
        $this->db->Execute("CREATE TABLE `account_scripts` (
            `id` int(11) NOT NULL,
            `account_id` int(11) DEFAULT NULL,
            `event_name` varchar(50) DEFAULT NULL,
            `target` varchar(15) DEFAULT NULL,
            `script_id` int(11) DEFAULT NULL,
            `version` varchar(10) DEFAULT NULL,
            `timeout` int(5) DEFAULT NULL,
            `issync` tinyint(1) DEFAULT NULL,
            `params` text,
            `order_index` int(11) NOT NULL DEFAULT '0',
            `script_path` varchar(255) DEFAULT NULL,
            `run_as` varchar(15) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `role_id` (`account_id`),
            KEY `script_id` (`script_id`),
            CONSTRAINT `account_scripts_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
           ) ENGINE=InnoDB DEFAULT CHARSET=latin1
        ");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableColumn('farm_role_scripts', 'ami_id');
    }

    protected function run2($stage)
    {
        $this->db->Execute("ALTER TABLE `farm_role_scripts` DROP `ami_id`;");
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableForeignKey('fk_farm_role_scripts_farm_roles_id', 'farm_role_scripts');
    }

    protected function run3($stage)
    {
        $this->db->Execute("DELETE FROM farm_role_scripts WHERE farm_roleid NOT IN (SELECT id FROM farm_roles)");

        $this->db->Execute("ALTER TABLE `farm_role_scripts` ADD CONSTRAINT `fk_farm_role_scripts_farm_roles_id`
           FOREIGN KEY (`farm_roleid`) REFERENCES `farm_roles`(`id`) ON DELETE CASCADE ON UPDATE RESTRICT;");
    }

    protected function isApplied4($stage)
    {
        return $this->hasTableAutoIncrement('account_scripts');
    }

    protected function run4($stage)
    {
        $this->db->Execute("ALTER TABLE `account_scripts` CHANGE COLUMN `id` `id` INT(11) NOT NULL AUTO_INCREMENT");
    }

    protected function isApplied5($stage)
    {
        return $this->hasTable('servers_launch_timelog');
    }

    protected function run5($stage)
    {
        $this->console->out('Adding servers_launch_timelog table...');
        $this->db->Execute("
            CREATE TABLE `servers_launch_timelog` (
            `server_id` varchar(36) NULL,
            `os_family` varchar(15) NULL,
            `os_version` varchar(10) NULL,
            `cloud` varchar(10) NULL,
            `cloud_location` varchar(36) NULL,
            `server_type` varchar(36) NULL,
            `behaviors` varchar(255) NULL,
            `ts_created` int(11) NULL,
            `ts_launched` int(11) NULL,
            `ts_hi` int(11) NULL,
            `ts_bhu` int(11) NULL,
            `ts_hu` int(11) NULL,
            `time_to_boot` int(5) NULL,
            `time_to_hi` int(5) NULL,
            `last_init_status` varchar(25) NULL,
            PRIMARY KEY (`server_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8
        ");
    }

}