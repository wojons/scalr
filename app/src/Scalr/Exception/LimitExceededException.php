<?php

namespace Scalr\Exception;

/**
 * LimitExceededException
 *
 * @author N.V.
 */
class LimitExceededException extends ScalrException
{

    /**
     * LimitExceededException
     *
     * @param   string  $limitName  Human readable limit name
     */
    public function __construct($limitName)
    {
        parent::__construct(_("{$limitName} limit exceeded for your account. Please <a href='#/billing'>upgrade your account</a> to higher plan"));
    }
}