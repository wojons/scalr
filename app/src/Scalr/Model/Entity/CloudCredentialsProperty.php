<?php

namespace Scalr\Model\Entity;
use Scalr\Model\Type\EncryptedType;
use Scalr\Model\Loader\Field;

/**
 * CloudCredentialsProperty entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="cloud_credentials_properties")
 */
class CloudCredentialsProperty extends Setting
{

    /*
     * AWS
     */
    const AWS_ACCOUNT_ID 	= 'account_id';
    const AWS_ACCESS_KEY	= 'access_key';
    const AWS_SECRET_KEY	= 'secret_key';
    const AWS_PRIVATE_KEY	= 'private_key';
    const AWS_CERTIFICATE	= 'certificate';
    const AWS_ACCOUNT_TYPE  = 'account_type';

    const AWS_DETAILED_BILLING_BUCKET           = 'detailed_billing.bucket';
    const AWS_DETAILED_BILLING_ENABLED          = 'detailed_billing.enabled';
    const AWS_DETAILED_BILLING_PAYER_ACCOUNT    = 'detailed_billing.payer_account';
    const AWS_DETAILED_BILLING_REGION           = 'detailed_billing.region';

    const AWS_ACCOUNT_TYPE_REGULAR      = 'regular';
    const AWS_ACCOUNT_TYPE_GOV_CLOUD    = 'gov-cloud';
    const AWS_ACCOUNT_TYPE_CN_CLOUD     = 'cn-cloud';

    /*
     * OpenStack
     */
    const OPENSTACK_USERNAME          = 'username';
    const OPENSTACK_API_KEY           = 'api_key';
    const OPENSTACK_PASSWORD          = 'password';
    const OPENSTACK_TENANT_NAME       = 'tenant_name';
    const OPENSTACK_DOMAIN_NAME       = 'domain_name';
    const OPENSTACK_KEYSTONE_URL      = 'keystone_url';
    const OPENSTACK_SSL_VERIFYPEER    = 'ssl_verifypeer';
    const OPENSTACK_IDENTITY_VERSION  = 'identity_version';

    const OPENSTACK_AUTH_TOKEN        = 'auth_token';

    const OPENSTACK_EXT_KEYPAIRS_ENABLED        = 'ext.keypairs_enabled';
    const OPENSTACK_EXT_CONFIG_DRIVE_ENABLED    = 'ext.configdrive_enabled';
    const OPENSTACK_EXT_SECURITYGROUPS_ENABLED  = 'ext.securitygroups_enabled';
    const OPENSTACK_EXT_SWIFT_ENABLED           = 'ext.swift_enabled';
    const OPENSTACK_EXT_CINDER_ENABLED          = 'ext.cinder_enabled';
    const OPENSTACK_EXT_FLOATING_IPS_ENABLED    = 'ext.floating_ips_enabled';
    const OPENSTACK_EXT_LBAAS_ENABLED           = 'ext.lbaas_enabled';

    /*
     * Rackspace
     */
    const RACKSPACE_USERNAME    = 'username';
    const RACKSPACE_API_KEY     = 'api_key';
    const RACKSPACE_IS_MANAGED  = 'is_managed';

    /*
     * GCE
     */
    const GCE_CLIENT_ID             = 'client_id';
    const GCE_SERVICE_ACCOUNT_NAME  = 'service_account_name';
    const GCE_KEY                   = 'key';
    const GCE_PROJECT_ID            = 'project_id';
    const GCE_ACCESS_TOKEN          = 'access_token';
    const GCE_JSON_KEY              = 'json_key';

    /*
     * Cloudstack
     */
    const CLOUDSTACK_API_KEY            = 'api_key';
    const CLOUDSTACK_SECRET_KEY         = 'secret_key';
    const CLOUDSTACK_API_URL            = 'api_url';

