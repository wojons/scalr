<?php

namespace Scalr\Service\Aws\Event;

use Scalr\Service\Aws\DataType\StringType;

/**
 * AWS EventType
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.09.2013
 */
class EventType extends StringType
{
    /**
     * It's fired when AWS send error response
     */
    const EVENT_ERROR_RESPONSE  = 'ErrorResponse';

    /**
     * It's fired after AWS Client successfully sends API request
     */
    const EVENT_SEND_REQUEST  = 'SendRequest';

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\DataType.StringType::getPrefix()
     */
    protected static function getPrefix()
    {
        return 'EVENT_';
    }
}