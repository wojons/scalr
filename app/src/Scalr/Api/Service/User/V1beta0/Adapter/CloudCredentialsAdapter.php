<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use SERVER_PLATFORMS;

/**
 * Cloud Credentials Adapter v1beta0
 *
 * @author N.V.
 *
 * @method  Entity\CloudCredentials toEntity($data) Converts data to cloud credentials entity
 */
class CloudCredentialsAdapter extends ApiEntityAdapter
{

    const CLOUD_CREDENTIALS_TYPE_AWS        = 'AwsCloudCredentials';
    const CLOUD_CREDENTIALS_TYPE_GCE        = 'GceCloudCredentials';
    const CLOUD_CREDENTIALS_TYPE_AZURE      = 'AzureCloudCredentials';
    const CLOUD_CREDENTIALS_TYPE_OPENSTACK  = 'OpenstackCloudCredentials';
    const CLOUD_CREDENTIALS_TYPE_CLOUDSTACK = 'CloudstackCloudCredentials';
    const CLOUD_CREDENTIALS_TYPE_RACKSPACE  = 'RackspaceCloudCredentials';

    /**
     * Rules of settings that changes require re-validation of cloud credentials
     */
    const RULE_TYPE_IMPORTANT_SETTINGS = 'settings.important';

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'id', 'name', 'description',
            '_cloudCredentialsType' => 'cloudCredentialsType', '_provider' => 'provider', '_scope' => 'scope', '_status' => 'status'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description'],

        self::RULE_TYPE_FILTERABLE  => ['id', 'name', 'cloudCredentialsType', 'provider', 'status', 'scope'],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    protected static $cloudsMap = [
        SERVER_PLATFORMS::EC2               => self::CLOUD_CREDENTIALS_TYPE_AWS,
        SERVER_PLATFORMS::GCE               => self::CLOUD_CREDENTIALS_TYPE_GCE,
        SERVER_PLATFORMS::AZURE             => self::CLOUD_CREDENTIALS_TYPE_AZURE,
        SERVER_PLATFORMS::CLOUDSTACK        => self::CLOUD_CREDENTIALS_TYPE_CLOUDSTACK,
        SERVER_PLATFORMS::IDCF              => self::CLOUD_CREDENTIALS_TYPE_CLOUDSTACK,
        SERVER_PLATFORMS::OPENSTACK         => self::CLOUD_CREDENTIALS_TYPE_OPENSTACK,
        SERVER_PLATFORMS::OCS               => self::CLOUD_CREDENTIALS_TYPE_OPENSTACK,
        SERVER_PLATFORMS::RACKSPACENG_UK    => self::CLOUD_CREDENTIALS_TYPE_RACKSPACE,
        SERVER_PLATFORMS::RACKSPACENG_US    => self::CLOUD_CREDENTIALS_TYPE_RACKSPACE,
        SERVER_PLATFORMS::HPCLOUD           => self::CLOUD_CREDENTIALS_TYPE_OPENSTACK,
        SERVER_PLATFORMS::MIRANTIS          => self::CLOUD_CREDENTIALS_TYPE_OPENSTACK,
        SERVER_PLATFORMS::VIO               => self::CLOUD_CREDENTIALS_TYPE_OPENSTACK,
        SERVER_PLATFORMS::CISCO             => self::CLOUD_CREDENTIALS_TYPE_OPENSTACK,
        SERVER_PLATFORMS::VERIZON           => self::CLOUD_CREDENTIALS_TYPE_OPENSTACK,
    ];

