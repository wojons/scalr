<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20140305141430 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '1442807f-1b60-45fd-91c4-5d7e68121a75';

    protected $depends = array('ff8cbdda-28a3-4f3b-bcdf-1ab338d018b3');

    protected $description = 'Convert table global_variables to new structure';

    protected $ignoreChanges = true;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 2;
    }

    protected function isApplied1($stage)
    {
        return $this->hasTable('variables');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('global_variables');
    }

    protected function run1($stage)
    {
        $this->console->out('Create new structure');

        $this->db->Execute("
            CREATE TABLE `variables` (
              `name` varchar(50) NOT NULL,
              `value` text,
              `flag_final` tinyint(1) NOT NULL DEFAULT '0',
              `flag_required` enum('','account', 'env','role','farm','farmrole') NOT NULL DEFAULT '',
              `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
              `format` varchar(15) NOT NULL DEFAULT '',
              `validator` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY(name)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $this->db->Execute("
            CREATE TABLE `account_variables` (
              `account_id` INT(11) NOT NULL,
              `name` varchar(50) NOT NULL,
              `value` text,
              `flag_final` tinyint(1) NOT NULL DEFAULT '0',
              `flag_required` enum('', 'env','role','farm','farmrole') NOT NULL DEFAULT '',
              `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
              `format` varchar(15) NOT NULL DEFAULT '',
              `validator` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY(name, account_id),
              CONSTRAINT `fk_account_variables_clients_id` FOREIGN KEY (`account_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $this->db->Execute("
            CREATE TABLE `client_environment_variables` (
              `env_id` INT(11) NOT NULL,
              `name` varchar(50) NOT NULL,
              `value` text,
              `flag_final` tinyint(1) NOT NULL DEFAULT '0',
              `flag_required` enum('','role','farm','farmrole') NOT NULL DEFAULT '',
              `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
              `format` varchar(15) NOT NULL DEFAULT '',
              `validator` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY(name, env_id),
              CONSTRAINT `fk_client_env_variables_client_env_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $this->db->Execute("
            CREATE TABLE `role_variables` (
              `role_id` INT(11) NOT NULL,
              `name` varchar(50) NOT NULL,
              `value` text,
              `flag_final` tinyint(1) NOT NULL DEFAULT '0',
              `flag_required` enum('','farmrole') NOT NULL DEFAULT '',
              `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
              `format` varchar(15) NOT NULL DEFAULT '',
              `validator` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY(name, role_id),
              CONSTRAINT `fk_role_variables_roles_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $this->db->Execute("
            CREATE TABLE `farm_variables` (
              `farm_id` INT(11) NOT NULL,
              `name` varchar(50) NOT NULL,
              `value` text,
              `flag_final` tinyint(1) NOT NULL DEFAULT '0',
              `flag_required` enum('','farmrole') NOT NULL DEFAULT '',
              `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
              `format` varchar(15) NOT NULL DEFAULT '',
              `validator` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY(name, farm_id),
              CONSTRAINT `fk_farm_variables_farms_id` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $this->db->Execute("
            CREATE TABLE `farm_role_variables` (
              `farm_role_id` INT(11) NOT NULL,
              `name` varchar(50) NOT NULL,
              `value` text,
              `flag_final` tinyint(1) NOT NULL DEFAULT '0',
              `flag_required` enum('') NOT NULL DEFAULT '',
              `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
              `format` varchar(15) NOT NULL DEFAULT '',
              `validator` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY(name, farm_role_id),
              CONSTRAINT `fk_farm_role_variables_farm_roles_id` FOREIGN KEY (`farm_role_id`) REFERENCES `farm_roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");

        $this->db->Execute("
            CREATE TABLE `server_variables` (
              `server_id` varchar(36) NOT NULL,
              `name` varchar(50) NOT NULL,
              `value` text,
              `flag_final` tinyint(1) NOT NULL DEFAULT '0',
              `flag_required` enum('') NOT NULL DEFAULT '',
              `flag_hidden` tinyint(1) NOT NULL DEFAULT '0',
              `format` varchar(15) NOT NULL DEFAULT '',
              `validator` varchar(255) NOT NULL DEFAULT '',
              PRIMARY KEY(name, server_id),
              CONSTRAINT `fk_server_variables_servers_server_id` FOREIGN KEY (`server_id`) REFERENCES `servers` (`server_id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
        ");
    }

    protected function validateBefore2($stage)
    {
        return $this->hasTable('variables');
    }

    protected function run2($stage)
    {
        $this->console->out('Converting data');

        $stm = $this->db->Execute('SELECT * FROM global_variables');
        while ($vr = $stm->fetchRow()) {
            try {
                $data = array(
                    'name' => $vr['name'],
                    'value' => $vr['value']
                );
                $data['format'] = is_null($vr['format']) ? '' : $vr['format'];
                $data['validator'] = is_null($vr['validator']) ? '' : $vr['validator'];

                if ($vr['flag_final'] == 1)
                    $data['flag_final'] = 1;
                if ($vr['flag_required'] == 1)
                    $data['flag_required'] = 'farmrole';
                if ($vr['flag_hidden'] == 1)
                    $data['flag_hidden'] = 1;

                switch ($vr['scope']) {
                    case 'env':
                        $data['env_id'] = $vr['env_id'];
                        $this->db->Execute('INSERT INTO client_environment_variables (' . implode(',', array_keys($data)) . ') VALUES(' . implode(',', array_fill(0, count($data), '?')) . ' )', array_values($data));
                        break;
                    case 'role':
                        $data['role_id'] = $vr['role_id'];
                        $this->db->Execute('INSERT INTO role_variables (' . implode(',', array_keys($data)) . ') VALUES(' . implode(',', array_fill(0, count($data), '?')) . ' )', array_values($data));
                        break;
                    case 'farm':
                        $data['farm_id'] = $vr['farm_id'];
                        $this->db->Execute('INSERT INTO farm_variables (' . implode(',', array_keys($data)) . ') VALUES(' . implode(',', array_fill(0, count($data), '?')) . ' )', array_values($data));
                        break;
                    case 'farmrole':
                        $id = $this->db->GetOne('SELECT role_id FROM farm_roles WHERE id = ?', array($vr['farm_role_id']));
                        if ($id != $vr['role_id']) {
                            $this->console->warning(sprintf("Skip farmrole value: farm_id: %d, real role_id: %d, role_id: %d, farm_role_id: %d", $vr['farm_id'], $id, $vr['role_id'], $vr['farm_role_id']));
                        } else {
                            $data['farm_role_id'] = $vr['farm_role_id'];
                            $this->db->Execute('INSERT INTO farm_role_variables (' . implode(',', array_keys($data)) . ') VALUES(' . implode(',', array_fill(0, count($data), '?')) . ' )', array_values($data));
                        }
                        break;
                    case 'server':
                        $data['server_id'] = $vr['server_id'];
                        $this->db->Execute('INSERT INTO server_variables (' . implode(',', array_keys($data)) . ') VALUES(' . implode(',', array_fill(0, count($data), '?')) . ' )', array_values($data));
                        break;
                    default:
                        $this->console->warning('Wrong scope: ' . $vr['scope']);
                        break;
                }
            } catch (\Exception $e) {
                $this->console->warning($e->getMessage());
            }
        }
    }
}