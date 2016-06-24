<?php

namespace Scalr\LogCollector;

use Exception;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Rest\Http\Response;

/**
 * ApiLogger
 * Logger implementation for API
 *
 * @author N.V.
 */
class ApiLogger extends AbstractLogger
{
    /**
     * API request type
     */
    const REQUEST_TYPE_API = 'api';

    /**
     * SYSTEM request type
     */
    const REQUEST_TYPE_SYSTEM = 'system';

    /**
     * Ip address
     *
     * @var string
     */
    private $ipAddress;

    /**
     * Request type
     *
     * @var string
     */
    private $requestType;

    /**
     * Request identifier
     *
     * @var string
     */
    private $requestId;

    /**
     * Constructor. Instantiates ApiLogger, prepares backend
     *
     * @param ApiLoggerConfiguration $config  Api logger config object
     */
    public function __construct($config)
    {
        parent::__construct(\Scalr::config('scalr.logger.api'));

        $this->ipAddress   = $config->ipAddress;
        $this->requestId   = $config->requestId;
        $this->requestType = $config->requestType;
    }

    /**
     * {@inheritdoc}
     * @see AbstractLogger::initializeSubscribers()
     */
    protected function initializeSubscribers()
    {
        parent::initializeSubscribers();

        $this->subscribers['api.error'] = [$this, 'handlerApiError'];
    }

    /**
     * {@inheritdoc}
     * @see AbstractLogger::getCommonData()
     */
    protected function getCommonData()
    {
        $data = parent::getCommonData();

        $data['ip_address']    = $this->ipAddress;
        $data['request_type']  = $this->requestType;
        $data['request_id']    = $this->requestId;

        return $data;
    }

    /**
     * Logs failed requests data
     *
     * @param   Request     $request    API request data
     * @param   Response    $response   API response data
     *
     * @return array   Returns array of the fields to log
     */
    protected function handlerApiError(Request $request, Response $response)
    {
        $data = [
            '.request.method'   => $request->getMethod(),
            '.request.url'      => $request->getUrl() . $request->getPath(),
            '.request.headers'  => $request->headers(),
            '.request.body'     => $request->getBody(),
            '.response.status'  => $response->getStatus(),
            '.response.headers' => $response->getHeaders(),
            '.response.body'    => $response->getBody()
        ];

        return $data;
    }
}