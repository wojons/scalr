<?php

namespace Scalr\Service\Azure\Services\Compute\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Compute\DataType\AvailabilitySetData;
use Scalr\Service\Azure\Services\Compute\DataType\AvailabilitySetList;
use Scalr\Service\Azure\Services\Compute\DataType\CreateAvailabilitySet;
use Scalr\Service\Azure\Services\ComputeService;

/**
 * Azure AvailabilitySets api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class AvailabilitySets extends AbstractApi
{
    /**
     * Create an availability set for virtual machines.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group for new Availability Set
     * @param array|CreateAvailabilitySet|AvailabilitySetData $requestData    Request data object or array
     *
     * @return AvailabilitySetData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $requestData)
    {
        $result = null;

        if (!($requestData instanceof CreateAvailabilitySet) && !($requestData instanceof AvailabilitySetData)) {
            $requestData = CreateAvailabilitySet::initArray($requestData);
        }

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/availabilitySets/' . $requestData->name;

        if (empty($requestData->id)) {
            $requestData->id = $path;
        }

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestData->toArray()
        );

        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = AvailabilitySetData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Deletes an availability set.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group with Availability Set
     * @param string $name              Name of Availability Set to delete
     *
     * @return boolean True if operation finished success and False in another case
     */
    public function delete($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/availabilitySets/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Retrieves information about an availability set.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group with Availability Set
     * @param string $name              New of Availability Set
     *
     * @return AvailabilitySetData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName, $name)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/availabilitySets/' . $name;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = AvailabilitySetData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Lists all availability sets in a resource group.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group with Availability Sets
     *
     * @return AvailabilitySetList Object with response
     */
    public function getList($subscriptionId, $resourceGroupName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . '/resourceGroups/' . $resourceGroupName
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE
            . '/availabilitySets';

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new AvailabilitySetList();

            foreach ($resultArray as $array) {
                $result->append(AvailabilitySetData::initArray($array));
            }
        }

        return $result;
    }

}
