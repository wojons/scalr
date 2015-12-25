<?php
namespace Scalr\Upgrade\Updates;

use Exception;
use ReflectionClass;
use Scalr\Acl\Acl;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20151015131054 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '429ae0e3-bf8e-4a9c-b3ab-12719a4d8929';

    protected $depends = [];

    protected $description = "Delete Analytics Project, Openstask Elb and Ips acl resources. Add read-only base role.";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    const RESOURCE_OPENSTACK_PUBLIC_IPS = 0x212;
    const RESOURCE_OPENSTACK_ELB = 0x213;
    const RESOURCE_ANALYTICS_PROJECTS = 0x240;

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 4;
    }

    protected function run1($stage)
    {
        $this->console->out("Deleting ACL Openstask Ips resource");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("DELETE FROM `acl_role_resources` WHERE `resource_id` = ?", [self::RESOURCE_OPENSTACK_PUBLIC_IPS]);

            $this->db->Execute("DELETE FROM `acl_account_role_resources` WHERE `resource_id` = ?", [self::RESOURCE_OPENSTACK_PUBLIC_IPS]);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }

    protected function run2($stage)
    {
        $this->console->out("Deleting ACL Openstask Elb resource");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("DELETE FROM `acl_role_resources` WHERE `resource_id` = ?
                ", [self::RESOURCE_OPENSTACK_ELB]);

            $this->db->Execute("DELETE FROM `acl_account_role_resources` WHERE `resource_id` = ?
                ", [self::RESOURCE_OPENSTACK_ELB]);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }

    protected function run3($stage)
    {
        $this->console->out("Deleting ACL Analytics Project resource");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("DELETE FROM `acl_role_resources` WHERE `resource_id` = ?
                ", [self::RESOURCE_ANALYTICS_PROJECTS]);

            $this->db->Execute("DELETE FROM `acl_account_role_resources` WHERE `resource_id` = ?
                ", [self::RESOURCE_ANALYTICS_PROJECTS]);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }

    protected function isApplied4($stage)
    {
        return defined('Scalr\\Acl\\Acl::ROLE_ID_READ_ONLY_ACCESS') && $this->db->GetOne("
            SELECT `role_id` FROM `acl_roles`
            WHERE `role_id` = ?
            LIMIT 1
        ", [Acl::ROLE_ID_READ_ONLY_ACCESS]);
    }

    protected function validateBefore4($stage)
    {
        return defined('Scalr\\Acl\\Acl::ROLE_ID_READ_ONLY_ACCESS');
    }

    protected function run4($stage)
    {
        $this->console->out("Adding Read-only base role");

        $this->db->BeginTrans();

        try {
            $this->db->Execute("
                INSERT INTO `acl_roles` (`role_id`, `name`)
                VALUES (?, ?)
            ", [
                Acl::ROLE_ID_READ_ONLY_ACCESS,
                'Read-only'
            ]);

            $params = [Acl::ROLE_ID_READ_ONLY_ACCESS, Acl::ROLE_ID_FULL_ACCESS];

            $noAccessResources = [
                Acl::RESOURCE_BILLING_ACCOUNT,
                Acl::RESOURCE_ANALYTICS_ACCOUNT,
                Acl::RESOURCE_ENV_CLOUDS_ENVIRONMENT,
                Acl::RESOURCE_GOVERNANCE_ENVIRONMENT,
                Acl::RESOURCE_ANALYTICS_ENVIRONMENT,
                Acl::RESOURCE_SECURITY_RETRIEVE_WINDOWS_PASSWORDS,
                Acl::RESOURCE_SERVICES_RABBITMQ,
                Acl::RESOURCE_DEPLOYMENTS_APPLICATIONS,
                Acl::RESOURCE_DEPLOYMENTS_SOURCES,
                Acl::RESOURCE_DEPLOYMENTS_TASKS,
                Acl::RESOURCE_DB_SERVICE_CONFIGURATION,
                Acl::RESOURCE_ORPHANED_SERVERS
            ];

            $notIn = "AND arc.`resource_id` NOT IN (" . implode(",", array_fill(0, count($noAccessResources), '?')) . ")";
            $params = array_merge($params, $noAccessResources);

            $this->db->Execute("
                INSERT INTO `acl_role_resources` (`role_id`, `resource_id`, `granted`)
                SELECT ?, arc.`resource_id`, 1
                FROM `acl_role_resources` arc
                WHERE arc.role_id = ? " . $notIn . "
            ", $params);

            $this->db->CommitTrans();
        } catch (Exception $e) {
            $this->db->RollbackTrans();
            throw $e;
        }
    }

}