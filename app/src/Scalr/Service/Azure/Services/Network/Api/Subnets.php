<?php

namespace Scalr\Service\Azure\Services\Network\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Network\DataType\SubnetData;
use Scalr\Service\Azure\Services\Network\DataType\SubnetList;
use Scalr\Service\Azure\Services\NetworkService;

/**
 * Azure Subnets api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class Subnets extends AbstractApi
{
    /**
     * Lists all subnets in a subscription.
     *
     * @param string $subscriptionId       Subscription Id
     * @param string $resourceGroupName    Name of Resource Group
     * @param string $virtualNetworkName   Name of the virtual network
     *
     * @return SubnetList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName, $virtualNetworkName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/virtualNetworks/'
            . $virtualNetworkName . '/subnets';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = new SubnetList();

            $resultArray = $response->getResult();

            foreach ($resultArray as $array) {
                $result->append(SubnetData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Retrieves information about a subnet.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $virtualNetworkName Name of the virtual network
     * @param string $name               Name of the subnet
     *
     * @return SubnetData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $virtualNetworkName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/virtualNetworks/'
            . $virtualNetworkName . '/subnets/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = SubnetData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Create a subnet for subnet.
     *
     * @param string $subscriptionId       Subscription Id
     * @param string $resourceGroupName    Name of Resource Group
     * @param string $virtualNetworkName   Name of the virtual network
     * @param string $name                 Name for new subnet
     * @param string $addressPrefix        optional Address prefix for the subnet
     * @param array  $networkSecurityGroup optional URI of the network security group resource
     *                                     Example Format: ["id" => "/subscriptions/{guid}/../microsoft.network/networkSecurityGroups/myNSG1"]
     *
     * @return SubnetData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $virtualNetworkName, $name, $addressPrefix = null, array $networkSecurityGroup = null)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/virtualNetworks/'
            . $virtualNetworkName . '/subnets/' . $name;

        $requestData = [];

        if (isset($addressPrefix)) {
            $requestData['properties']['addressPrefix'] = $addressPrefix;
        }

        if (isset($networkSecurityGroup)) {
            $requestData['properties']['networkSecurityGroup'] = $networkSecurityGroup;
        }

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = SubnetData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes a subnet.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group with Availability Set
     * @param string $virtualNetworkName Name of the virtual network
     * @param string $name               Name of Subnet to delete
     *
     * @return boolean True if operation finished success and False in another case
     */
    public function delete($subscriptionId, $resourceGroupName, $virtualNetworkName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/virtualNetworks/'
            . $virtualNetworkName . '/subnets/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

}