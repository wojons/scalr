<?php

namespace Scalr\Service\Exception;

use Exception;

/**
 * ResposeErrorFactory interface
 *
 * @author  N.V.
 */
interface ResponseErrorFactory
{

    /**
     * Makes a correct exception
     *
     * @param  ErrorData|string          $message   optional The error message
     * @param  int                       $code      optional The error code
     * @param  Exception                 $previous  optional The previous exception
     * @return Exception
     */
    public static function make($message = null, $code = 0, Exception $previous = null);
}