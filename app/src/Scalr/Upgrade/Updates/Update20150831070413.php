<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;

class Update20150831070413 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '2b2f5540-a23a-422a-9559-866f28c6dc1c';

    protected $depends = [];

    protected $description = "Creates acl_account_role_resource_modes table";

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
        return $this->hasTable('acl_account_role_resource_modes');
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out('Creating acl_account_role_resource_modes table');

        $this->db->Execute("
            CREATE TABLE `acl_account_role_resource_modes` (
                `account_role_id` varchar(20) NOT NULL,
                `resource_id` int(11) NOT NULL,
                `mode` tinyint(1) DEFAULT NULL,
                PRIMARY KEY (`account_role_id`,`resource_id`),
                KEY `idx_resource_id` (`resource_id`),
                CONSTRAINT `fk_5b640da31e7b`
                    FOREIGN KEY (`account_role_id`, `resource_id`)
                    REFERENCES `acl_account_role_resources` (`account_role_id`, `resource_id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8
        ");
    }
}