<?php

namespace Scalr\Service\Azure\Services\Network\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Network\DataType\CreatePublicIpAddress;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceIpConfigurationsData;
use Scalr\Service\Azure\Services\Network\DataType\PublicIpAddressData;
use Scalr\Service\Azure\Services\Network\DataType\PublicIpAddressList;
use Scalr\Service\Azure\Services\NetworkService;

/**
 * Azure Interfaces api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class PublicIPAddresses extends AbstractApi
{
    /**
     * Lists all public ip addresses in a subscription or resource group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  optional Name of Resource Group
     *
     * @return PublicIpAddressList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName = null)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId;

        if (isset($resourceGroupName)) {
            $path .= '/resourceGroups/' . $resourceGroupName;
        }

        $path .= NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/publicIPAddresses';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new PublicIpAddressList();

            foreach ($resultArray as $array) {
                $result->append(PublicIpAddressData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Retrieves information about a public ip addresses.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name of the public ip addresses
     *
     * @return PublicIpAddressData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/publicIPAddresses/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = PublicIpAddressData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Create a subnet for public ip addresses.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name for new public ip addresses
     * @param array|CreatePublicIpAddress|PublicIpAddressData  $requestData      Request data
     *
     * @return PublicIpAddressData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $name, $requestData)
    {
        $result = null;

        if (!($requestData instanceof CreatePublicIpAddress) && !($requestData instanceof PublicIpAddressData)) {
            $requestData = CreatePublicIpAddress::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/publicIPAddresses/' . $name;

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = PublicIpAddressData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes a public ip addresses.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group with Availability Set
     * @param string $name              Name of public ip addresses to delete
     *
     * @return boolean True if operation finished success and False in another case
     */
    public function delete($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/publicIPAddresses/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Associate public ip address with nic
     *
     * @param string $subscriptionId        Subscription Id
     * @param string $resourceGroupName     Name of Resource Group
     * @param string $nicName               Name for nic
     * @param string $publicIpName          Name for public ip addresses
     * @return bool
     * @throws \Scalr\Service\AzureException
     * @throws \Scalr\Service\Azure\Exception\RestApiException
     */
    public function associate($subscriptionId, $resourceGroupName, $nicName, $publicIpName)
    {
        $result = null;

        $nicInfo = $this->getAzure()->network->interface->getInfo($subscriptionId, $resourceGroupName, $nicName);

        $publicIpId = "/subscriptions/" . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . "/publicIPAddresses/" . $publicIpName;

        foreach ($nicInfo->properties->ipConfigurations as &$ipConfig) {
            /* @var $ipConfig InterfaceIpConfigurationsData */
            // NOTE works properly only with one ipConfiguration set
            if (empty($ipConfig->properties->publicIPAddress)) {
                $ipConfig->properties->publicIPAddress = [];
            }

            $ipConfig->properties->publicIPAddress->id = $publicIpId;
            $result = $this->getAzure()->network->interface->create($subscriptionId, $resourceGroupName, $nicName, $nicInfo);

            break;
        }

        return $result ? true : false;
    }

    /**
     * Remove public ip address from nic
     *
     * @param string $subscriptionId        Subscription Id
     * @param string $resourceGroupName     Name of Resource Group
     * @param string $nicName               Name for nic
     * @param string $publicIpName          Name for public ip addresses
     * @return bool
     * @throws \Scalr\Service\AzureException
     */
    public function disassociate($subscriptionId, $resourceGroupName, $nicName, $publicIpName)
    {
        $result = null;

        $nicInfo = $this->getAzure()->network->interface->getInfo($subscriptionId, $resourceGroupName, $nicName);

        $publicIpId = "/subscriptions/" . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . "/publicIPAddresses/" . $publicIpName;

        foreach ($nicInfo->properties->ipConfigurations as &$ipConfig) {
            /* @var $ipConfig InterfaceIpConfigurationsData */
            if (!empty($ipConfig->properties->publicIPAddress->id) && $ipConfig->properties->publicIPAddress->id == $publicIpId) {
                unset($ipConfig->properties->publicIPAddress);

                $result = $this->getAzure()->network->interface->create($subscriptionId, $resourceGroupName, $nicName, $nicInfo);
                break;
            }
        }

        return $result ? true : false;
    }

}