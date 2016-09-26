<?php

namespace Scalr\Service\Azure\Services\Storage\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Storage\DataType\AccountData;
use Scalr\Service\Azure\Services\Storage\DataType\AccountList;
use Scalr\Service\Azure\Services\StorageService;

/**
 * Azure Accounts class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class Accounts extends AbstractApi
{
    /**
     * Create a storage account.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name for storage account
     * @param array|AccountData  $requestData  Request data
     *
     * @return AccountData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $name, $requestData)
    {
        $result = null;

        if (!($requestData instanceof AccountData)) {
            $requestData = AccountData::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . StorageService::ENDPOINT_MICROSOFT_STORAGE
            . '/storageAccounts/' . $name;

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        if (!$response->hasError()) {
            $result = AccountData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Lists all storage accounts in a subscription or a resource group.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName optional Name of Resource Group
     *
     * @return AccountList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName = null)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId;

        if (isset($resourceGroupName)) {
            $path .= '/resourceGroups/' . $resourceGroupName;
        }

        $path .= StorageService::ENDPOINT_MICROSOFT_STORAGE . '/storageAccounts';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();
            $result = new AccountList();

            foreach ($resultArray as $array) {
                $result->append(AccountData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Retrieves information about a storage account.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name of the account
     *
     * @return AccountData Object with response
     */
    public function getProperties($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . StorageService::ENDPOINT_MICROSOFT_STORAGE
            . '/storageAccounts/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = AccountData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Retrieves keys of a storage account.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name of the account
     *
     * @return object Object with response {key1:value1}
     */
    public function getKeys($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . StorageService::ENDPOINT_MICROSOFT_STORAGE
            . '/storageAccounts/' . $name . '/listKeys';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = $response->getResult();
        }

        return (object) $result;
    }

    /**
     * Deletes a storage account.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name of Storage account to delete
     *
     * @return boolean True if operation finished success and False in another case
     */
    public function delete($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . StorageService::ENDPOINT_MICROSOFT_STORAGE
            . '/storageAccounts/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

}