<?php

namespace Scalr\Service\Azure\Exception;

use Exception;
use Scalr\Service\Azure\DataType\ErrorData;
use Scalr\Service\Exception\ResponseErrorFactory;

/**
 * AzureResponseErrorFactory class
 *
 * @author  N.V.
 */
class AzureResponseErrorFactory implements ResponseErrorFactory
{
    /**
     * {@inheritdoc}
     * @return RestClientException
     * @see \Scalr\Service\Exception\ResponseErrorFactory::make()
     */
    public static function make($message = null, $code = 0, Exception $previous = null)
    {
        if ($message instanceof ErrorData) {
            switch ($message->code) {
                case ErrorData::ERR_AZURE_NOT_FOUND:
                    return new NotFoundException($message, $code, $previous);
                default:
                    break;
            }
        }

        return new RestClientException($message, $code, $previous);
    }
}