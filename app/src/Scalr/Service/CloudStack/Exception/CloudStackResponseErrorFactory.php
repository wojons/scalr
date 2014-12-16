<?php

namespace Scalr\Service\CloudStack\Exception;

use Exception;
use Scalr\Service\CloudStack\Client\QueryClientResponse;
use Scalr\Service\CloudStack\DataType\ErrorData;
use Scalr\Service\Exception\ResponseErrorFactory;

/**
 * Class CloudStackResponseErrorFactory
 * @author  N.V.
 */
class CloudStackResponseErrorFactory implements ResponseErrorFactory
{

    /**
     * {@inheritdoc}
     * @return RestClientException
     * @see \Scalr\Service\Exception\ResponseErrorFactory::make()
     */
    public static function make($message = null, $code = 0, Exception $previous = null)
    {
        if ($message instanceof ErrorData) {
            if (stristr($message->message, 'Not Found')) {
                return new NotFoundException($message, $code, $previous);
            }
        }

        return new RestClientException($message, $code, $previous);
    }
}
