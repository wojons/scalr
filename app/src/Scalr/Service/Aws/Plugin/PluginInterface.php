<?php

namespace Scalr\Service\Aws\Plugin;

use Scalr\Service\Aws\Exception\PluginException;
use Scalr\Service\Aws\Event\EventInterface;
use Scalr\Service\Aws\Event\EventType;
use Scalr\DependencyInjection\Container;

/**
 * AWS Client PluginInterface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     26.09.2013
 */
interface PluginInterface
{
    /**
     * Sets dependency injection container;
     *
     * @param   Container    $diContainer
     */
    public function setContainer($diContainer);

    /**
     * Sets an AWS instance
     *
     * @param   \Scalr\Service\Aws $aws
     */
    public function setAws(\Scalr\Service\Aws $aws);

    /**
     * Checks whether the plugin is subscribed on specified event
     *
     * @param   EventType|string    $eventType    The type of the event or its name
     * @return  bool    Returns true if the plugin is subscribed on specified event
     */
    public function isSubscribedEvent($eventType);

    /**
     * Gets all subscribed events
     *
     * @return array Returns the list of the subscribed events
     */
    public function getSubscribedEvents();

    /**
     * Handles subscribed event
     *
     * @param   EventInterface $event The AWS Client Event object
     * @throws  PluginException
     */
    public function handle(EventInterface $event);
}
