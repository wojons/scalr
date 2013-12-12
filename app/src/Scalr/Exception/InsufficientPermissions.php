<?php

class Scalr_Exception_InsufficientPermissions extends Exception
{
    //The message is used in tests
    const MESSAGE = "You cannot use this component because you have not been placed in a team with the appropriate permissions. Please contact your account owner to obtain access.";

    function __construct($message = null, $code = null, $previous = null)
    {
        $message = self::MESSAGE; // . $this->getTraceAsString();
        parent::__construct($message);
    }
}