<?php

namespace Scalr\Service\Azure\Services\Network\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Network\DataType\CreateVirtualNetwork;
use Scalr\Service\Azure\Services\Network\DataType\VirtualNetworkData;
use Scalr\Service\Azure\Services\Network\DataType\VirtualNetworkList;
use Scalr\Service\Azure\Services\NetworkService;

/**
 * Azure VirtualNetworks api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class VirtualNetworks extends AbstractApi
{
    /**
     * Lists all virtual networks in a subscription or resource group.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName optional Name of Resource Group
     *
     * @return VirtualNetworkList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName = null)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId;

        if (isset($resourceGroupName)) {
            $path .= '/resourceGroups/' . $resourceGroupName;
        }

        $path .= NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/virtualNetworks';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new VirtualNetworkList();

            foreach ($resultArray as $array) {
                $result->append(VirtualNetworkData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Retrieves information about a virtual networks.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name of the virtual network
     *
     * @return VirtualNetworkData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/virtualNetworks/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = VirtualNetworkData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Create a virtual network for virtual machines.
     *
     * @param string $subscriptionId      Subscription Id
     * @param string $resourceGroupName   Name of Resource Group
     * @param array|CreateVirtualNetwork|VirtualNetworkData  $requestData  Request data
     *
     * @return VirtualNetworkData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $requestData)
    {
        $result = null;

        if (!($requestData instanceof CreateVirtualNetwork) && !($requestData instanceof VirtualNetworkData)) {
            $requestData = CreateVirtualNetwork::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/virtualNetworks/' . $requestData->name;

        if (empty($requestData->id)) {
            $requestData->id = $path;
        }

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = VirtualNetworkData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes a virtual network.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group with Availability Set
     * @param string $name              Name of Network to delete
     *
     * @return boolean True if operation finished success and False in another case
     */
    public function delete($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/virtualNetworks/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

}