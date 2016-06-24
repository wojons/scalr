<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Acl\Resource\Definition;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Acl\Acl;

class Update20160226114036 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '626a47f2-fe13-439d-9647-635c99295990';

    protected $depends = [];

    protected $description = 'Adding new ACL Resources for Discovery manager';

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_DISCOVERY_SERVERS') &&
        Definition::has(Acl::RESOURCE_DISCOVERY_SERVERS) &&
        defined('Scalr\\Acl\\Acl::PERM_DISCOVERY_SERVERS_IMPORT');
    }

    protected function run1($stage)
    {
        $this->console->out("Adding ACL permissions to import Servers");

        $this->db->Execute("
            INSERT IGNORE INTO `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_DISCOVERY_SERVERS,
            Acl::PERM_DISCOVERY_SERVERS_IMPORT
        ]);
    }
}