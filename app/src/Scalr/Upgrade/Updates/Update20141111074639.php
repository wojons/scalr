<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141111074639 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '12570d4f-8cb6-43e0-b889-4229a8b5eb3a';

    protected $depends = [];

    protected $description = 'Drop fk_ssh_keys_farms_id foreign key from ssh_keys';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

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
        return !$this->hasTableForeignKey('fk_ssh_keys_farms_id', 'ssh_keys');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('ssh_keys');
    }

    protected function run1($stage)
    {
        $this->db->Execute("ALTER TABLE `ssh_keys` DROP FOREIGN KEY `fk_ssh_keys_farms_id`");
    }
}