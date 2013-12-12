<?php

namespace Scalr\Service\Aws\Event;

use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ErrorData;

/**
 * ErrorResponseEvent
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.09.2013
 *
 * @property-read  \Scalr\Service\Aws\Client\ClientException $exception
 *                 Client Exception object
 */
class ErrorResponseEvent extends AbstractEvent implements EventInterface
{
    /**
     * Client Exception object
     *
     * @var ClientException
     */
    protected $exception;

    /**
     * The name of the API call
     *
     * @var string
     */
    protected $apicall;
}