    protected static $statusMap = [
        Entity\CloudCredentials::STATUS_DISABLED    => 'disabled',
        Entity\CloudCredentials::STATUS_ENABLED     => 'enabled',
        Entity\CloudCredentials::STATUS_SUSPENDED   => 'suspended',
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\CloudCredentials';

    public function getStatusFromObject($object)
    {
        $statuses = array_flip(static::$statusMap);

        if (!isset($statuses[$object->status])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected status value");
        }

        return $statuses[$object->status];
    }

    public function getOpenstackProvider($object)
    {
        if (empty($object->provider)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'provider'");
        }

        if (!PlatformFactory::isOpenstack($object->provider, true)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected cloud provider");
        }

        return $object->provider;
    }

    public function getCloudstackProvider($object)
    {
        if (empty($object->provider)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'provider'");
        }

        if (!PlatformFactory::isCloudstack($object->provider)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected cloud provider");
        }

        return $object->provider;
    }

    public function _cloudCredentialsType($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->cloudCredentialsType = static::$cloudsMap[$from->cloud];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                $to->cloud = array_flip(static::$cloudsMap)[$from->cloudCredentialsType];
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                switch ($from->cloudCredentialsType) {
                    case static::CLOUD_CREDENTIALS_TYPE_OPENSTACK:
                        $cloud = ['$in' => PlatformFactory::getCanonicalOpenstackPlatforms()];
                        break;

                    case static::CLOUD_CREDENTIALS_TYPE_CLOUDSTACK:
                        $cloud = ['$in' => PlatformFactory::getCloudstackBasedPlatforms()];
                        break;

                    case static::CLOUD_CREDENTIALS_TYPE_RACKSPACE:
                        $cloud = ['$in' => PlatformFactory::getRackspacePlatforms()];
                        break;

                    default:
                        $clouds = array_flip(static::$cloudsMap);

                        if (empty($clouds[$from->cloudCredentialsType])) {
                            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unknown cloudCredentialsType '{$from->cloudCredentialsType}'");
                        }

                        $cloud = $clouds[$from->cloudCredentialsType];
                        break;
                }

                return [['cloud' => $cloud]];
        }
    }

    public function _provider($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                if (PlatformFactory::isOpenstack($from->cloud, true) || PlatformFactory::isCloudstack($from->cloud)) {
                    $to->provider = $from->cloud;
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                if (!(PlatformFactory::isOpenstack($from->provider, true) || PlatformFactory::isCloudstack($from->provider))) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unknown cloud provider");
                }

                return [['cloud' => $from->provider]];
        }
    }

    public function _scope($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->scope = $from->getScope();
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                switch ($from->scope) {
                    case ScopeInterface::SCOPE_SCALR:
                        break;

                    case ScopeInterface::SCOPE_ENVIRONMENT:
                        $to->envId = $this->controller->getEnvironment()->id;
                        $to->accountId = $this->controller->getUser()->getAccountId();
                        break;

                    case ScopeInterface::SCOPE_ACCOUNT:
                        $to->accountId = $this->controller->getUser()->getAccountId();
                        break;

                    default:
                        throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                if (empty($from->scope)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed scope value");
                }

                return $this->controller->getScopeCriteria($from->scope);
        }
    }

    public function _status($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->status = static::$statusMap[$from->status];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                $to->status = $this->getStatusFromObject($from);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['status' => $this->getStatusFromObject($from)]];
        }
    }

    /**
     * @param   Entity\CloudCredentials    $entity
     * @param   Entity\CloudCredentials    $prevConfig
     *
     * @throws  ApiErrorException
     */
    public function validateEntity($entity, $prevConfig = null)
    {
        if (empty($entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Cloud credentials name cannot be empty");
        }

        $criteria = $this->controller->getScopeCriteria();
        $criteria[] = ['name' => $entity->name];
        $criteria[] = ['id' => ['$ne' => $entity->id]];

        /* @var $exists Entity\CloudCredentials */
        if ($exists = Entity\CloudCredentials::findOne($criteria)) {
            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, "Cloud credentials named '{$entity->name}' already exists in {$exists->getScope()}");
        }
    }

    /**
     * Checks that changes require re-validation of cloud credentials
     *
     * @param   SettingsCollection  $newProperties      Changed cloud credentials settings
     * @param   SettingsCollection  $currentProperties  Current cloud credentials settings
     *
     * @return  bool    Returns true if cloud credentials should be re-validated, false - otherwise
     */
    public function needValidation(SettingsCollection $newProperties, SettingsCollection $currentProperties = null)
    {
        if ($currentProperties === null) {
            return true;
        }

        $importantProperties = isset($this->rules[static::RULE_TYPE_IMPORTANT_SETTINGS])
            ? $this->rules[static::RULE_TYPE_IMPORTANT_SETTINGS]
            : array_keys($this->rules[static::RULE_TYPE_SETTINGS]);

        foreach ($importantProperties as $property) {
            if (isset($newProperties[$property]) && (
                    isset($newProperties[$property]) != isset($currentProperties[$property]) ||
                    $currentProperties[$property] != $newProperties[$property])) {
                return true;
            }
        }

        return false;
    }
}