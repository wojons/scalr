<?php

namespace Scalr\LogCollector;

use Scalr_Account_User;

/**
 * AuditLoggerConfiguration class
 *
 * @author Vlad Dobrovolskiy <v.dobrovolskiy@scalr.com>
 */
class AuditLoggerConfiguration
{
    /**
     * User object
     *
     * @var Scalr_Account_User
     */
    public $user;

    /**
     * Account identifier
     *
     * @var int
     */
    public $accountId;

    /**
     * Environment identifier
     *
     * @var int
     */
    public $envId;

    /**
     * Ip address
     *
     * @var string
     */
    public $remoteAddr;

    /**
     * Real user identifier
     *
     * @var int
     */
    public $ruid;

    /**
     * Request type
     *
     * @var string
     */
    public $requestType;

    /**
     * Task name
     *
     * @var string
     */
    public $systemTask;

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