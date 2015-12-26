<?php

namespace Scalr\Service\Azure\Services\Network\Api;

use Scalr\Service\Azure;
use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Network\DataType\CreateInterface;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceData;
use Scalr\Service\Azure\Services\Network\DataType\InterfaceList;
use Scalr\Service\Azure\Services\NetworkService;

/**
 * Azure Interfaces api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class Interfaces extends AbstractApi
{
    /**
     * Lists all networks in a subscription or resource group.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName optional Name of Resource Group
     *
     * @return InterfaceList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName = null)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId;

        if (isset($resourceGroupName)) {
            $path .= '/resourceGroups/' . $resourceGroupName;
        }

        $path .= NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/networkInterfaces';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new InterfaceList();

            foreach ($resultArray as $array) {
                $result->append(InterfaceData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Retrieves information about a network.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name of the network
     * @param bool   $expandPublicIp    If true - adds public ip info to response
     *
     * @return InterfaceData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $name, $expandPublicIp = false)
    {
        $result = null;
        $queryData = [];

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkInterfaces/' . $name;
        
        if ($expandPublicIp) {
            $queryData['$expand'] = 'ipConfigurations/publicIPAddress';
        }

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion(), Azure::URL_MANAGEMENT_WINDOWS, $queryData);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = InterfaceData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Create a virtual network interface for virtual machines.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name for new network interface
     * @param array|CreateInterface|InterfaceData  $requestData  Request data
     *
     * @return InterfaceData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $name, $requestData)
    {
        $result = null;

        if (!($requestData instanceof CreateInterface) && !($requestData instanceof InterfaceData)) {
            $requestData = CreateInterface::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkInterfaces/' . $name;

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = InterfaceData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes a virtual network interface.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $name              Name of Network interface to delete
     *
     * @return boolean True if operation finished success and False in another case
     */
    public function delete($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkInterfaces/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

}