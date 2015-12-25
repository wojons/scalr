<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use DomainException;
use Exception;
use InvalidArgumentException;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity;
use Scalr\Service\Aws;
use Scalr\Service\Aws\S3\DataType\ObjectData;
use SERVER_PLATFORMS;

/**
 * Aws Detailed Billing Adapter v1beta0
 *
 * @author N.V.
 */
class AwsDetailedBillingAdapter extends ApiEntityAdapter
{

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [],

        self::RULE_TYPE_SETTINGS_PROPERTY => 'properties',
        self::RULE_TYPE_SETTINGS    => [
            Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT => 'payerAccount',
            Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION        => 'cloudLocation',
            Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET        => 'bucket'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['payerAccount', 'cloudLocation', 'bucket'],

        self::RULE_TYPE_FILTERABLE  => [],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['payerAccount' => true]],
    ];

    protected $entityClass = 'Scalr\Model\Entity\CloudCredentials';

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::toEntity()
     */
    public function toEntity($data, $entity = null)
    {
        if (empty($entity) || !$entity instanceof $this->entityClass) {
            throw new InvalidArgumentException("Second argument must be a instance of {$this->entityClass} class");
        }

        $converterRules = $this->getRules();

        if (!is_object($data)) {
            $data = (object) $data;
        }

        if (!empty($converterRules[static::RULE_TYPE_SETTINGS])) {
            $collection = $this->getSettingsCollection($entity);

            foreach ($converterRules[static::RULE_TYPE_SETTINGS] as $key => $property) {
                if (isset($data->$property)) {
                    $collection[is_int($key) ? $property : $key] = $data->$property;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see ApiEntityAdapter::toData()
     */
    public function toData($entity, $result = null)
    {
        if (empty($result) || !$result instanceof $this->dataClass) {
            throw new InvalidArgumentException("Second argument must be a instance of {$this->dataClass} class");
        }

        $converterRules = $this->getRules();

        if (!empty($converterRules[static::RULE_TYPE_SETTINGS])) {
            $collection = $this->getSettingsCollection($entity);

            foreach ($converterRules[static::RULE_TYPE_SETTINGS] as $key => $property) {
                //This is necessary when result data key does not match the property name of the entity
                $result->$property = $collection[is_int($key) ? $property : $key];
            }
        }

        return $result;
    }

    /**
     * @param Entity\CloudCredentials $entity
     *
     * @throws ApiErrorException
     */
    public function validateEntity($entity)
    {
        $container = $this->controller->getContainer();
        if ($container->analytics->enabled) {
            $ccProps = $entity->properties;
            if (!empty($ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET])) {
                try {
                    $region = $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION];

                    $aws = $container->aws($region, $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY], $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY]);

                    if (!empty($ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]) && $aws->getAccountNumber() != $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]) {
                        $payerCredentials = $this->controller->getEnvironment()->cloudCredentialsList(
                            [SERVER_PLATFORMS::EC2],
                            [['accountId' => $this->controller->getUser()->getAccountId()]],
                            [['name' => Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID], ['value' => $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]]]
                        );

                        $payerCredentials = array_shift($payerCredentials) ?: $entity;
                        $payerCcProps = $payerCredentials->properties;

                        $aws = $container->aws(
                            $region,
                            $payerCcProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY],
                            $payerCcProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY],
                            !empty($payerCcProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE])
                                ? $payerCcProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]
                                : null,
                            !empty($payerCcProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY])
                                ? $payerCcProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]
                                : null
                        );
                    }

                    try {
                        $bucketObjects = $aws->s3->bucket->listObjects($ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET]);
                    } catch (Exception $e) {
                        if (strpos($e->getMessage(), 'The authorization header is malformed') !== false) {
                            if (preg_match("/expecting\s+'(.+?)'/i", $e->getMessage(), $matches) && in_array($matches[1], Aws::getCloudLocations())) {
                                $expectingRegion = $matches[1];

                                if (isset($payerCcProps)) {
                                    $aws = $container->aws(
                                        $region,
                                        $payerCcProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY],
                                        $payerCcProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY],
                                        !empty($payerCcProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE])
                                            ? $payerCcProps[Entity\CloudCredentialsProperty::AWS_CERTIFICATE]
                                            : null,
                                        !empty($payerCcProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY])
                                            ? $payerCcProps[Entity\CloudCredentialsProperty::AWS_PRIVATE_KEY]
                                            : null
                                    );
                                } else {
                                    $aws = $container->aws($expectingRegion, $ccProps[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY], $ccProps[Entity\CloudCredentialsProperty::AWS_SECRET_KEY]);
                                }

                                $bucketObjects = $aws->s3->bucket->listObjects($ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET]);
                                $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION] = $expectingRegion;
                            } else {
                                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage(), $e->getCode(), $e);
                            }
                        } else {
                            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage(), $e->getCode(), $e);
                        }
                    }

                    $objectName = (empty($ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT])
                            ? ''
                            : "{$ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_PAYER_ACCOUNT]}-") . 'aws-billing-detailed-line-items-with-resources-and-tags';

                    $objectExists = false;
                    $bucketObjectName = null;

                    foreach ($bucketObjects as $bucketObject) {
                        /* @var $bucketObject ObjectData */
                        if (strpos($bucketObject->objectName, $objectName) !== false) {
                            $bucketObjectName = $bucketObject->objectName;
                            $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_ENABLED] = 1;
                            $objectExists = true;
                            break;
                        }
                    }

                    if (!$objectExists) {
                        throw new ApiErrorException(
                            404,
                            ErrorMessage::ERR_OBJECT_NOT_FOUND,
                            "Bucket with name '{$objectName}' does not exist"
                        );
                    }

                    $aws->s3->object->getMetadata($ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET], $bucketObjectName);
                } catch (Exception $e) {
                    throw new ApiErrorException(
                        400,
                        ErrorMessage::ERR_INVALID_VALUE,
                        sprintf(
                            "Cannot access billing bucket with name %s. Error: %s",
                            $ccProps[Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET],
                            $e->getMessage()
                        ),
                        $e->getCode(),
                        $e
                    );
                }
            }
        }
    }
}