<?php

namespace Scalr\Service\Azure\Services\ResourceManager\Api;

use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\ResourceManager\DataType\ResourceGroupData;
use Scalr\Service\Azure\Services\ResourceManager\DataType\ResourceGroupList;

/**
 * Azure Resource Groups class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class ResourceGroups extends AbstractApi
{
    /**
     * Create a resource group in the specified subscription.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param string $location          Specifies the supported Azure location for the resource group
     * @param array  $tags              optional Key-value array with tags, related to new Resource Group
     *
     * @return ResourceGroupData Object with response
     */
    public function create($subscriptionId, $resourceGroupName, $location, array $tags = [])
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/resourcegroups/' . $resourceGroupName;

        $requestArr = [
            'name'      => $resourceGroupName,
            'location'  => $location,
        ];

        if (count($tags)) {
            $requestArr['tags'] = $tags;
        }

        $request = $this->getClient()->prepareRequest(
            $path, 'PUT', $this->getApiVersion(),
            $this->getServiceUrl(), [], $requestArr
        );

        $response = $this->getClient()->call($request);
        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        if (!$response->hasError()) {
            $result = ResourceGroupData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * Delete a resource group from the specified subscription.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     *
     * @return object Object with response
     */
    public function delete($subscriptionId, $resourceGroupName)
    {
        $path = '/subscriptions/' . $subscriptionId . '/resourcegroups/' . $resourceGroupName;

        $request = $this->getClient()->prepareRequest($path, 'DELETE', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        return (!$response->hasError() && $response->getResponseCode() == 200) ? true : false;
    }

    /**
     * Get information about the specified resource group.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     *
     * @return ResourceGroupData Object with response
     */
    public function getInfo($subscriptionId, $resourceGroupName)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/resourcegroups/' . $resourceGroupName;

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion());
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $result = ResourceGroupData::initArray($response->getResult());
        }

        return $result;
    }

    /**
     * List all of the resource groups that are defined in the specified subscription.
     *
     * @param string $subscriptionId Subscription Id
     * @param int    $top            optional Max count limit of returned items
     * @param string $skiptoken      optional Skiptoken is only used if a partial result is returned in a previous operation call.
     *                               If the response contains a nextLink element, the value of the nextLink element includes a $skiptoken parameter with a value
     *                               that specifies the starting point in the collection of entities for the next GET operation.
     *                               The $skiptoken parameter must only be used on the URI contained in the value of the nextLink element.
     * @param string $filter         optional Filter can be used to restrict the results to specific tagged resources. The following possible values can be used with $filter:
     *                               $filter=tagname eq {value}
     *                               $filter=tagname eq {tagname} and tagvalue eq {tagvalue}
     *                               $filter=startswith(tagname, {tagname prefix})
     *
     * @return ResourceGroupList Object with response
     */
    public function getList($subscriptionId, $top = null, $skiptoken = null, $filter = null)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/resourcegroups';

        $queryData = [];

        if ($top) {
            $queryData['top'] = $top;
        }

        if ($skiptoken) {
            $queryData['skiptoken'] = $skiptoken;
        }

        if ($filter) {
            $queryData['filter'] = $filter;
        }

        $request = $this->getClient()->prepareRequest($path, 'GET', $this->getApiVersion(), $this->getServiceUrl(), $queryData);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new ResourceGroupList();

            foreach ($resultArray as $array) {
                $result->append(ResourceGroupData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * Update the properties that are assigned to the specified resource group.
     *
     * @param string $subscriptionId    Subscription Id
     * @param string $resourceGroupName Name of Resource Group
     * @param array  $tags              optional Key-value array with tags, related to new Resource Group
     *
     * @return ResourceGroupData Object with response
     */
    public function update($subscriptionId, $resourceGroupName, $tags = [])
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId . '/resourcegroups/' . $resourceGroupName;

        $patchData = [];

        if (count($tags)) {
            $patchData['tags'] = $tags;
        }

        $request = $this->getClient()->prepareRequest(
            $path, 'PATCH', $this->getApiVersion(),
            $this->getServiceUrl(), [], $patchData
        );

        $response = $this->getClient()->call($request);

        $response = $this->getClient()->waitFinishingProcess($response, $this->getServiceUrl(), $this->getApiVersion());

        if (!$response->hasError()) {
            $result = ResourceGroupData::initArray($response->getResult());
        }

        return $result;
    }
}
