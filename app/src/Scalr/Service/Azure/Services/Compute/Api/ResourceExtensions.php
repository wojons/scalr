<?php

namespace Scalr\Service\Azure\Services\Compute\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Compute\DataType\CreateResourceExtension;
use Scalr\Service\Azure\Services\Compute\DataType\ResourceExtensionData;
use Scalr\Service\Azure\Services\ComputeService;

/**
 * Azure ResourceExtensions api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class ResourceExtensions extends AbstractApi
{
    /**
     * Create or update a virtual machine extension.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $virtualMachineName Name of Virtual Machine
     * @param array|CreateResourceExtension|ResourceExtensionData $requestData  Request data
     *
     * @return ResourceExtensionData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $virtualMachineName, $requestData)
    {
        $result = null;

        if (!($requestData instanceof CreateResourceExtension) && !($requestData instanceof ResourceExtensionData)) {
            $requestData = CreateResourceExtension::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $virtualMachineName
            . '/extensions/' . $requestData->name;

        if (empty($requestData->id)) {
            $requestData->id = $path;
        }

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = ResourceExtensionData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes a virtual machine extension.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $virtualMachineName Name of Virtual Machine
     * @param string $extensionName      Name of Extension
     *
     * @return bool True if resource extension is deleted successfully, otherwise False
     */
    public function delete($subscriptionId, $resourceGroupName, $virtualMachineName, $extensionName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $virtualMachineName
            . '/extensions/' . $extensionName;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Get information about a virtual machine extension.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroupName  Name of Resource Group
     * @param string $virtualMachineName Name of Virtual Machine
     * @param string $extensionName      Name of Extension
     *
     * @return ResourceExtensionData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $virtualMachineName, $extensionName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $virtualMachineName
            . '/extensions/' . $extensionName;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = ResourceExtensionData::initArray($response->getResult());
        }

        return $result;
    }
}