    const CLOUDSTACK_ACCOUNT_NAME       = 'account_name';
    const CLOUDSTACK_DOMAIN_NAME        = 'domain_name';
    const CLOUDSTACK_DOMAIN_ID          = 'domain_id';
    const CLOUDSTACK_SHARED_IP          = 'shared_ip';
    const CLOUDSTACK_SHARED_IP_ID       = 'shared_ip_id';
    const CLOUDSTACK_SHARED_IP_INFO     = 'shared_ip_info';
    const CLOUDSTACK_SZR_PORT_COUNTER   = 'szr_port_counter';

    /*
     * Azure
     */
    const AZURE_TENANT_NAME             = 'tenant_name';

    const AZURE_AUTH_CODE               = 'auth_code';

    const AZURE_ACCESS_TOKEN            = 'access_token';
    const AZURE_ACCESS_TOKEN_EXPIRE     = 'access_token_expire';

    const AZURE_REFRESH_TOKEN           = 'refresh_token';
    const AZURE_REFRESH_TOKEN_EXPIRE    = 'refresh_token_expire';

    const AZURE_CLIENT_TOKEN            = 'client_token';
    const AZURE_CLIENT_TOKEN_EXPIRE     = 'client_token_expire';

    const AZURE_SUBSCRIPTION_ID         = 'subscription_id';
    const AZURE_STORAGE_ACCOUNT_NAME    = 'storage_account_name';

    const AZURE_CLIENT_OBJECT_ID        = 'client_object_id';

    const AZURE_CONTRIBUTOR_ID          = 'contributor_id';

    const AZURE_ROLE_ASSIGNMENT_ID      = 'role_assignment_id';

    const AZURE_AUTH_STEP               = 'step';

    /**
     * Encrypted properties list static cache
     *
     * @var array
     */
    protected static $encryptedFields;

    /**
     * Cloud credentials UUID
     *
     * @Id
     * @Column(type="uuidShort")
     *
     * @var string
     */
    public $cloudCredentialsId;

    /**
     * Property value
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $value = false;

    /**
     * Gets names of encrypted properties
     *
     * @return array
     */
    public static function listEncryptedFields()
    {
        if (empty(static::$encryptedFields)) {
            static::$encryptedFields = [
                static::CLOUDSTACK_API_KEY,
                static::CLOUDSTACK_API_URL,
                static::CLOUDSTACK_SECRET_KEY,

                static::AZURE_ACCESS_TOKEN,
                static::AZURE_REFRESH_TOKEN,
                static::AZURE_CLIENT_TOKEN,
                static::AZURE_TENANT_NAME,
                static::AZURE_AUTH_CODE,

                static::OPENSTACK_API_KEY,
                static::OPENSTACK_AUTH_TOKEN,
                static::OPENSTACK_KEYSTONE_URL,
                static::OPENSTACK_PASSWORD,
                static::OPENSTACK_TENANT_NAME,
                static::OPENSTACK_DOMAIN_NAME,
                static::OPENSTACK_USERNAME,
                static::OPENSTACK_SSL_VERIFYPEER,

                static::AWS_ACCESS_KEY,
                static::AWS_CERTIFICATE,
                static::AWS_PRIVATE_KEY,
                static::AWS_SECRET_KEY,

                static::GCE_ACCESS_TOKEN,
                static::GCE_CLIENT_ID,
                static::GCE_KEY,
                static::GCE_PROJECT_ID,
                static::GCE_SERVICE_ACCOUNT_NAME,
                static::GCE_JSON_KEY,

                static::RACKSPACE_API_KEY,
                static::RACKSPACE_IS_MANAGED,
                static::RACKSPACE_USERNAME
            ];
        }

        return static::$encryptedFields;
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        /* @var $field Field */
        //NOTE: hack for partial encryption, todo: think about improvement of this
        if (in_array($this->name, static::listEncryptedFields())) {
            $field = $this->getIterator()->fields()['value'];
            $prevType = $field->getType();
            $field->setType(new EncryptedType($field));
        }

        parent::save();

        if (isset($field)) {
            $field->setType($prevType);
        }
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::load()
     */
    public function load($obj, $tableAlias = null)
    {
        parent::load($obj, $tableAlias);

        if (in_array($this->name, static::listEncryptedFields())) {
            $this->value = \Scalr::getContainer()->crypto->decrypt($this->value);
        }
    }
}