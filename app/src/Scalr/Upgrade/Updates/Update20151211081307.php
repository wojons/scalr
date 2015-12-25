<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151211081307 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '7cd00ba2-9ace-461d-a532-902f0ee81b49';

    protected $depends = [];

    protected $description = 'Adds ssh_keys.farm_id foreign key';

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1()
    {
        return $this->hasTableIndex('ssh_keys', 'idx_farm_id');
    }

    protected function run1()
    {
        if ($this->hasTableIndex('ssh_keys', 'fk_ssh_keys_farms_id')) {
            $this->db->Execute("ALTER TABLE `ssh_keys` DROP INDEX `fk_ssh_keys_farms_id`");
        }

        $this->db->Execute("ALTER TABLE `ssh_keys` ADD INDEX `idx_farm_id`(`farm_id`)");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableForeignKey('fk_ssh_keys_farms_id', 'ssh_keys');
    }

    protected function run2($stage)
    {
        $this->db->Execute("
            UPDATE `ssh_keys` s
            LEFT JOIN `farms` f ON f.`id` = s.`farm_id`
            SET s.`farm_id` = NULL
            WHERE f.`id` IS NULL
        ");

        $this->db->Execute("
            ALTER TABLE `ssh_keys` ADD CONSTRAINT `fk_ssh_keys_farms_id`
            FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION
        ");
    }
}
