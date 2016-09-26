<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160120023319 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '350ef94a-6a11-459d-94fb-65a8d5877721';

    protected $depends = [];

    protected $description = 'Remove legacy tables';

    protected $dbservice = 'adodb';

    private $tablesForRemoval = [
        'billing_packages',
        'nameservers',
        'farm_event_observers',
        'farm_event_observers_config',
        'countries',
        'subscriptions',
        'servers_stats'
    ];

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        foreach ($this->tablesForRemoval as $table) {
            if ($this->hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    protected function run1($stage)
    {
        foreach ($this->tablesForRemoval as $table) {
            if ($this->hasTable($table)) {
                $this->console->out("Removing %s table...", $table);
                $this->db->Execute("DROP TABLE `{$table}`");
            }
        }
    }
}