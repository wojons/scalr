<?php
namespace Scalr\System\Zmq\Cron\Payload;

use Scalr\System\Zmq\Cron\PayloadRouterInterface;
use Scalr\System\Zmq\Cron\Payload;

/**
 * ScalarizrMessagingPayload
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (11.11.2014)
 */
class ScalarizrMessagingPayload extends Payload implements PayloadRouterInterface
{

    /**
     * The service name including composition
     *
     * @var string
     */
    public $address;

    /**
     * Constructor
     *
     * @param   mixed   $body  The body of the message
     */
    public function __construct($body = null)
    {
        parent::__construct($body);

        if (isset($body->address)) {
            //Keeps an address in the payload to be accessible in the request
            $this->address = $body->address;
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractPayload::__sleep()
     */
    public function __sleep()
    {
        return array_merge(parent::__sleep(), ['address']);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\PayloadRouterInterface::getAddress()
     */
    public function getAddress(\Scalr\System\Zmq\Cron\TaskInterface $task)
    {
        return $this->address;
    }
}