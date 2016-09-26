<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentials;

use Exception;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentialsAdapter;
use Scalr\Model\Entity;

/**
 * Gce Cloud Credentials Adapter v1beta0
 *
 * @author N.V.
 */
class GceCloudCredentialsAdapter extends CloudCredentialsAdapter
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
            Entity\CloudCredentialsProperty::GCE_PROJECT_ID            => 'projectId',
            Entity\CloudCredentialsProperty::GCE_CLIENT_ID             => 'clientId',
            Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME  => 'accountName',
            Entity\CloudCredentialsProperty::GCE_KEY                   => '!privateKey'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'projectId', 'clientId', 'accountName', 'privateKey'],

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
        parent::validateEntity($entity, $prevConfig);

        $ccProps = $entity->properties;
        $prevCcProps = isset($prevConfig) ? $prevConfig->properties : null;

        if ($this->needValidation($ccProps, $prevCcProps)) {
            $ccProps[Entity\CloudCredentialsProperty::GCE_ACCESS_TOKEN] = "";

            try {
                $client = new \Google_Client();
                $client->setApplicationName("Scalr GCE");
                $client->setScopes(['https://www.googleapis.com/auth/compute']);

                $key = base64_decode($ccProps[Entity\CloudCredentialsProperty::GCE_KEY]);
                // If it's not a json key we need to convert PKCS12 to PEM
                if (!$ccProps[Entity\CloudCredentialsProperty::GCE_JSON_KEY]) {
                    @openssl_pkcs12_read($key, $certs, 'notasecret');
                    $key = $certs['pkey'];
                }
                
                $client->setAuthConfig([
                    'type' => 'service_account',
                    'project_id' => $ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID],
                    'private_key' => $key,
                    'client_email' => $ccProps[Entity\CloudCredentialsProperty::GCE_SERVICE_ACCOUNT_NAME],
                    'client_id' => $ccProps[Entity\CloudCredentialsProperty::GCE_CLIENT_ID]
                ]);
                
                $client->setClientId($ccProps[Entity\CloudCredentialsProperty::GCE_CLIENT_ID]);

                $gce = new \Google_Service_Compute($client);

                $gce->zones->listZones($ccProps[Entity\CloudCredentialsProperty::GCE_PROJECT_ID]);

            } catch (Exception $e) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Provided GCE credentials are incorrect: ({$e->getMessage()})");
            }

            $entity->status = Entity\CloudCredentials::STATUS_ENABLED;
        }
    }
}