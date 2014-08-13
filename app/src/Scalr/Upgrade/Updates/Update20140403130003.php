<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140403130003 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'f6dfb66e-a306-47ba-b49d-31dd4c196d22';

    protected $depends = array('cc7f0f71-f771-4840-96ec-7d6c68da9e8a');

    protected $description = 'Migrate scripts shortcuts to new table; refactor table scripts';

    protected $ignoreChanges = true;

    public function getNumberStages()
    {
        return 9;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('script_shortcuts');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out('Create new table script_shortcuts');
        $this->db->Execute("
            CREATE TABLE `script_shortcuts` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `script_id` int(11) NOT NULL,
              `farm_id` int(11) NOT NULL,
              `farm_role_id` int(11) DEFAULT NULL,
              `is_sync` tinyint(1) NOT NULL DEFAULT '0',
              `timeout` int(11) NOT NULL DEFAULT '0',
              `version` int(11) NOT NULL,
              `params` text NOT NULL,
              PRIMARY KEY (`id`),
              KEY `fk_script_shortcuts_scripts_id` (`script_id`),
              KEY `fk_script_shortcuts_farms_id` (`farm_id`),
              KEY `fk_script_shortcuts_farm_roles_id` (`farm_role_id`),
              CONSTRAINT `fk_script_shortcuts_farm_roles_id` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
              CONSTRAINT `fk_script_shortcuts_farms_id` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
              CONSTRAINT `fk_script_shortcuts_scripts_id` FOREIGN KEY (`script_id`) REFERENCES `scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB
        ");
    }

    protected function isApplied2()
    {
        return $this->hasTable('script_shortcuts') && $this->db->GetOne('SELECT COUNT(*) FROM script_shortcuts');
    }

    protected function run2()
    {
        $this->console->out('Fill shortcuts from farm_role_scripts');
        $rows = $this->db->GetAll('SELECT * FROM farm_role_scripts WHERE ismenuitem = 1');
        foreach ($rows as $row) {
            try {
                $this->db->Execute('INSERT INTO script_shortcuts (script_id, farm_id, farm_role_id, is_sync, timeout, version, params) VALUES(?,?,?,?,?,?,?)', array(
                    $row['scriptid'],
                    $row['farmid'],
                    $row['farm_roleid'] != 0 ? $row['farm_roleid'] : null,
                    $row['issync'] == 1 ? 1 : 0,
                    $row['timeout'],
                    $row['version'] == 'latest' ? -1 : intval($row['version']),
                    $row['params']
                ));
            } catch (\Exception $e) {
                $this->console->warning($e->getMessage());
            }
        }
    }

    protected function validateBefore3()
    {
        return $this->hasTableColumn('scripts', 'clientid');
    }

    protected function isApplied3()
    {
        return $this->hasTableColumn('scripts', 'account_id');
    }

    protected function run3()
    {
        $this->console->out('Rename clientid to account_id');
        $this->db->Execute('ALTER TABLE scripts DROP KEY clientid, CHANGE COLUMN `clientid` `account_id` int(11) NULL DEFAULT NULL, ADD INDEX idx_account_id(account_id)');
    }

    protected function validateBefore4()
    {
        return $this->hasTable('scripts');
    }

    protected function isApplied4()
    {
        return $this->hasTableForeignKey('fk_scripts_clients_id', 'scripts');
    }

    protected function run4()
    {
        $this->console->out('Make foreign key for scripts.account_id');
        $this->db->Execute('UPDATE scripts SET account_id = NULL WHERE account_id = 0');
        $this->db->Execute('ALTER TABLE scripts ADD CONSTRAINT `fk_scripts_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
    }

    protected function validateBefore5()
    {
        return $this->hasTableColumn('scripts', 'env_id');
    }

    protected function isApplied5()
    {
        return $this->hasTableForeignKey('fk_scripts_client_environments_id', 'scripts');
    }

    protected function run5()
    {
        $this->console->out('Make foreing key for scrips.env_id');
        $this->db->Execute('ALTER TABLE scripts DROP KEY env_id, MODIFY COLUMN `env_id` int(11) NULL DEFAULT NULL, ADD INDEX idx_env_id(env_id)');
        $this->db->Execute('UPDATE scripts SET env_id = NULL WHERE env_id = 0');
        $this->db->Execute('ALTER TABLE scripts ADD CONSTRAINT `fk_scripts_client_environments_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION');
    }

    protected function validateBefore6()
    {
        return $this->hasTable('scripts');
    }

    protected function isApplied6()
    {
        return $this->hasTableColumn('scripts', 'is_sync');
    }

    protected function run6()
    {
        $this->db->Execute("ALTER TABLE scripts CHANGE COLUMN `issync` `is_sync` tinyint(1) DEFAULT '0'");
    }

    protected function validateBefore7()
    {
        return $this->hasTable('scripts');
    }

    protected function isApplied7()
    {
        return $this->hasTableColumn('scripts', 'dt_created');
    }

    protected function run7()
    {
        $this->db->Execute("ALTER TABLE scripts CHANGE COLUMN `dtadded` `dt_created` datetime DEFAULT NULL");
    }

    protected function validateBefore8()
    {
        return $this->hasTable('scripts');
    }

    protected function isApplied8()
    {
        return $this->hasTableColumn('scripts', 'dt_changed');
    }

    protected function run8()
    {
        $this->db->Execute("ALTER TABLE scripts CHANGE COLUMN `dtchanged` `dt_changed` datetime DEFAULT NULL");
    }

    protected function validateBefore9()
    {
        return $this->hasTable('script_revisions');
    }

    protected function isApplied9()
    {
        return $this->hasTable('script_versions');
    }

    protected function run9()
    {
        $this->db->Execute("ALTER TABLE `script_revisions` DROP FOREIGN KEY `fk_script_revisions_scripts_id`,
            CHANGE `scriptid` `script_id` int(11) NOT NULL,
            ADD CONSTRAINT `fk_script_versions_scripts_id` FOREIGN KEY (`script_id`) REFERENCES `scripts` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
        ");

        $this->db->Execute('ALTER TABLE `script_revisions` DROP KEY `scriptid_revision`, DROP PRIMARY KEY,
            DROP COLUMN `id`,
            CHANGE `revision` `version` int(11) NOT NULL,
            CHANGE `script` `content` longtext NOT NULL,
            CHANGE `dtcreated` `dt_created` datetime NOT NULL,
            ADD PRIMARY KEY(`script_id`, `version`)
        ');

        $this->db->Execute("ALTER TABLE script_revisions RENAME TO `script_versions`");
    }
}
