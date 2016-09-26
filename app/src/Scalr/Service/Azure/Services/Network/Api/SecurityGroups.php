<?php

namespace Scalr\Service\Azure\Services\Network\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Network\DataType\CreateSecurityGroup;
use Scalr\Service\Azure\Services\Network\DataType\SecurityGroupData;
use Scalr\Service\Azure\Services\Network\DataType\SecurityGroupList;
use Scalr\Service\Azure\Services\NetworkService;

/**
 * Azure Security Groups api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class SecurityGroups extends AbstractApi
{
    /**
     * Lists security groups in a resource group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     *
     * @return SecurityGroupList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new SecurityGroupList();

            foreach ($resultArray as $array) {
                $result->append(SecurityGroupData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Creates security rule in a security group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     * @param array|CreateSecurityGroup  Request data
     *
     * @return SecurityGroupData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $securityGroupName,  $requestData)
    {
        $result = null;

        if (!($requestData instanceof CreateSecurityGroup)) {
            $requestData = CreateSecurityGroup::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName;

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = SecurityGroupData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Gets security group's info in a security group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     *
     * @return SecurityGroupData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $securityGroupName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = SecurityGroupData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes security rule
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     *
     * @return bool True if operation finished success and False in other case
     */
    public function delete($subscriptionId, $resourceGroupName, $securityGroupName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

}