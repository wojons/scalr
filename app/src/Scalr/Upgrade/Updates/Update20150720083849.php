<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150720083849 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'fd03928a-84f2-4d15-b8ee-552501551913';

    protected $depends = [];

    protected $description = 'Add foreign key by farm_id to bundle_tasks';

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
        return $this->hasTableForeignKey('bundle_tasks_farms_id ', 'bundle_tasks');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('bundle_tasks') &&
        $this->hasTable('farms');
    }

    protected function run1($stage)
    {
        $this->console->out('Adding foreign key to bundle_tasks.');

        $this->db->BeginTrans();
        try {
            $this->db->Execute("
                UPDATE `bundle_tasks` b
                    LEFT JOIN `farms` f ON f.id = b.farm_id
                    SET b.farm_id = NULL
                    WHERE b.farm_id > 0 AND f.id IS NULL
                ");

            $this->db->Execute("
                ALTER TABLE `bundle_tasks` ADD CONSTRAINT `bundle_tasks_farms_id`
                    FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`)
                    ON DELETE SET NULL
                    ON UPDATE NO ACTION
            ");

            $this->db->CommitTrans();
        } catch (\Exception $e) {
            $this->db->RollbackTrans();
            $this->console->warning($e->getMessage());
        }
    }
}
