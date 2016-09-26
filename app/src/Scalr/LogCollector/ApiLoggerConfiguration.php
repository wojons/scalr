<?php

namespace Scalr\LogCollector;

/**
 * ApiLoggerConfiguration class
 *
 * @author Vlad Dobrovolskiy <v.dobrovolskiy@scalr.com>
 */
class ApiLoggerConfiguration
{
    /**
     * Ip address
     *
     * @var string
     */
    public $ipAddress;

    /**
     * Request identifier
     *
     * @var string
     */
    public $requestId;

    /**
     * Request type
     *
     * @var string
     */
    public $requestType;

    /**
     * Constructor
     *
     * @param string $requestType  Request type
     */
    public function __construct($requestType)
    {
        $this->requestType = $requestType;
    }

}