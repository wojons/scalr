<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20160314142849 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '43c6dac3-820b-4e95-8bdf-ca18a98b0262';

    protected $depends = [];

    protected $description = 'Restores FK in the analytics.managed table';

    protected $dbservice = 'cadb';

    /**
     * {@inheritdoc}
     * @see \Scalr\Upgrade\AbstractUpdate::isRefused()
     */
    public function isRefused()
    {
        return !$this->container->analytics->enabled ? "Cost analytics is turned off" : false;
    }

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
        return $this->hasTableForeignKey('fk_managed_poller_sessions', 'managed');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('managed');
    }

    protected function run1($stage)
    {
        $this->console->out("Creating managed_tmp table...");
        $this->db->Execute("CREATE TABLE managed_tmp LIKE managed");

        $this->console->out("Applying changes to managed_tmp table...");
        $this->db->Execute("
            ALTER TABLE managed_tmp
            ADD CONSTRAINT `fk_managed_poller_sessions` FOREIGN KEY (`sid`)
                REFERENCES `poller_sessions` (`sid`) 
                ON DELETE CASCADE
        ");

        $this->console->out("Swapping table names...");
        $this->db->Execute("RENAME TABLE managed TO managed_backup, managed_tmp TO managed");

        $this->console->out("Copying actual data...");
        $this->db->Execute("
            INSERT IGNORE INTO managed
            SELECT mb.* FROM managed_backup mb
            JOIN poller_sessions ps USING(sid)
        ");

        $this->console->out("Dropping managed_tmp table...");
        $this->db->Execute("DROP TABLE IF EXISTS managed_backup");
    }
}
