<?php

namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150612121802 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '07ccdf6e-e37f-4da0-af19-df963f00f825';

    protected $depends = [];

    protected $description = 'Update services_ssl_cert table. Add foreign key to environments.';

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private $sql = [];

    /**
     * {@inheritdoc}
     *
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 5;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTableForeignKey('fk_94f84469e7e0ee97 ', 'services_ssl_certs');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('services_ssl_certs');
    }

    protected function run1($stage)
    {
        $this->console->out('Delete broken records.');

        $this->db->Execute("
            DELETE FROM `services_ssl_certs`
            WHERE `env_id` > 0 AND NOT EXISTS (
                SELECT 1 FROM `client_environments`
                WHERE `client_environments`.`id` = `services_ssl_certs`.`env_id`
            )
        ");
        $affected = $this->db->Affected_Rows();
        $this->console->out("Deleted {$affected} outdated certificates.");
    }

    protected function isApplied2($stage)
    {
        return ('NO' == $this->getTableColumnDefinition('services_ssl_certs', 'ssl_pkey')->isNullable) &&
            ('NO' == $this->getTableColumnDefinition('services_ssl_certs', 'ssl_cert')->isNullable);
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTableColumn('services_ssl_certs', 'ssl_pkey') &&
            $this->hasTableColumn('services_ssl_certs', 'ssl_cert');
    }

    protected function run2($stage)
    {
        $this->console->out('Change columns `services_ssl_certs`.`ssl_pkey` and `services_ssl_certs`.`ssl_cert` to NOT NULL.');
        $this->sql[] = "CHANGE `ssl_pkey` `ssl_pkey` TEXT NOT NULL, CHANGE `ssl_cert` `ssl_cert` TEXT NOT NULL";
    }

    protected function isApplied3($stage)
    {
        return $this->hasTableIndex('services_ssl_certs', 'idx_env_id');
    }

    protected function validateBefore3($stage)
    {
        return $this->hasTableColumn('services_ssl_certs', 'env_id');
    }

    protected function run3($stage)
    {
        $this->console->out('Adding index for env_id.');

        $this->sql[] = "ADD INDEX `idx_env_id` (`env_id`)";
    }

    protected function isApplied4($stage)
    {
        return $this->hasTableForeignKey('fk_94f84469e7e0ee97 ', 'services_ssl_certs');
    }

    protected function validateBefore4($stage)
    {
        return $this->hasTable('services_ssl_certs') &&
            $this->hasTable('client_environments');
    }

    protected function run4($stage)
    {
        $this->console->out('Adding foreign key to environments.');

        $this->sql[] = "ADD CONSTRAINT `fk_94f84469e7e0ee97`
            FOREIGN KEY (`env_id`)
            REFERENCES `client_environments` (`id`)
            ON DELETE CASCADE";
    }

    protected function isApplied5($stage)
    {
        return !count($this->sql);
    }

    protected function validateBefore5($stage)
    {
        return $this->hasTable('services_ssl_certs') &&
            $this->hasTable('client_environments');
    }

    protected function run5($stage)
    {
        $this->console->out('Applying changes.');

        $this->applyChanges('services_ssl_certs', $this->sql);
    }
}
