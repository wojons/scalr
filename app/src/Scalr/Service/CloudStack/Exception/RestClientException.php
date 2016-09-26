<?php
namespace Scalr\Service\CloudStack\Exception;

use Exception;
use Scalr\Service\CloudStack\DataType\ErrorData;

/**
 * RestClientException
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class RestClientException extends CloudStackException
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
            parent::__construct('CloudStack error. ' . $message->message, $code, $previous);
        } else {
            parent::__construct($message, $code, $previous);
        }
    }
}