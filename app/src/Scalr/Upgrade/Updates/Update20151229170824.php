<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151229170824 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'c803be12-ae46-11e5-bf7f-feff819cdc9f';

    protected $depends = [];

    protected $description = 'Add field last_data to table farm_role_scaling_metrics. Change calc_function in avg for BandWidth sensor';

    protected $dbservice = 'adodb';

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
        return $this->hasTableColumn('farm_role_scaling_metrics', 'last_data');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out('Creating field last_data');

        $this->db->Execute("
            ALTER TABLE farm_role_scaling_metrics ADD `last_data` text DEFAULT NULL
        ");
    }

    protected function isApplied2($stage)
    {
        $found = $this->db->GetOne("SELECT * FROM scaling_metrics WHERE name = ? AND calc_function = ? LIMIT 1", array('BandWidth', 'avg'));
        return $found !== null;
    }

    protected function validateBefore2($stage)
    {
        return true;
    }

    protected function run2($stage)
    {
        $this->console->out('Updating calc_function for BandWidth sensor');
        $this->db->Execute("UPDATE scaling_metrics SET calc_function = ? WHERE name = ?", array('avg', 'BandWidth'));
    }
}