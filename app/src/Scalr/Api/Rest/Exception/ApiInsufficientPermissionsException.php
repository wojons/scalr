<?php

namespace Scalr\Api\Rest\Exception;

use Scalr\Api\DataType\ErrorMessage;

/**
 * ApiInsufficientPermissionsException
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (17.03.2015)
 */
class ApiInsufficientPermissionsException extends ApiErrorException
{
	/**
	 * Constructor
	 *
	 * @param    string $message  optional The numan readable message
	 */
    public function __construct($message = null)
    {
        $message = $message ?: 'Insufficient Permissions';

        parent::__construct(403, ErrorMessage::ERR_PERMISSION_VIOLATION, $message);
    }
};