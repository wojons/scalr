<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentials;

use Exception;
use Scalr;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentialsAdapter;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity;

/**
 * AWS Cloud Credentials Adapter v1beta0
 *
 * @author N.V.
 */
class AwsCloudCredentialsAdapter extends CloudCredentialsAdapter
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
            '_cloudCredentialsType' => 'cloudCredentialsType', '_scope' => 'scope', '_status' => 'status',
            '_billing' => 'billing'
        ],

        self::RULE_TYPE_SETTINGS_PROPERTY => 'properties',
        self::RULE_TYPE_SETTINGS    => [
            Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE   => 'accountType',
            Entity\CloudCredentialsProperty::AWS_ACCESS_KEY     => 'accessKey',
            Entity\CloudCredentialsProperty::AWS_SECRET_KEY     => '!secretKey',
            Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID     => 'accountId',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'accountType', 'accessKey', 'secretKey', 'billing'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    public function _billing($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                if ($this->controller->getContainer()->analytics->enabled && $from->properties[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED]) {
                    $to->billing = new $this->dataClass();
                    $this->controller->adapter('awsDetailedBilling')->toData($from, $to->billing);
                }
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                if ($this->controller->getContainer()->analytics->enabled) {
                    if ($from->billing === false) {
                        $to->properties[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED] = false;
                    } else {
                        $to->properties[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED] = true;

                        $adapter = $this->controller->adapter('awsDetailedBilling');

                        $adapter->toEntity($from->billing, $to);
                    }
                }
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ ]];
        }
    }

    /**
     * @param   Entity\CloudCredentials $entity
     * @param   Entity\CloudCredentials $prevConfig
     *
     * @throws  ApiErrorException
     * @throws  Exception
     * @throws  Scalr\Service\Aws\Client\ClientException
     */
    public function validateEntity($entity, $prevConfig = null)
    {
        parent::validateEntity($entity, $prevConfig);

        $ccProps = $entity->properties;
        $prevCcProps = isset($prevConfig) ? $prevConfig->properties : null;

        if ($this->needValidation($ccProps, $prevCcProps)) {
            if (empty($ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE])) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property accountType");
            }

            if (!in_array(
                $ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE],
                [
                    Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_REGULAR,
                    Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD,
                    Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD
                ]
            )) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected account type {$ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE]}");
            }


            switch ($ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE]) {
                case Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD:
                    $region = \Scalr\Service\Aws::REGION_US_GOV_WEST_1;
                    break;

                case Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD:
                    $region = \Scalr\Service\Aws::REGION_CN_NORTH_1;
                    break;

                default:
                    $region = \Scalr\Service\Aws::REGION_US_EAST_1;
                    break;
            }

            //Validates both access and secret keys
            try {
                $aws = $this->controller->getContainer()->aws(
                    $region,
                    $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY],
                    $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY]
                );

                $aws->s3->bucket->getList();
            } catch (Exception $e) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Failed to verify your AWS Cloud Credentials: {$e->getMessage()}");
            }

            //Extract AWS Account ID
            $awsAccountId = $aws->getAccountNumber();

            if (($prevAwsAccountId = $prevCcProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID]) &&
                $awsAccountId != $prevAwsAccountId &&
                $prevConfig->isUsed()) {
                throw new ApiErrorException(400, ErrorMessage::ERR_OBJECT_IN_USE, "Change AWS Account ID aren't possible while this cloud credentials is in use");
            }

            $ccProps[Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID] = $awsAccountId;

            $entity->status = Entity\CloudCredentials::STATUS_ENABLED;

            if ($ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED]) {
                $this->controller->adapter('awsDetailedBilling')->validateEntity($entity);
            }
        }
    }
}