<?php

namespace Scalr\Exception;

/**
 * InvalidCloudCredentialsException class
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.3.3 (26.03.2015)
 */
class InvalidCloudCredentialsException extends ScalrException
{
    /**
     * Constructor
     *
     * @param    $message optional Error message
     */
    public function __construct($message = null)
    {
        parent::__construct($message ?: "Invalid cloud credentials.");
    }
}