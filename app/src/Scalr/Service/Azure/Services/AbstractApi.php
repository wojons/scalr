<?php

namespace Scalr\Service\Azure\Services;

use Scalr\Service\Azure\AbstractService;

/**
 * Azure abstract api interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
class AbstractApi
{
    /**
     * @var AbstractService
     */
    private $service;

    /**
     * Constructor
     *
     * @param AbstractService     $service      Azure client
     */
    public function __construct(AbstractService $service)
    {
        $this->service = $service;
    }

    /**
     * Returns API version that this client use.
     *
     * @return string API Version
     */
    public function getApiVersion()
    {
        return $this->service->getApiVersion();
    }

    /**
     * Gets url for each service
     *
     * @return string
     */
    public function getServiceUrl()
    {
        return $this->service->getServiceUrl();
    }

    /**
     * Gets query client
     *
     * @return \Scalr\Service\Azure\Client\QueryClient
     */
    public function getClient()
    {
        return $this->service->getAzure()->getClient();
    }

    /**
     * Gets Azure object
     *
     * @return \Scalr\Service\Azure
     */
    public function getAzure()
    {
        return $this->service->getAzure();
    }

}