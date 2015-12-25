<?php

namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150902141535 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '02803552-1cc9-4939-b1c6-611220b6d979';

    protected $depends = [];

    protected $description = 'Remove AI column `id` from `client_environment_properties`';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private $sql = [];

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
        return !$this->hasTableColumn('client_environment_properties', 'id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('client_environment_properties');
    }

    protected function run1($stage)
    {
        $this->console->out("Drop column `client_environment_property`.`id`");

        $this->sql[] = "DROP COLUMN `id`";
    }

    protected function isApplied2($stage)
    {
        $indexes = $this->hasTableCompatibleIndex('client_environment_properties', [ 'id' ], true);

        return !(is_array($indexes) ? in_array('PRIMARY', $indexes) : $indexes);
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableIndex('client_environment_properties', 'PRIMARY');
    }

    protected function run2($stage)
    {
        $this->console->out("Drop old PRIMARY KEY on table `client_environment_properties`");

        $this->sql[] = "DROP PRIMARY KEY";
    }

    protected function isApplied3($stage)
    {
        $indexes = $this->hasTableCompatibleIndex('client_environment_properties', [ 'env_id', 'name', 'group' ]);

        return is_array($indexes) ? in_array('PRIMARY', $indexes) : $indexes;
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTableColumn('client_environment_properties', 'env_id')
            && $this->hasTableColumn('client_environment_properties', 'name')
            && $this->hasTableColumn('client_environment_properties', 'group');
    }

    protected function run3($stage)
    {
        $this->console->out("Adds a new PRIMARY KEY on `env_id`, `name` and `group` columns.");

        $this->sql[] = "ADD PRIMARY KEY (`env_id`, `name`, `group`)";
    }

    protected function isApplied4($stage)
    {
        $indexes = $this->hasTableCompatibleIndex('client_environment_properties', [ 'env_id', 'name', 'group' ], true);

        return !(is_array($indexes) ? in_array('env_id_2', $indexes) : $indexes);
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTableIndex('client_environment_properties', 'env_id_2');
    }

    protected function run4($stage)
    {
        $this->console->out("Drop unnecessary index `env_id_2` from table `client_environment_properties`");

        $this->sql[] = "DROP INDEX `env_id_2`";
    }

    protected function isApplied5($stage)
    {
        return empty($this->sql);
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('client_environment_properties');
    }

    protected function run5($stage)
    {
        $this->console->out("Apply changes");

        $this->applyChanges('client_environment_properties', $this->sql);
    }
}