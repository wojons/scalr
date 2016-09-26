<?php

namespace Scalr\Service\Aws\Exception;

use Exception;
use Scalr\Service\Aws\Client\QueryClientException;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Exception\ResponseErrorFactory;

/**
 * AwsResponseErrorFactory class
 *
 * @author  N.V.
 */
class AwsResponseErrorFactory implements ResponseErrorFactory
{

    /**
     * {@inheritdoc}
     * @return QueryClientException
     * @see    \Scalr\Service\Exception\ResponseErrorFactory::make()
     */
    public static function make($message = null, $code = 0, Exception $previous = null)
    {
        if ($message instanceof ErrorData) {
            switch ($message->getCode()) {
                case ErrorData::ERR_EC2_INSTANCE_NOT_FOUND:
                    return new InstanceNotFoundException($message, $code, $previous);
                default:
                    break;
            }
        }

        return new QueryClientException($message, $code, $previous);
    }
}