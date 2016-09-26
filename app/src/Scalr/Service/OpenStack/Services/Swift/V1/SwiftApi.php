<?php
namespace Scalr\Service\OpenStack\Services\Swift\V1;

use Scalr\Service\OpenStack\Exception\RestClientException;
use Scalr\Service\OpenStack\Client\ClientInterface;
use Scalr\Service\OpenStack\Services\SwiftService;
use Scalr\Service\OpenStack\Client\RestClientResponse;

/**
 * Object Storage API (SWIFT)
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (27.02.2014)
 */
class SwiftApi
{

    /**
     * @var SwiftService
     */
    protected $service;

    /**
     * Constructor
     *
     * @param   SwiftService $service
     */
    public function __construct(SwiftService $service)
    {
        $this->service = $service;
    }

    /**
     * Gets HTTP Client
     *
     * @return  ClientInterface Returns HTTP Client
     */
    public function getClient()
    {
        return $this->service->getOpenStack()->getClient();
    }

    /**
     * Escapes string
     *
     * @param   string    $string A string needs to be escapted
     * @return  string    Returns url encoded string
     */
    public function escape($string)
    {
        return rawurlencode($string);
    }

    /**
     * GET Service action
     *
     * @return  RestClientResponse     Returns raw response
     * @throws  RestClientException
     */
    public function describeService()
    {
        $response = $this->getClient()->call($this->service);
        $response->hasError();
        return $response;
    }

    /**
     * POST Service action
     *
     * @return  RestClientResponse     Returns raw response
     * @throws  RestClientException
     */
    public function updateService($options)
    {
        $response = $this->getClient()->call($this->service, '/', $options, 'POST');
        $response->hasError();
        return $response;
    }
}