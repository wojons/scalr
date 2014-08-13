<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20131218143206 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'f7f5b00a-b0a4-467b-940e-6e8d20304758';

    protected $depends = array(
        '8cd24661-9b31-4056-89e4-465d0f07f4dd'
    );

    protected $description = 'Remove old deprecated tables.';

    protected $ignoreChanges = true;

    /**
     * Gets the list of tables needs to be marked to remove
     *
     * @return array
     */
    private function _getTableList()
    {
        return array(
            'account_alerts',
            'account_audit',
            'account_group_permissions',
            'account_groups',
            'account_user_groups',
            'aws_errors',
            'aws_regions',
            'config',
            'debug_pm',
            'debug_rackspace',
            'debug_ui',
            'farm_stats',
            'garbage_queue',
            'global_variables_backup',
            'init_tokens',
            'ipaccess',
            'instances_history',
            'payment_redirects',
            'real_servers',
            'rebundle_log',
            'sensor_data',
            'storage_backup_configs',
            'wus_info',
        );
    }


    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        $ret = true;
        foreach ($this->_getTableList() as $table) {
            if ($this->hasTable($table)) {
                $ret = false;
                break;
            }
        }
        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::validateBefore()
     */
    public function validateBefore($stage = null)
    {
        return true;
    }

    protected function run1($stage)
    {
        foreach ($this->_getTableList() as $table) {
            if ($this->hasTable($table)) {
                $this->console->out("Renaming table '%s' to 'backup_%s'", $table, $table);
                $this->db->Execute("RENAME TABLE `" . $table . "` TO `backup_" . $table . "`");
            }
        }
    }

    protected function isApplied2($stage)
    {
        $ret = true;
        foreach ($this->_getTableList() as $table) {
            if ($this->hasTable('backup_' . $table)) {
                $ret = false;
                break;
            }
        }
        return $ret;
    }

    protected function run2($stage)
    {
        foreach ($this->_getTableList() as $table) {
            if ($this->hasTable('backup_' . $table)) {
                $this->console->out("Dropping deprecated table 'backup_%s'", $table);
                $this->db->Execute("DROP TABLE `backup_" . $table . "`");
            }
        }
    }
}