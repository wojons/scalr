<?php

namespace Scalr\Tests\Functional\Api;

use Scalr\Api\Rest\Http\Response;

/**
 * ApiTestResponse
 *
 * This class is used in the tests
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0 (14.03.2015)
 *
 * @method   getH
 */
class ApiTestResponse
{
    /**
     * THe HTTP Response object
     *
     * @var Response
     */
    public $response;

    /**
     * Headers
     *
     * @var array
     */
    public $headers;

    /**
     * HTTP Response status code
     *
     * @var int
     */
    public $status;

    /**
     * Constructor
     *
     * @param   Response   $response  The HTTP Response
     */
    public function __construct(Response $response)
    {
        $this->response = $response;
        list($this->status, $this->headers) = $response->finalize();
    }

    public function __call($method, $args)
    {
        if (method_exists($this->response, $method)) {
            return call_user_func_array($this->response, $method);
        }

        throw new \BadMethodCallException(sprintf("Method %s does not exist in the %s class", $method, get_class($this->response)));
    }

    /**
     * Gets json decoded response
     *
     * @return  object
     */
    public function getBody()
    {
        return $this->response->getBody() == '' ? null : json_decode($this->response->getBody());
    }
}