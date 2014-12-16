<?php

namespace Scalr\Service\Cloud\Rackspace\Exception;

use Exception;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Exception\ResponseErrorFactory;

/**
 * Class ClientException
 * @author  N.V.
 */
class RackspaceResponseErrorFactory implements ResponseErrorFactory
{

    /**
     * {@inheritdoc}
     * @return ClientException
     * @see \Scalr\Service\Exception\ResponseErrorFactory::make()
     */
    public static function make($message = '', $code = 0, Exception $previous = null)
    {
        if (stristr($message, "404")) {
            return new NotFoundException($message, $code, $previous);
        }

        return new ClientException($message, $code, $previous);
    }
}
