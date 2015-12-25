<?php

namespace Scalr\Service\Azure;

use Scalr\Service\Azure;

/**
 * Azure abstract service interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
abstract class AbstractService
{
    /**
     * @var Azure
     */
    protected $azure;

    /**
     * Api version of the service
     *
     * @var string
     */
    protected $apiVersion;

    /**
     * Constructor
     *
     * @param Azure $azure
     */
    public function __construct(Azure $azure)
    {
        $this->azure = $azure;
        $this->apiVersion = $this->getApiVersion();
    }

    /**
     * Returns API version that this client use.
     *
     * @return string API Version
     */
    abstract public function getApiVersion();

    /**
     * Gets url for each service
     *
     * @return string
     */
    abstract public function getServiceUrl();

    /**
     * Gets an Azure instance
     *
     * @return Azure Returns Azure instance
     */
    public function getAzure()
    {
        return $this->azure;
    }

}