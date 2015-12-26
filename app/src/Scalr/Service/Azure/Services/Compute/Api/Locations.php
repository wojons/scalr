<?php

namespace Scalr\Service\Azure\Services\Compute\Api;

use Scalr\Service\Azure;
use Scalr\Service\Azure\Services\AbstractApi;
use Scalr\Service\Azure\Services\Compute\DataType\InstanceTypeData;
use Scalr\Service\Azure\Services\Compute\DataType\InstanceTypeList;
use Scalr\Service\Azure\Services\Compute\DataType\OfferData;
use Scalr\Service\Azure\Services\Compute\DataType\OfferList;
use Scalr\Service\Azure\Services\Compute\DataType\PublisherData;
use Scalr\Service\Azure\Services\Compute\DataType\PublisherList;
use Scalr\Service\Azure\Services\Compute\DataType\SkuData;
use Scalr\Service\Azure\Services\Compute\DataType\SkuList;
use Scalr\Service\Azure\Services\Compute\DataType\VersionData;
use Scalr\Service\Azure\Services\Compute\DataType\VersionList;
use Scalr\Service\Azure\Services\ComputeService;

/**
 * Azure Locations api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class Locations extends AbstractApi
{
    /**
     * List all of available instance types (Hardware profiles).
     *
     * @param string $subscriptionId Subscription Id
     * @param string $location       Location (one of provided)
     *
     * @return InstanceTypeList Object with API response
     */
    public function getInstanceTypesList($subscriptionId, $location)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE . '/locations/'
            . $location . '/vmSizes';

        $request = $this->getClient()->prepareRequest($path, 'GET', ComputeService::RESOURCE_API_VERSION);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new InstanceTypeList();

            foreach ($resultArray as $array) {
                $result->append(InstanceTypeData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * List all of available publishers.
     *
     * @param string $subscriptionId Subscription Id
     * @param string $location       Location
     *
     * @return PublisherList Object with API response
     */
    public function getPublishersList($subscriptionId, $location)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE . '/locations/'
            . $location . '/publishers';

        $request = $this->getClient()->prepareRequest($path, 'GET', ComputeService::RESOURCE_API_VERSION);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new PublisherList();

            foreach ($resultArray as $array) {
                $result->append(PublisherData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * List all of available offers.
     *
     * @param string $subscriptionId Subscription Id
     * @param string $location       Location
     * @param string $publisher      Publisher
     *
     * @return OfferList Object with API response
     */
    public function getOffersList($subscriptionId, $location, $publisher)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE . '/locations/'
            . $location . '/publishers/' . $publisher
            . '/artifacttypes/vmimage/offers';

        $request = $this->getClient()->prepareRequest($path, 'GET', ComputeService::RESOURCE_API_VERSION);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new OfferList();

            foreach ($resultArray as $array) {
                $result->append(OfferData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * List all of available skus.
     *
     * @param string $subscriptionId Subscription Id
     * @param string $location       Location
     * @param string $publisher      Publisher
     * @param string $offer          Offer
     *
     * @return SkuList Object with API response
     */
    public function getSkusList($subscriptionId, $location, $publisher, $offer)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE . '/locations/'
            . $location . '/publishers/' . $publisher
            . '/artifacttypes/vmimage/offers/'
            . $offer . '/skus';

        $request = $this->getClient()->prepareRequest($path, 'GET', ComputeService::RESOURCE_API_VERSION);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new SkuList();

            foreach ($resultArray as $array) {
                $result->append(SkuData::initArray($array));
            }
        }

        return $result;
    }

    /**
     * List all of available versions.
     *
     * @param string $subscriptionId Subscription Id
     * @param string $location       Location
     * @param string $publisher      Publisher
     * @param string $offer          Offer
     * @param string $sku            Sku
     *
     * @return VersionList Object with API response
     */
    public function getVersionsList($subscriptionId, $location, $publisher, $offer, $sku)
    {
        $result = null;

        $path = '/subscriptions/' . $subscriptionId
            . ComputeService::ENDPOINT_MICROSOFT_COMPUTE . '/locations/'
            . $location . '/publishers/' . $publisher
            . '/artifacttypes/vmimage/offers/'
            . $offer . '/skus/' . $sku . '/versions';

        $request = $this->getClient()->prepareRequest($path, 'GET', ComputeService::RESOURCE_API_VERSION);
        $response = $this->getClient()->call($request);

        if (!$response->hasError()) {
            $resultArray = $response->getResult();

            $result = new VersionList();

            foreach ($resultArray as $array) {
                $result->append(VersionData::initArray($array));
            }
        }

        return $result;
    }

}
