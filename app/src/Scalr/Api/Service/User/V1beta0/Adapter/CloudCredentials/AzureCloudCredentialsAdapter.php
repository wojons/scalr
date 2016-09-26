<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentials;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentialsAdapter;
use Scalr\Model\Entity;

/**
 * Azure Cloud Credentials Adapter v1beta0
 *
 * @author N.V.
 */
class AzureCloudCredentialsAdapter extends CloudCredentialsAdapter
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
            'id', 'name', 'description',
            '_cloudCredentialsType' => 'cloudCredentialsType', '_scope' => 'scope', '_status' => 'status'
        ],

        self::RULE_TYPE_SETTINGS_PROPERTY => 'properties',
        self::RULE_TYPE_SETTINGS    => [
            Entity\CloudCredentialsProperty::AZURE_TENANT_NAME     => 'tenantName',
            Entity\CloudCredentialsProperty::AZURE_SUBSCRIPTION_ID => 'subscription',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'tenantName', 'subscription'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    /**
     * @param   Entity\CloudCredentials $entity
     * @param   Entity\CloudCredentials $prevConfig
     *
     * @throws  ApiErrorException
     */
    public function validateEntity($entity, $prevConfig = null)
    {
        throw new ApiErrorException(501, ErrorMessage::ERR_NOT_IMPLEMENTED, "MS Azure allows only OAuth authentication");
    }
}