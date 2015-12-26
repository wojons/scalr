<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151128154158 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2ece67ab-aa04-4f23-8a4b-f92bc265b158';

    protected $depends = [];

    protected $description = 'Create table role_environments';

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
        return $this->hasTable('role_environments');
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            CREATE TABLE `role_environments` (
                `role_id` int(11) NOT NULL,
                `env_id` int(11) NOT NULL,
                PRIMARY KEY (`role_id`,`env_id`),
                KEY `idx_env_id` (`env_id`),
                CONSTRAINT `fk_role_environments_client_environments_id` FOREIGN KEY (`env_id`) REFERENCES `client_environments` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
                CONSTRAINT `fk_role_environments_roles_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");
    }
}