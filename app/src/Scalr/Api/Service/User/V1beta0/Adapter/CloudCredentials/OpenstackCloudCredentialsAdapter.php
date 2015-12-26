<?php


namespace Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentials;


use Exception;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentialsAdapter;
use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\OpenStackConfig;
use Scalr\Service\OpenStack\Services\Servers\Type\ServersExtension;
use Scalr\System\Config\Yaml;
use SERVER_PLATFORMS;

/**
 * Openstack Cloud Credentials Adapter v1beta0
 *
 * @author N.V.
 */
class OpenstackCloudCredentialsAdapter extends CloudCredentialsAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            '_cloudCredentialsType' => 'cloudCredentialsType', '_provider' => 'provider', '_scope' => 'scope', '_status' => 'status',
            'id', 'name', 'description',
            '_keystoneUrl' => 'keystoneUrl'
        ],

        self::RULE_TYPE_SETTINGS_PROPERTY => 'properties',
        self::RULE_TYPE_SETTINGS    => [
            Entity\CloudCredentialsProperty::OPENSTACK_USERNAME        => 'userName',
            Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD        => '!password',
            Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME     => 'tenantName',
            Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME     => 'domainName',
            Entity\CloudCredentialsProperty::OPENSTACK_SSL_VERIFYPEER  => 'sslVerification',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'keystoneUrl', 'userName', 'password', 'tenantName', 'sslVerification'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    public function _cloudCredentialsType($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->cloudCredentialsType = static::CLOUD_CREDENTIALS_TYPE_OPENSTACK;
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                $to->cloud = $this->getOpenstackProvider($from);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['cloud' => ['$in' => PlatformFactory::getCanonicalOpenstackPlatforms()]]];
        }
    }

    public function _provider($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->provider = $from->cloud;
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['cloud' => $this->getOpenstackProvider($from)]];
        }
    }

    public function _keystoneUrl($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->keystoneUrl = $from->properties[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                $to->properties[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL] = $from->keystoneUrl;
                $to->properties[Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION] = OpenStackConfig::parseIdentityVersion($from->keystoneUrl);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ ]];
        }
    }

    /**
     * @param   Entity\CloudCredentials $entity
     * @param   Entity\CloudCredentials $prevConfig
     */
    public function validateEntity($entity, $prevConfig = null)
    {
        parent::validateEntity($entity, $prevConfig);

        $ccProps = $entity->properties;
        $prevCcProps = isset($prevConfig) ? $prevConfig->properties : null;

        if ($this->needValidation($ccProps, $prevCcProps)) {
            if (empty($ccProps[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL])) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Missed property keystoneUrl");
            }

            /* @var $config Yaml */
            $config = $this->controller->getContainer()->config;

            if (isset($platform) &&
                $config->defined("scalr.{$platform}.use_proxy") &&
                $config("scalr.{$platform}.use_proxy") &&
                in_array($config('scalr.connections.proxy.use_on'), ['both', 'scalr'])) {
                $proxySettings = $config('scalr.connections.proxy');
            } else {
                $proxySettings = null;
            }

            try {
                $os = new OpenStack(new OpenStackConfig(
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL],
                    'fake-region',
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION],
                    $proxySettings
                ));

                //It throws an exception on failure
                $zones = $os->listZones();
                $zone = array_shift($zones);

                $os = new OpenStack(new OpenStackConfig(
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_USERNAME],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_KEYSTONE_URL],
                    $zone->name,
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_API_KEY],
                    null, // Closure callback for token
                    null, // Auth token. We should be assured about it right now
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_PASSWORD],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_TENANT_NAME],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_DOMAIN_NAME],
                    $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_IDENTITY_VERSION],
                    $proxySettings
                ));

                // Check SG Extension
                $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_SECURITYGROUPS_ENABLED] = (int)$os->servers->isExtensionSupported(ServersExtension::securityGroups());

                // Check Floating Ips Extension
                $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_FLOATING_IPS_ENABLED] = (int)$os->servers->isExtensionSupported(ServersExtension::floatingIps());

                // Check Cinder Extension
                $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_CINDER_ENABLED] = (int)$os->hasService('volume');

                // Check Swift Extension
                $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_SWIFT_ENABLED] = (int)$os->hasService('object-store');

                // Check LBaas Extension
                $ccProps[Entity\CloudCredentialsProperty::OPENSTACK_EXT_LBAAS_ENABLED] = (!in_array($entity->cloud, array(SERVER_PLATFORMS::RACKSPACENG_US, SERVER_PLATFORMS::RACKSPACENG_UK)) && $os->hasService('network')) ? (int)$os->network->isExtensionSupported('lbaas') : 0;
            } catch (Exception $e) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Failed to verify your Openstack credentials: {$e->getMessage()}");
            }

            $entity->status = Entity\CloudCredentials::STATUS_ENABLED;
        }
    }
}