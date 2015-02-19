<?php

class Scalr_Exception_InsufficientPermissions extends Exception
{
    //The message is used in tests
    const MESSAGE = "You do not have permission to view this component. Please contact your account owner to obtain access.";

    function __construct($message = null, $code = null, $previous = null)
    {
        $message = self::MESSAGE; // . $this->getTraceAsString();
        parent::__construct($message);
    }
}