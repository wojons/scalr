<?php

namespace Scalr\Service\Azure\Services\Compute\Api;

use Scalr\Service\Azure;
use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Compute\DataType\CreateVirtualMachine;
use Scalr\Service\Azure\Services\Compute\DataType\SaveImage;
use Scalr\Service\Azure\Services\Compute\DataType\VirtualInstanceViewData;
use Scalr\Service\Azure\Services\Compute\DataType\VirtualMachineData;
use Scalr\Service\Azure\Services\Compute\DataType\VirtualMachineList;
use Scalr\Service\Azure\Services\ComputeService;

/**
 * Azure VirtualMachines api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class VirtualMachines extends AbstractApi
{
    /**
     * Create or update a virtual machine in a given subscription.
     * In the update scenario, these APIs will be specifically used for
     * detaching a data disk from a VM.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param array|CreateVirtualMachine|VirtualMachineData $requestData       Request data
     * @param bool   $validating        optional Validating
     * @return VirtualMachineData
     */
    public function create($subscriptionId, $resourceGroup, $requestData, $validating = false)
    {
        $result = null;

        if (!($requestData instanceof CreateVirtualMachine) && !($requestData instanceof VirtualMachineData)) {
            $requestData = CreateVirtualMachine::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $requestData->name;

        if (empty($requestData->id)) {
            $requestData->id = $path;
        }

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), ['validating' => $validating], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = VirtualMachineData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Lists all of the virtual machine in the specified resource group.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     *
     * @return VirtualMachineList Object with list of Virtual Machines
     */
    public function getList($subscriptionId, $resourceGroup)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new VirtualMachineList();

            foreach ($resultArray as $array) {
                $result->append(VirtualMachineData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Get information about the model view of a virtual machine.
     *
     * @param string $subscriptionId     Subscription Id
     * @param string $resourceGroup      Name of Resource Group
     * @param string $name               Name of the needle Virtual Machine
     * @param bool   $expandInstanceView If true - adds instance view info to response
     *
     * @return VirtualMachineData Object with Virtual Machine model-view info
     */
    public function getModelViewInfo($subscriptionId, $resourceGroup, $name, $expandInstanceView = true)
    {
        $result = null;
        $queryData = [];

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name;

        if ($expandInstanceView) {
            $queryData['$expand'] = 'instanceView';
        }

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion(), $this->getServiceUrl(), $queryData);

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = VirtualMachineData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Get information about the instance view of a virtual machine.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param string $name              Name of the needle Virtual Machine
     *
     * @return VirtualInstanceViewData Object with Virtual Machine instance-view info
     */
    public function getInstanceViewInfo($subscriptionId, $resourceGroup, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name . '/InstanceView';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = VirtualInstanceViewData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes a virtual machine.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param string $name              Name of the Virtual Machine
     *
     * @return bool True if VM is deleted successful, otherwise False
     */
    public function delete($subscriptionId, $resourceGroup, $name)
    {
        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Start a virtual machine.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param string $name              Name of the Virtual Machine
     *
     * @return bool True if VM is started successful, otherwise False
     */
    public function start($subscriptionId, $resourceGroup, $name)
    {
        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name . '/start';

        $request = $this->getClient()->prepareRequest($path, 'POST', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Stop a virtual machine.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param string $name              Name of the Virtual Machine
     *
     * @return bool True if VM is stopped successful, otherwise False
     */    
    public function poweroff($subscriptionId, $resourceGroup, $name)
    {
        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name . '/powerOff';

        $request = $this->getClient()->prepareRequest($path, 'POST', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Stop and deallocate a virtual machine.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param string $name              Name of the Virtual Machine
     *
     * @return bool True if operation finished successful, otherwise False
     */
    public function deallocate($subscriptionId, $resourceGroup, $name)
    {
        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name . '/deallocate';

        $request = $this->getClient()->prepareRequest($path, 'POST', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Restart a virtual machine.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param string $name              Name of the Virtual Machine
     *
     * @return bool True if VM is restarted successful, otherwise False
     */
    public function restart($subscriptionId, $resourceGroup, $name)
    {
        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name . '/restart';

        $request = $this->getClient()->prepareRequest($path, 'POST', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Save an image that is associated with a generalized virtual machine.
     *
     * @param string          $subscriptionId           Subscription Id
     * @param string          $resourceGroup            Name of Resource Group
     * @param string          $name                     Name of the Virtual Machine
     * @param array|SaveImage $requestData              Request data
     *
     * @return string Returns image uri
     */
    public function saveImage($subscriptionId, $resourceGroup, $name, $requestData)
    {
        if (!($requestData instanceof SaveImage)) {
            $requestData = SaveImage::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name . '/capture';

        $request = $this->getClient()->prepareRequest(
            $path, 'POST', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        $imageUri = null;

        if (!$response->hasError()) {
            $resource = $response->getResult();

            if (!empty($resource['resources'])) {
                $vmData = VirtualMachineData::initArray(reset($resource['resources']));
                /* @var $vmData VirtualMachineData */
                if (!empty($vmData->properties->storageProfile->osDisk->image)) {
                    $imageUri = $vmData->properties->storageProfile->osDisk->image['uri'];
                }
            }
        }

        return $imageUri;
    }

    /**
     * This API is used to mark a Virtual Machine as generalized in Azure.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroup     Name of Resource Group
     * @param string $name              Name of the Virtual Machine
     *
     * @return bool True if VM is generalized successful, otherwise False
     */
    public function generalize($subscriptionId, $resourceGroup, $name)
    {
        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroup
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/virtualMachines/' . $name . '/generalize';

        $request = $this->getClient()->prepareRequest($path, 'POST', $this->getApiVersion(), $this->getServiceUrl());

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

}
