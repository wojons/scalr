<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150513142633 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '97a62c28-15ec-48e9-97ad-fe62279acba8';

    protected $depends = [];

    protected $description = '`farm_role_cloud_services` table changes';

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
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out("Changing `farm_role_cloud_services`.`farm_role_id` field");
        $this->db->Execute("ALTER TABLE `farm_role_cloud_services` CHANGE `farm_role_id` `farm_role_id` INT(11) NULL DEFAULT NULL");
    }

    protected function isApplied2($stage)
    {
        return $this->hasTableForeignKey('fk_farm_role_cloud_services_farms', 'farm_role_cloud_services');
    }

    protected function validateBefore2($stage)
    {
        return true;
    }

    protected function run2($stage)
    {
        $this->console->out("Creating `farm_role_cloud_services`.`farm_id` FOREIGN KEY");
        $this->db->BeginTrans();
        $this->db->Execute("
            DELETE FROM farm_role_cloud_services
            WHERE NOT EXISTS (
                SELECT 1 FROM farms
                WHERE farms.id = farm_role_cloud_services.farm_id
            )
        ");
        $this->db->Execute("
            ALTER TABLE `farm_role_cloud_services`
                ADD CONSTRAINT `fk_farm_role_cloud_services_farms` FOREIGN KEY (`farm_id`)
                REFERENCES `farms` (`id`) ON DELETE CASCADE
        ");
        $this->db->CommitTrans();
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableForeignKey('fk_farm_role_cloud_services_environments', 'farm_role_cloud_services');
    }

    protected function validateBefore3($stage)
    {
        return true;
    }

    protected function run3($stage)
    {
        $this->console->out("Creating `farm_role_cloud_services`.`env_id` FOREIGN KEY");
        $this->db->BeginTrans();
        $this->db->Execute("
            DELETE FROM farm_role_cloud_services
            WHERE NOT EXISTS (
                SELECT 1 FROM client_environments
                WHERE client_environments.id = farm_role_cloud_services.env_id
            )
        ");
        $this->db->Execute("
            ALTER TABLE `farm_role_cloud_services`
                ADD CONSTRAINT `fk_farm_role_cloud_services_environments` FOREIGN KEY (`env_id`)
                REFERENCES `client_environments` (`id`) ON DELETE CASCADE
        ");
        $this->db->CommitTrans();
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return true;
    }

    protected function run4($stage)
    {
        $this->console->out("Changing `farm_role_cloud_services` PRIMARY KEY");
        $this->db->BeginTrans();
        $this->db->execute("UPDATE farm_role_cloud_services SET cloud_location = '' WHERE cloud_location IS NULL");
        $this->db->execute("UPDATE farm_role_cloud_services SET platform = '' WHERE platform IS NULL");
        $this->db->execute("
            ALTER TABLE `farm_role_cloud_services`
                CHANGE COLUMN `id` `id` VARCHAR(255) NOT NULL,
                CHANGE COLUMN `platform` `platform` VARCHAR(36) NOT NULL,
                CHANGE COLUMN `cloud_location` `cloud_location` VARCHAR(36) NOT NULL,
                DROP PRIMARY KEY,
                ADD PRIMARY KEY (`id`, `env_id`, `platform`, `cloud_location`) ;
        ");
        $this->db->CommitTrans();
    }
}