<?php
namespace Scalr\Service\CloudStack\Client;

use Scalr\Service\CloudStack\Exception\RestClientException;

/**
 * ClientInterface
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
interface ClientInterface
{

    /**
     * Makes a REST request to CloudStack service
     *
     * @param   \Scalr\Service\CloudStack\Services\ServiceInterface|string $endpoint  Endpoint url
     * @param   array            $args  optional An array of the query parameters
     * @param   string           $verb     optional An HTTP Verb
     * @return  ClientResponseInterface Returns response from server
     * @throws  RestClientException
     */
    public function call($endpoint, array $args = null, $verb = 'GET');
}
