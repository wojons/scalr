<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140520095636 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '4c1e857b-4559-4215-bedb-f663ad9c87b1';

    protected $depends = array('e40ca96b-9582-4e24-8d40-2c6a166ede0e');

    protected $description = 'Optimize table ssh_keys';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 6;
    }

    protected function isApplied1($stage)
    {
        return !$this->hasTableColumn('ssh_keys', 'client_id');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('ssh_keys');
    }

    protected function run1($stage)
    {
        $this->db->Execute('ALTER TABLE ssh_keys DROP COLUMN client_id');
    }

    protected function isApplied2($stage)
    {
        return !$this->hasTableIndex('ssh_keys', 'farmid');
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('ssh_keys');
    }

    protected function run2($stage)
    {
        $this->db->Execute('ALTER TABLE ssh_keys DROP INDEX farmid');
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableIndex('ssh_keys', 'idx_platform');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTable('ssh_keys');
    }

    protected function run3($stage)
    {
        $this->db->Execute('ALTER TABLE `ssh_keys` ADD INDEX `idx_platform` (`platform`, `type`, `env_id`, `farm_id`, `cloud_location`, `cloud_key_name`)');
    }

    protected function isApplied4()
    {
        return $this->hasTableForeignKey('fk_ssh_keys_client_environments_id', 'ssh_keys');
    }

    protected function validateBefore4()
    {
        return $this->hasTable('ssh_keys') && $this->hasTableColumn('ssh_keys', 'env_id');
    }

    protected function run4()
    {
        $this->db->Execute('DELETE ssh_keys FROM ssh_keys LEFT JOIN client_environments ce ON ce.id = ssh_keys.env_id WHERE ce.id IS NULL');
        $this->console->out('Removed %d old keys by envId', $this->db->Affected_Rows());
        $this->db->Execute('ALTER TABLE ssh_keys ADD CONSTRAINT `fk_ssh_keys_client_environments_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
    }

    protected function isApplied5()
    {
        return $this->hasTableForeignKey('fk_ssh_keys_farms_id', 'ssh_keys');
    }

    protected function validateBefore5()
    {
        return $this->hasTable('ssh_keys') && $this->hasTableColumn('ssh_keys', 'farm_id');
    }

    protected function run5()
    {
        $this->db->Execute('UPDATE ssh_keys SET farm_id = NULL WHERE farm_id = 0');
        $this->db->Execute('DELETE ssh_keys FROM ssh_keys LEFT JOIN farms f ON f.id = ssh_keys.farm_id WHERE f.id IS NULL AND ssh_keys.farm_id IS NOT NULL');
        $this->console->out('Removed %d old keys by farmId', $this->db->Affected_Rows());
        $this->db->Execute('ALTER TABLE ssh_keys ADD CONSTRAINT `fk_ssh_keys_farms_id` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
    }

    protected function isApplied6()
    {
        return !$this->hasTable('global_variables');
    }

    protected function validateBefore6()
    {
        return $this->hasTable('global_variables');
    }

    protected function run6()
    {
        $this->console->out('Remove old table global_variables');
        $this->db->Execute('DROP TABLE global_variables');
    }
}
