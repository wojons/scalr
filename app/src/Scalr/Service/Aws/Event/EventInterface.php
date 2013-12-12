<?php

namespace Scalr\Service\Aws\Event;

/**
 * EventInterface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.09.2013
 */
interface EventInterface
{
    /**
     * Gets event name
     *
     * @return  string Returns an event name
     */
    public function getName();

    /**
     * Stops event propagation
     */
    public function stopPropagation();

    /**
     * Returns true if propogation has been stopped
     *
     * @return bool Returns true if propogation has been stopped
     */
    public function isPropagationStopped();
}