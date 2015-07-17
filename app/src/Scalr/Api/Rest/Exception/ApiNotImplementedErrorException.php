<?php

namespace Scalr\Api\Rest\Exception;

use Scalr\Api\DataType\ErrorMessage;

/**
 * ApiNotImplementedErrorException
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (04.03.2015)
 */
class ApiNotImplementedErrorException extends ApiErrorException
{
	/**
	 * Constructor
	 *
	 * @param    string $message  optional The numan readable message
	 */
    public function __construct($message = null)
    {
        $message = $message ?: 'This functionality has not been implemented yet';

        parent::__construct(501, ErrorMessage::ERR_NOT_IMPLEMENTED, $message);
    }
};