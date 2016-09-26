<?php
namespace Scalr\Upgrade\Updates;

use Exception;
use Scalr\Model\Entity;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use SERVER_PLATFORMS;

class Update20151009141048 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '690f6d23-ed52-4235-b50f-e188b7b3a139';

    protected $depends = [];

    protected $description = "Migrate cloud credentials from `client_environment_properties` to `cloud_credentials`";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    private static function getCloudsCredentialProperties()
    {
        return [
            SERVER_PLATFORMS::EC2 => [
                'ec2.access_key'                        => Entity\CloudCredentialsProperty::AWS_ACCESS_KEY,
                'ec2.secret_key'                        => Entity\CloudCredentialsProperty::AWS_SECRET_KEY,
                'ec2.private_key'                       => Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY,
                'ec2.certificate'                       => Entity\CloudCredentialsProperty::AWS_CERTIFICATE,
                'ec2.account_id'                        => Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID,
                'ec2.account_type'                      => Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE,

                'ec2.detailed_billing.bucket'           => Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET,
                'ec2.detailed_billing.enabled'          => Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED,
                'ec2.detailed_billing.payer_account'    => Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT,
                'ec2.detailed_billing.region'           => Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION,
            ],

            SERVER_PLATFORMS::GCE => [
                'gce.client_id'             => Entity\CloudCredentialsProperty::GCE_CLIENT_ID,
                'gce.service_account_name'  => Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME,
                'gce.key'                   => Entity\CloudCredentialsProperty::GCE_KEY,
                'gce.project_id'            => Entity\CloudCredentialsProperty::GCE_PROJECT_ID,
                'gce.access_token'          => Entity\CloudCredentialsProperty::GCE_ACCESS_TOKEN,
                'gce.json_key'              => Entity\CloudCredentialsProperty::GCE_JSON_KEY,
            ],

            SERVER_PLATFORMS::AZURE => [
                'azure.tenant_name'             => Entity\CloudCredentialsProperty::AZURE_TENANT_NAME,
                'azure.auth_code'               => Entity\CloudCredentialsProperty::AZURE_AUTH_CODE,
                'azure.access_token'            => Entity\CloudCredentialsProperty::AZURE_ACCESS_TOKEN,
                'azure.refresh_token'           => Entity\CloudCredentialsProperty::AZURE_REFRESH_TOKEN,
                'azure.client_token'            => Entity\CloudCredentialsProperty::AZURE_CLIENT_TOKEN,

                'azure.access_token_expire'     => Entity\CloudCredentialsProperty::AZURE_ACCESS_TOKEN_EXPIRE,
                'azure.refresh_token_expire'    => Entity\CloudCredentialsProperty::AZURE_REFRESH_TOKEN_EXPIRE,
                'azure.client_token_expire'     => Entity\CloudCredentialsProperty::AZURE_CLIENT_TOKEN_EXPIRE,
                'azure.subscription_id'         => Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID,
                'azure.storage_account_name'    => Entity\CloudCredentialsProperty::AZURE_STORAGE_ACCOUNT_NAME,
                'azure.client_object_id'        => Entity\CloudCredentialsProperty::AZURE_CLIENT_OBJECT_ID,
                'azure.contributor_id'          => Entity\CloudCredentialsProperty::AZURE_CONTRIBUTOR_ID,
                'azure.role_assignment_id'      => Entity\CloudCredentialsProperty::AZURE_ROLE_ASSIGNMENT_ID,
                'azure.step'                    => Entity\CloudCredentialsProperty::AZURE_AUTH_STEP,
            ],

            SERVER_PLATFORMS::OPENSTACK => [
                SERVER_PLATFORMS::OPENSTACK . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::OPENSTACK . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::OPENSTACK . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::OPENSTACK . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::OPENSTACK . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::OPENSTACK . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::OPENSTACK . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::OPENSTACK . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::OPENSTACK . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::OPENSTACK . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::OPENSTACK . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::OPENSTACK . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::OPENSTACK . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::OPENSTACK . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::OPENSTACK . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::OPENSTACK . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::OCS => [
                SERVER_PLATFORMS::OCS . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::OCS . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::OCS . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::OCS . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::OCS . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::OCS . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::OCS . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::OCS . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::OCS . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::OCS . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::OCS . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::OCS . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::OCS . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::OCS . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::OCS . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::OCS . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::NEBULA => [
                SERVER_PLATFORMS::NEBULA . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::NEBULA . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::NEBULA . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::NEBULA . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::NEBULA . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::NEBULA . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::NEBULA . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::NEBULA . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::NEBULA . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::NEBULA . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::NEBULA . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::NEBULA . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::NEBULA . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::NEBULA . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::NEBULA . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::NEBULA . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::MIRANTIS => [
                SERVER_PLATFORMS::MIRANTIS . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::MIRANTIS . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::MIRANTIS . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::MIRANTIS . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::MIRANTIS . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::MIRANTIS . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::MIRANTIS . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::MIRANTIS . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::MIRANTIS . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::MIRANTIS . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::MIRANTIS . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::MIRANTIS . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::MIRANTIS . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::MIRANTIS . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::MIRANTIS . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::MIRANTIS . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::VIO => [
                SERVER_PLATFORMS::VIO . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::VIO . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::VIO . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::VIO . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::VIO . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::VIO . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::VIO . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::VIO . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::VIO . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::VIO . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::VIO . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::VIO . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::VIO . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::VIO . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::VIO . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::VIO . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::VERIZON => [
                SERVER_PLATFORMS::VERIZON . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::VERIZON . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::VERIZON . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::VERIZON . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::VERIZON . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::VERIZON . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::VERIZON . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::VERIZON . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::VERIZON . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::VERIZON . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::VERIZON . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::VERIZON . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::VERIZON . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::VERIZON . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::VERIZON . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::VERIZON . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::CISCO => [
                SERVER_PLATFORMS::CISCO . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::CISCO . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::CISCO . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::CISCO . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::CISCO . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::CISCO . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::CISCO . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::CISCO . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::CISCO . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::CISCO . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::CISCO . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::CISCO . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::CISCO . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::CISCO . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::CISCO . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::CISCO . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::HPCLOUD => [
                SERVER_PLATFORMS::HPCLOUD . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::HPCLOUD . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::HPCLOUD . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::HPCLOUD . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::HPCLOUD . ".domain_name"                => Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::HPCLOUD . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::HPCLOUD . ".ssl_verifypeer"             => Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER,
                SERVER_PLATFORMS::HPCLOUD . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::HPCLOUD . "identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::HPCLOUD . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::HPCLOUD . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::HPCLOUD . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::HPCLOUD . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::HPCLOUD . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::HPCLOUD . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::HPCLOUD . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],

            SERVER_PLATFORMS::RACKSPACENG_US => [
                SERVER_PLATFORMS::RACKSPACENG_US . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::RACKSPACENG_US . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::RACKSPACENG_US . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::RACKSPACENG_US . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::RACKSPACENG_US . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::RACKSPACENG_US . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::RACKSPACENG_US . "identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::RACKSPACENG_US . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_US . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_US . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_US . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_US . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_US . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_US . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],
            SERVER_PLATFORMS::RACKSPACENG_UK => [
                SERVER_PLATFORMS::RACKSPACENG_UK . ".username"                   => Entity\CloudCredentialsProperty::OPENSTACK_USERNAME,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".api_key"                    => Entity\CloudCredentialsProperty::OPENSTACK_API_KEY,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".password"                   => Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".tenant_name"                => Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".keystone_url"               => Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".auth_token"                 => Entity\CloudCredentialsProperty::OPENSTACK_AUTH_TOKEN,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".identity_version"            => Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".ext.keypairs_enabled"        => Entity\CloudCredentialsProperty::OPENSTACK_EXT_KEYPAIRS_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".ext.configdrive_enabled"     => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CONFIG_DRIVE_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".ext.securitygroups_enabled"  => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".ext.swift_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".ext.cinder_enabled"          => Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".ext.floating_ips_enabled"    => Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED,
                SERVER_PLATFORMS::RACKSPACENG_UK . ".ext.lbaas_enabled"           => Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED,
            ],

            SERVER_PLATFORMS::CLOUDSTACK => [
                SERVER_PLATFORMS::CLOUDSTACK . ".api_key"           => Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY,
                SERVER_PLATFORMS::CLOUDSTACK . ".secret_key"        => Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY,
                SERVER_PLATFORMS::CLOUDSTACK . ".api_url"           => Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL,
                SERVER_PLATFORMS::CLOUDSTACK . ".account_name"      => Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME,
                SERVER_PLATFORMS::CLOUDSTACK . ".domain_name"       => Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::CLOUDSTACK . ".domain_id"         => Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_ID,
                SERVER_PLATFORMS::CLOUDSTACK . ".shared_ip"         => Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP,
                SERVER_PLATFORMS::CLOUDSTACK . ".shared_ip_id"      => Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_ID,
                SERVER_PLATFORMS::CLOUDSTACK . ".shared_ip_info"    => Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_INFO,
                SERVER_PLATFORMS::CLOUDSTACK . ".szr_port_counter"  => Entity\CloudCredentialsProperty::CLOUDSTACK_SZR_PORT_COUNTER,
            ],
            SERVER_PLATFORMS::IDCF => [
                SERVER_PLATFORMS::IDCF . ".api_key"           => Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY,
                SERVER_PLATFORMS::IDCF . ".secret_key"        => Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY,
                SERVER_PLATFORMS::IDCF . ".api_url"           => Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL,
                SERVER_PLATFORMS::IDCF . ".account_name"      => Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME,
                SERVER_PLATFORMS::IDCF . ".domain_name"       => Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_NAME,
                SERVER_PLATFORMS::IDCF . ".domain_id"         => Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_ID,
                SERVER_PLATFORMS::IDCF . ".shared_ip"         => Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP,
                SERVER_PLATFORMS::IDCF . ".shared_ip_id"      => Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_ID,
                SERVER_PLATFORMS::IDCF . ".shared_ip_info"    => Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_INFO,
                SERVER_PLATFORMS::IDCF . ".szr_port_counter"  => Entity\CloudCredentialsProperty::CLOUDSTACK_SZR_PORT_COUNTER,
            ],
        ];
    }

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
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $envIds = $this->db->Execute("SELECT `id` FROM `client_environments`");

        $platformVariables = static::getCloudsCredentialProperties();

        foreach ($envIds as $row) {
            $environment = \Scalr_Environment::init()->loadById($row['id']);

            $platforms = [];
            foreach (array_keys(SERVER_PLATFORMS::getList()) as $platform) {
                if ($environment->getPlatformConfigValue($platform . '.is_enabled', false)) {
                    $platforms[] = $platform;
                }
            }

            foreach($platforms as $platform) {
                try {
                    switch ($platform) {
                        default:
                            $cloudCredentials = new Entity\CloudCredentials();
                            $cloudCredentials->accountId = $environment->getAccountId();
                            $cloudCredentials->envId = $environment->id;
                            $cloudCredentials->cloud = $platform;
                            $cloudCredentials->name = "{$environment->id}-{$environment->getAccountId()}-{$platform}-" . \Scalr::GenerateUID(true);
                            $cloudCredentials->status = Entity\CloudCredentials::STATUS_ENABLED;

                            foreach ($platformVariables[$platform] as $name => $newName) {
                                $value = $environment->getPlatformConfigValue($name);

                                if ($value === null) {
                                    $value = false;
                                }

                                $cloudCredentials->properties[$newName] =  $value;
                            }

                            $cloudCredentials->save();

                            $cloudCredentials->bindToEnvironment($environment);
                            break;
                    }
                } catch (Exception $e) {
                    $this->console->error(get_class($e) . " in {$e->getFile()} on line {$e->getLine()}: " . $e->getMessage());
                    error_log(get_class($e) . " in {$e->getFile()} at line {$e->getLine()}: {$e->getMessage()}\n{$e->getTraceAsString()}");
                }
            }
        }
    }
}