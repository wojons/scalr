<?php

namespace Scalr\Service\Aws\Event;

/**
 * SendRequestEvent
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.09.2013
 */
class SendRequestEvent extends AbstractEvent implements EventInterface
{
    /**
     * The number of the request in the current session
     * @var int
     */
    protected $requestNumber;

    /**
     * The name of the API call
     *
     * @var string
     */
    protected $apicall;
}