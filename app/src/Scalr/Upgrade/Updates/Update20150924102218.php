<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Acl\Acl;
use Scalr\Acl\Resource\Definition;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150924102218 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'c728ac9f-6f9b-48f6-b931-cda772a7c939';

    protected $depends = [];

    protected $description = "Creates read-only permissions.";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 32;
    }

    protected function isApplied1($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GCE_STATIC_IPS', 'PERM_GCE_STATIC_IPS_MANAGE');
    }

    protected function validateBefore1($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GCE_STATIC_IPS', 'PERM_GCE_STATIC_IPS_MANAGE');
    }

    protected function run1($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GCE_STATIC_IPS', 'PERM_GCE_STATIC_IPS_MANAGE');
    }

    protected function isApplied2($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GCE_PERSISTENT_DISKS', 'PERM_GCE_PERSISTENT_DISKS_MANAGE');
    }

    protected function validateBefore2($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GCE_PERSISTENT_DISKS', 'PERM_GCE_PERSISTENT_DISKS_MANAGE');
    }

    protected function run2($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GCE_PERSISTENT_DISKS', 'PERM_GCE_PERSISTENT_DISKS_MANAGE');
    }

    protected function isApplied3($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GCE_SNAPSHOTS', 'PERM_GCE_SNAPSHOTS_MANAGE');
    }

    protected function validateBefore3($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GCE_SNAPSHOTS', 'PERM_GCE_SNAPSHOTS_MANAGE');
    }

    protected function run3($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GCE_SNAPSHOTS', 'PERM_GCE_SNAPSHOTS_MANAGE');
    }

    protected function isApplied4($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_CLOUDSTACK_VOLUMES', 'PERM_CLOUDSTACK_VOLUMES_MANAGE');
    }

    protected function validateBefore4($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_CLOUDSTACK_VOLUMES', 'PERM_CLOUDSTACK_VOLUMES_MANAGE');
    }

    protected function run4($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_CLOUDSTACK_VOLUMES', 'PERM_CLOUDSTACK_VOLUMES_MANAGE');
    }

    protected function isApplied5($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_CLOUDSTACK_SNAPSHOTS', 'PERM_CLOUDSTACK_SNAPSHOTS_MANAGE');
    }

    protected function validateBefore5($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_CLOUDSTACK_SNAPSHOTS', 'PERM_CLOUDSTACK_SNAPSHOTS_MANAGE');
    }

    protected function run5($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_CLOUDSTACK_SNAPSHOTS', 'PERM_CLOUDSTACK_SNAPSHOTS_MANAGE');
    }

    protected function isApplied6($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_CLOUDSTACK_PUBLIC_IPS', 'PERM_CLOUDSTACK_PUBLIC_IPS_MANAGE');
    }

    protected function validateBefore6($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_CLOUDSTACK_PUBLIC_IPS', 'PERM_CLOUDSTACK_PUBLIC_IPS_MANAGE');
    }

    protected function run6($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_CLOUDSTACK_PUBLIC_IPS', 'PERM_CLOUDSTACK_PUBLIC_IPS_MANAGE');
    }

    protected function isApplied7($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_OPENSTACK_VOLUMES', 'PERM_OPENSTACK_VOLUMES_MANAGE');
    }

    protected function validateBefore7($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_OPENSTACK_VOLUMES', 'PERM_OPENSTACK_VOLUMES_MANAGE');
    }

    protected function run7($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_OPENSTACK_VOLUMES', 'PERM_OPENSTACK_VOLUMES_MANAGE');
    }

    protected function isApplied8($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_OPENSTACK_SNAPSHOTS', 'PERM_OPENSTACK_SNAPSHOTS_MANAGE');
    }

    protected function validateBefore8($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_OPENSTACK_SNAPSHOTS', 'PERM_OPENSTACK_SNAPSHOTS_MANAGE');
    }

    protected function run8($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_OPENSTACK_SNAPSHOTS', 'PERM_OPENSTACK_SNAPSHOTS_MANAGE');
    }

    protected function isApplied9($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_WEBHOOKS_ENVIRONMENT', 'PERM_WEBHOOKS_ENVIRONMENT_MANAGE');
    }

    protected function validateBefore9($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_WEBHOOKS_ENVIRONMENT', 'PERM_WEBHOOKS_ENVIRONMENT_MANAGE');
    }

    protected function run9($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_WEBHOOKS_ENVIRONMENT', 'PERM_WEBHOOKS_ENVIRONMENT_MANAGE');
    }

    protected function isApplied10($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_DNS_ZONES', 'PERM_DNS_ZONES_MANAGE');
    }

    protected function validateBefore10($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_DNS_ZONES', 'PERM_DNS_ZONES_MANAGE');
    }

    protected function run10($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_DNS_ZONES', 'PERM_DNS_ZONES_MANAGE');
    }

    protected function isApplied11($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_S3', 'PERM_AWS_S3_MANAGE');
    }

    protected function validateBefore11($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_S3', 'PERM_AWS_S3_MANAGE');
    }

    protected function run11($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_S3', 'PERM_AWS_S3_MANAGE');
    }

    protected function isApplied12($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_ELASTIC_IPS', 'PERM_AWS_ELASTIC_IPS_MANAGE');
    }

    protected function validateBefore12($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_ELASTIC_IPS', 'PERM_AWS_ELASTIC_IPS_MANAGE');
    }

    protected function run12($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_ELASTIC_IPS', 'PERM_AWS_ELASTIC_IPS_MANAGE');
    }

    protected function isApplied13($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_ELB', 'PERM_AWS_ELB_MANAGE');
    }

    protected function validateBefore13($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_ELB', 'PERM_AWS_ELB_MANAGE');
    }

    protected function run13($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_ELB', 'PERM_AWS_ELB_MANAGE');
    }

    protected function isApplied14($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_IAM', 'PERM_AWS_IAM_MANAGE');
    }

    protected function validateBefore14($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_IAM', 'PERM_AWS_IAM_MANAGE');
    }

    protected function run14($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_IAM', 'PERM_AWS_IAM_MANAGE');
    }

    protected function isApplied15($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_RDS', 'PERM_AWS_RDS_MANAGE');
    }

    protected function validateBefore15($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_RDS', 'PERM_AWS_RDS_MANAGE');
    }

    protected function run15($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_RDS', 'PERM_AWS_RDS_MANAGE');
    }

    protected function isApplied16($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_SNAPSHOTS', 'PERM_AWS_SNAPSHOTS_MANAGE');
    }

    protected function validateBefore16($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_SNAPSHOTS', 'PERM_AWS_SNAPSHOTS_MANAGE');
    }

    protected function run16($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_SNAPSHOTS', 'PERM_AWS_SNAPSHOTS_MANAGE');
    }

    protected function isApplied17($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_VOLUMES', 'PERM_AWS_VOLUMES_MANAGE');
    }

    protected function validateBefore17($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_VOLUMES', 'PERM_AWS_VOLUMES_MANAGE');
    }

    protected function run17($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_VOLUMES', 'PERM_AWS_VOLUMES_MANAGE');
    }

    protected function isApplied18($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_AWS_ROUTE53', 'PERM_AWS_ROUTE53_MANAGE');
    }

    protected function validateBefore18($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_AWS_ROUTE53', 'PERM_AWS_ROUTE53_MANAGE');
    }

    protected function run18($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_AWS_ROUTE53', 'PERM_AWS_ROUTE53_MANAGE');
    }

    protected function isApplied19($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_SECURITY_SECURITY_GROUPS', 'PERM_SECURITY_SECURITY_GROUPS_MANAGE');
    }

    protected function validateBefore19($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_SECURITY_SECURITY_GROUPS', 'PERM_SECURITY_SECURITY_GROUPS_MANAGE');
    }

    protected function run19($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_SECURITY_SECURITY_GROUPS', 'PERM_SECURITY_SECURITY_GROUPS_MANAGE');
    }

    protected function isApplied20($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_SECURITY_SSH_KEYS', 'PERM_SECURITY_SSH_KEYS_MANAGE');
    }

    protected function validateBefore20($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_SECURITY_SSH_KEYS', 'PERM_SECURITY_SSH_KEYS_MANAGE');
    }

    protected function run20($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_SECURITY_SSH_KEYS', 'PERM_SECURITY_SSH_KEYS_MANAGE');
    }

    protected function isApplied21($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_SERVICES_APACHE', 'PERM_SERVICES_APACHE_MANAGE');
    }

    protected function validateBefore21($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_SERVICES_APACHE', 'PERM_SERVICES_APACHE_MANAGE');
    }

    protected function run21($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_SERVICES_APACHE', 'PERM_SERVICES_APACHE_MANAGE');
    }

    protected function isApplied22($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_SERVICES_CHEF_ENVIRONMENT', 'PERM_SERVICES_CHEF_ENVIRONMENT_MANAGE');
    }

    protected function validateBefore22($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_SERVICES_CHEF_ENVIRONMENT', 'PERM_SERVICES_CHEF_ENVIRONMENT_MANAGE');
    }

    protected function run22($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_SERVICES_CHEF_ENVIRONMENT', 'PERM_SERVICES_CHEF_ENVIRONMENT_MANAGE');
    }

    protected function isApplied23($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_SERVICES_CHEF_ACCOUNT', 'PERM_SERVICES_CHEF_ACCOUNT_MANAGE');
    }

    protected function validateBefore23($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_SERVICES_CHEF_ACCOUNT', 'PERM_SERVICES_CHEF_ACCOUNT_MANAGE');
    }

    protected function run23($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_SERVICES_CHEF_ACCOUNT', 'PERM_SERVICES_CHEF_ACCOUNT_MANAGE');
    }

    protected function isApplied24($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_SERVICES_SSL', 'PERM_SERVICES_SSL_MANAGE');
    }

    protected function validateBefore24($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_SERVICES_SSL', 'PERM_SERVICES_SSL_MANAGE');
    }

    protected function run24($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_SERVICES_SSL', 'PERM_SERVICES_SSL_MANAGE');
    }

    protected function isApplied25($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GENERAL_CUSTOM_EVENTS', 'PERM_GENERAL_CUSTOM_EVENTS_MANAGE');
    }

    protected function validateBefore25($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GENERAL_CUSTOM_EVENTS', 'PERM_GENERAL_CUSTOM_EVENTS_MANAGE');
    }

    protected function run25($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GENERAL_CUSTOM_EVENTS', 'PERM_GENERAL_CUSTOM_EVENTS_MANAGE');
    }

    protected function isApplied26($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GENERAL_CUSTOM_SCALING_METRICS', 'PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE');
    }

    protected function validateBefore26($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GENERAL_CUSTOM_SCALING_METRICS', 'PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE');
    }

    protected function run26($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GENERAL_CUSTOM_SCALING_METRICS', 'PERM_GENERAL_CUSTOM_SCALING_METRICS_MANAGE');
    }

    protected function isApplied27($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GENERAL_SCHEDULERTASKS', 'PERM_GENERAL_SCHEDULERTASKS_MANAGE');
    }

    protected function validateBefore27($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GENERAL_SCHEDULERTASKS', 'PERM_GENERAL_SCHEDULERTASKS_MANAGE');
    }

    protected function run27($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GENERAL_SCHEDULERTASKS', 'PERM_GENERAL_SCHEDULERTASKS_MANAGE');
    }

    protected function isApplied28($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_ORCHESTRATION_ACCOUNT', 'PERM_ORCHESTRATION_ACCOUNT_MANAGE');
    }

    protected function validateBefore28($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_ORCHESTRATION_ACCOUNT', 'PERM_ORCHESTRATION_ACCOUNT_MANAGE');
    }

    protected function run28($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_ORCHESTRATION_ACCOUNT', 'PERM_ORCHESTRATION_ACCOUNT_MANAGE');
    }

    protected function isApplied29($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GLOBAL_VARIABLES_ACCOUNT', 'PERM_GLOBAL_VARIABLES_ACCOUNT_MANAGE');
    }

    protected function validateBefore29($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GLOBAL_VARIABLES_ACCOUNT', 'PERM_GLOBAL_VARIABLES_ACCOUNT_MANAGE');
    }

    protected function run29($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GLOBAL_VARIABLES_ACCOUNT', 'PERM_GLOBAL_VARIABLES_ACCOUNT_MANAGE');
    }

    protected function isApplied30($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_WEBHOOKS_ACCOUNT', 'PERM_WEBHOOKS_ACCOUNT_MANAGE');
    }

    protected function validateBefore30($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_WEBHOOKS_ACCOUNT', 'PERM_WEBHOOKS_ACCOUNT_MANAGE');
    }

    protected function run30($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_WEBHOOKS_ACCOUNT', 'PERM_WEBHOOKS_ACCOUNT_MANAGE');
    }

    protected function isApplied31($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT', 'PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE');
    }

    protected function validateBefore31($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT', 'PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE');
    }

    protected function run31($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT', 'PERM_GLOBAL_VARIABLES_ENVIRONMENT_MANAGE');
    }

    protected function isApplied32($stage)
    {
        return $this->checkAppliedForPermission('RESOURCE_DB_DATABASE_STATUS', 'PERM_DB_DATABASE_STATUS_MANAGE');
    }

    protected function validateBefore32($stage)
    {
        return $this->checkValidationForPermission('RESOURCE_DB_DATABASE_STATUS', 'PERM_DB_DATABASE_STATUS_MANAGE');
    }

    protected function run32($stage)
    {
        $this->createNewFullAccessPermission('RESOURCE_DB_DATABASE_STATUS', 'PERM_DB_DATABASE_STATUS_MANAGE');
    }

    /**
     * Check if stage is applied for the specified resource and permission
     *
     * @param    string    $resourceName   The name of the ACL resource (Example:"RESOURCE_FARMS")
     * @param    string    $permissionName The name of the ACL permission (Example:"PERM_FARMS_SERVERS")
     * @return   boolean
     */
    private function checkAppliedForPermission($resourceName, $permissionName)
    {
        return  defined('Scalr\\Acl\\Acl::' . $resourceName) &&
            defined('Scalr\\Acl\\Acl::' . $permissionName) &&
            Definition::has(constant('Scalr\\Acl\\Acl::' . $resourceName)) &&
            $this->db->GetOne("
                SELECT `granted` FROM `acl_role_resource_permissions`
                WHERE `resource_id` = ? AND `role_id` = ? AND `perm_id` = ?
                LIMIT 1
            ", [
                constant('Scalr\\Acl\\Acl::' . $resourceName),
                Acl::ROLE_ID_FULL_ACCESS,
                constant('Scalr\\Acl\\Acl::' . $permissionName)
            ]) == 1;
    }

    /**
     * Check if stage is validated for the specified resource and permission
     *
     * @param    string    $resourceName   The name of the ACL resource (Example:"RESOURCE_FARMS")
     * @param    string    $permissionName The name of the ACL permission (Example:"PERM_FARMS_SERVERS")
     * @return   boolean
     */
    private function checkValidationForPermission($resourceName, $permissionName)
    {
        return defined('Scalr\\Acl\\Acl::' . $resourceName) &&
            defined('Scalr\\Acl\\Acl::' . $permissionName);
    }

    /**
     * Adds a new Full Access Role Permission
     *
     * @param    string    $resourceName   The name of the ACL resource (Example:"RESOURCE_FARMS")
     * @param    string    $permissionName The name of the ACL permission (Example:"PERM_FARMS_SERVERS")
     */
    private function createNewFullAccessPermission($resourceName, $permissionName)
    {
        $this->console->out('Creating %s ACL permission', $permissionName);

        $this->db->Execute("
            INSERT IGNORE `acl_role_resource_permissions` (`role_id`, `resource_id`, `perm_id`, `granted`)
            VALUES (?, ?, ?, 1)
        ", [
            Acl::ROLE_ID_FULL_ACCESS,
            constant('Scalr\\Acl\\Acl::' . $resourceName),
            constant('Scalr\\Acl\\Acl::' . $permissionName)
        ]);
    }

}