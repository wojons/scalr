<?php

namespace Scalr\Service\Azure\Client;

use http\Client\Request;
use Scalr\Service\Azure;

/**
 * ClientInterface
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
interface ClientInterface
{
    /**
     * Create http Request object with specified params.
     *
     * @param string $path       Requested resource path
     * @param string $method     Http method
     * @param string $apiVersion Api version of the service
     * @param string $baseUrl    optional Base url
     * @param array  $queryData  optional Associative array of query-string params
     * @param array  $postFields optional Associative array of post-fields
     * @param array  $headers    optional Associative array of request headers
     * @throws \Scalr\Service\AzureException
     * @throws \InvalidArgumentException
     * @return Request $request Http Request object
     */
    public function prepareRequest($path, $method, $apiVersion, $baseUrl = Azure::URL_MANAGEMENT_WINDOWS, $queryData = [], $postFields = [], $headers = []);

    /**
     * Makes a REST request to Azure service
     *
     * @param   Request $message  Http request
     * @return  ClientResponseInterface Returns response from server
     * @throws  \Scalr\Service\Azure\Exception\RestClientException
     */
    public function call($message);
}
