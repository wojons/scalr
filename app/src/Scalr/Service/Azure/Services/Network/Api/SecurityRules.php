<?php

namespace Scalr\Service\Azure\Services\Network\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Network\DataType\CreateSecurityRule;
use Scalr\Service\Azure\Services\Network\DataType\SecurityRuleData;
use Scalr\Service\Azure\Services\Network\DataType\SecurityRuleList;
use Scalr\Service\Azure\Services\Network\DataType\SecurityRuleProperties;
use Scalr\Service\Azure\Services\NetworkService;

/**
 * Azure SecurityRules api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class SecurityRules extends AbstractApi
{
    /**
     * Lists security rules in a resource group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     *
     * @return SecurityRuleList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName, $securityGroupName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName . '/securityRules';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new SecurityRuleList();

            foreach ($resultArray as $array) {
                $result->append(SecurityRuleData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Lists default security rules in a resource group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     *
     * @return SecurityRuleList Object with response
     */
    public function getDefaultList($subscriptionId, $resourceGroupName, $securityGroupName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK . '/networkSecurityGroups/'
            . $securityGroupName . '/defaultSecurityRules';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new SecurityRuleList();

            foreach ($resultArray as $array) {
                $result->append(SecurityRuleData::initArray($array));
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
     * @param string $securityRuleName   Name of Security Rule
     * @param array|CreateSecurityRule   Request data
     *
     * @return SecurityRuleProperties Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $securityGroupName, $securityRuleName,  $requestData)
    {
        $result = null;

        if (!($requestData instanceof CreateSecurityRule)) {
            $requestData = CreateSecurityRule::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName
            . '/securityRules/' . $securityRuleName;

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = SecurityRuleProperties::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Gets security rule's info in a security group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     * @param string $securityRuleName   Name of Security Rule
     *
     * @return SecurityRuleData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $securityGroupName, $securityRuleName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName
            . '/securityRules/' . $securityRuleName;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = SecurityRuleData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Gets default security rule's info in a security group.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     * @param string $securityRuleName   Name of Security Rule
     *
     * @return SecurityRuleData Object with response
     */
    public function getInfoDefault($subscriptionId, $resourceGroupName, $securityGroupName, $securityRuleName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName
            . '/defaultSecurityRules/' . $securityRuleName;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = SecurityRuleData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes security rule
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $securityGroupName  Name of Security Group
     * @param string $securityRuleName   Name of Security Rule
     *
     * @return bool True if operation finished success and False in other case
     */
    public function delete($subscriptionId, $resourceGroupName, $securityGroupName, $securityRuleName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . NetworkService::ENDPOINT_MICROSOFT_NETWORK
            . '/networkSecurityGroups/' . $securityGroupName
            . '/securityRules/' . $securityRuleName;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

}