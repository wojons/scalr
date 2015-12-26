<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150819062557 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '613f4cbc-bcfa-46b4-8137-9b6c9df18c65';

    protected $depends = [];

    protected $description = 'Drop unused tables and columns';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function isApplied1($stage)
    {
        return !$this->hasTable('farm_role_options');
    }

    protected function run1($stage)
    {
        $this->console->out('Drop table farm_role_options');
        $this->db->Execute('DROP TABLE farm_role_options');
    }

    protected function isApplied2($stage)
    {
        return !$this->hasTable('role_parameters');
    }

    protected function run2($stage)
    {
        $this->console->out('Drop table role_parameters');
        $this->db->Execute('DROP TABLE role_parameters');
    }

    protected function isApplied3()
    {
        return !$this->hasTableColumn('farm_roles', 'new_role_id');
    }

    protected function run3()
    {
        $this->console->out('Drop column new_role_id from farm_roles');
        $this->db->Execute('ALTER TABLE farm_roles DROP new_role_id');
    }

    protected function isApplied4()
    {
        return !$this->hasTableColumn('servers', 'replace_server_id');
    }

    protected function run4()
    {
        $this->console->out('Drop column replace_server_id from servers');
        $this->db->Execute('ALTER TABLE servers DROP replace_server_id');
    }
}
