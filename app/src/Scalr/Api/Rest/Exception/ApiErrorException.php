<?php

namespace Scalr\Api\Rest\Exception;
use Exception;


/**
 * ApiErrorException
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (03.03.2015)
 *
 * @method   string getMessage() getMessage() Returns human readable API error message
 */
class ApiErrorException extends \Exception
{
    /**
     * Http response status code
     *
     * @var int
     */
    private $status;

    /**
     * Machine-readable API error code
     *
     * @var string
     */
    private $error;

    /**
     * Constructor
     *
     * @param   int         $status              HTTP response status code
     * @param   string      $error               Machine-readable API error code
     * @param   string      $message             Human-readable error message
     * @param   int         $code       optional The Exception code
     * @param   Exception   $previous   optional The previous exception used for the exception chaining
     */
    public function __construct($status, $error, $message, $code = 0, Exception $previous = null)
    {
        $this->status = $status;
        $this->error = $error;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Gets HTTP response status code
     *
     * @return number  Returns HTTP response status code
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Gets machine-readable API error code
     *
     * @return string Returns machine-readable API error code
     */
    public function getError()
    {
        return $this->error;
    }
}