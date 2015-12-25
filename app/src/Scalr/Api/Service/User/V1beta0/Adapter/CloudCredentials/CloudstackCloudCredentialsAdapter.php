<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentials;

use Exception;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Service\User\V1beta0\Adapter\CloudCredentialsAdapter;
use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use Scalr\Service\CloudStack\CloudStack;
use Scalr\Service\CloudStack\DataType\AccountList;
use Scalr\Service\CloudStack\DataType\ListAccountsData;

/**
 * Cloudstack Cloud Credentials Adapter v1beta0
 *
 * @author N.V.
 */
class CloudstackCloudCredentialsAdapter extends CloudCredentialsAdapter
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
            '_cloudCredentialsType' => 'cloudCredentialsType', '_provider' => 'provider', '_scope' => 'scope', '_status' => 'status'
        ],

        self::RULE_TYPE_SETTINGS_PROPERTY => 'properties',
        self::RULE_TYPE_SETTINGS    => [
            Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL    => 'apiUrl',
            Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY    => 'apiKey',
            Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY => '!secretKey'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name', 'description', 'apiUrl', 'apiKey', 'secretKey'],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['id' => true]],
    ];

    public function getCloudstackProvider($object)
    {
        if (empty($object->provider)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'provider'");
        }

        if (!in_array($object->provider, PlatformFactory::getCloudstackBasedPlatforms())) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected provider");
        }

        return $object->provider;
    }

    public function _cloudCredentialsType($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from Entity\CloudCredentials */
                $to->cloudCredentialsType = static::CLOUD_CREDENTIALS_TYPE_CLOUDSTACK;
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to Entity\CloudCredentials */
                $to->cloud = $this->getCloudstackProvider($from);
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [['cloud' => ['$in' => PlatformFactory::getCloudstackBasedPlatforms()]]];
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
                return [['cloud' => $this->getCloudstackProvider($from)]];
        }
    }

    /**
     * Searches a Cloudstack user name from the accounts list by api key and sets properties
     *
     * @param   AccountList $accounts   Accounts list
     * @param   array       $pars       Cloudstack properties
     *
     * @return  bool    Returns true if api key is found, false - otherwise
     */
    private function searchCloudstackUser(AccountList $accounts = null, &$pars)
    {
        if (!empty($accounts)) {
            foreach ($accounts as $account) {
                foreach ($account->user as $user) {
                    if ($user->apikey == $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY]) {
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_ACCOUNT_NAME] = $user->account;
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_NAME] = $user->domain;
                        $pars[Entity\CloudCredentialsProperty::CLOUDSTACK_DOMAIN_ID] = $user->domainid;

                        return true;
                    }
                }
            }
        }

        return false;
    }

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
            try {
                $cs = new CloudStack(
                    $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_URL],
                    $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_API_KEY],
                    $ccProps[Entity\CloudCredentialsProperty::CLOUDSTACK_SECRET_KEY],
                    $entity->cloud
                );

                $listAccountsData = new ListAccountsData();
                $listAccountsData->listall = true;
            } catch (Exception $e) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Failed to verify your Cloudstack credentials: {$e->getMessage()}");
            }

            if (!$this->searchCloudstackUser($cs->listAccounts($listAccountsData), $ccProps)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Cannot determine account name for provided keys");
            }

            $entity->status = Entity\CloudCredentials::STATUS_ENABLED;
        }
    }
}