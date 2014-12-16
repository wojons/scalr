<?php
namespace Scalr\System\Zmq\Cron;

/**
 * Payload router interface
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (11.11.2014)
 */
interface PayloadRouterInterface
{
    /**
     * Gets the name of the service
     *
     * This method is used for custom routing, when the address
     * depends on the payload content
     *
     * @param   \Scalr\System\Zmq\Cron\TaskInterface $task  The task
     * @return  string  Returns the name of the service to send the MDP request
     */
    public function getAddress(\Scalr\System\Zmq\Cron\TaskInterface $task);
}