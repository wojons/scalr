<?php

namespace Scalr\Service\OpenStack\Exception;

use Exception;
use Scalr\Service\Exception\ResponseErrorFactory;
use Scalr\Service\OpenStack\Client\ErrorData;

class OpenStackResponseErrorFactory implements ResponseErrorFactory
{

    /**
     * {@inheritdoc}
     * @return RestClientException
     * @see \Scalr\Service\Exception\ResponseErrorFactory::make()
     */
    public static function make($message = null, $code = 0, Exception $previous = null)
    {
        if ($message instanceof ErrorData) {
            if ($message->code == 404 || stristr($message->message, "could not be found") || stristr($message->message, "404")) {
                return new NotFoundException($message, $code, $previous);
            }
        }

        return new RestClientException($message, $code, $previous);
    }
}
