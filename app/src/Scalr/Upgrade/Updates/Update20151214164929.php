<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Definition;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151214164929 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '0d6852db-bfcf-4fc5-9945-cbf81589836a';

    protected $depends = [];

    protected $description = 'Adding new ACL Resources for Cloud Credentials';

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
        return defined('Scalr\\Acl\\Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT') &&
               $this->db->GetOne("
                   SELECT `granted` FROM `acl_role_resources`
                   WHERE `resource_id` = ? AND `role_id` = ?
                   LIMIT 1
               ", array(
                   Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT,
                   Acl::ROLE_ID_FULL_ACCESS,
               )) == 1;
    }

    protected function validateBefore1($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT') &&
               Definition::has(Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT);
    }

    protected function run1($stage)
    {
        $this->console->out("Adding ACL resource to manage Cloud Credentials (account scope)");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT
        ));
    }

    protected function isApplied2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT') &&
               $this->db->GetOne("
                   SELECT `granted` FROM `acl_role_resources`
                   WHERE `resource_id` = ? AND `role_id` = ?
                   LIMIT 1
               ", array(
                   Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT,
                   Acl::ROLE_ID_FULL_ACCESS,
               )) == 1;
    }

    protected function validateBefore2($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT') &&
               Definition::has(Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT);
    }

    protected function run2($stage)
    {
        $this->console->out("Adding ACL resource to manage Cloud Credentials (environment scope)");
        $this->db->Execute("
            INSERT IGNORE `acl_role_resources` (`role_id`, `resource_id`, `granted`)
            VALUES (?, ?, 1)
        ", array(
            Acl::ROLE_ID_FULL_ACCESS,
            Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT
        ));
    }

    protected function isApplied3($stage)
    {
        return false;
    }

    protected function validateBefore3($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT') &&
               Definition::has(Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT);
    }

    protected function run3($stage)
    {
        $this->console->out("Initializing a new Cloud Credentials (environment scope) ACL Resource");
        $this->db->Execute("
            INSERT IGNORE `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`)
            SELECT DISTINCT `account_role_id`, ?, `granted` FROM `acl_account_role_resources`
            WHERE `resource_id` = ?
        ", array(
            Acl::RESOURCE_CLOUD_CREDENTIALS_ENVIRONMENT,
            Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT
        ));
    }

    protected function isApplied4($stage)
    {
        return false;
    }

    protected function validateBefore4($stage)
    {
        return defined('Scalr\\Acl\\Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT') &&
        Definition::has(Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT);
    }

    protected function run4($stage)
    {
        $this->console->out("Initializing a new Cloud Credentials (account scope) ACL Resource");
        $this->db->Execute("
            INSERT IGNORE `acl_account_role_resources` (`account_role_id`, `resource_id`, `granted`)
            SELECT DISTINCT `account_role_id`, ?, `granted` FROM `acl_account_role_resources`
            WHERE `resource_id` = ?
        ", array(
            Acl::RESOURCE_CLOUD_CREDENTIALS_ACCOUNT,
            Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT
        ));
    }
}