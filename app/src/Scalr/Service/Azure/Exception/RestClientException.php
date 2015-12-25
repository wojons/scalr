<?php

namespace Scalr\Service\Azure\Exception;

use Scalr\Service\AzureException;
use Scalr\Service\Azure\DataType\ErrorData;

/**
 * RestClientException class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class RestClientException extends AzureException
{
    /**
     * Error details
     * @var ErrorData
     */
    public $error;

    /*
     * Constructor
     */
    public function __construct($message, $code = null, $previous = null)
    {
        if ($message instanceof ErrorData) {
            $this->error = $message;
            parent::__construct('Azure error. ' . $message->message, $code, $previous);
        } else {
            parent::__construct($message, $code, $previous);
        }
    }
}
