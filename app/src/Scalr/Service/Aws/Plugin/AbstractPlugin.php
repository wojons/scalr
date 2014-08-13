<?php

namespace Scalr\Service\Aws\Plugin;

/**
 * AbstractPlugin
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     26.09.2013
 */
abstract class AbstractPlugin
{

    const PRIORITY_MEDIUM = 0x66;

    /**
     * Plugin priority
     *
     * @var int
     */
    public $priority = self::PRIORITY_MEDIUM;

    /**
     * AWS instance
     *
     * @var \Scalr\Service\Aws
     */
    protected $aws;

    /**
     * DI Container
     *
     * @var \Scalr\DependencyInjection\Container
     */
    protected $container;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Plugin.PluginInterface::setAws()
     */
    public function setAws(\Scalr\Service\Aws $aws)
    {
        $this->aws = $aws;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Plugin.PluginInterface::setContainer()
     */
    public function setContainer($diContainer)
    {
        $this->container = $diContainer;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Plugin.PluginInterface::isSubscribedEvent()
     */
    public function isSubscribedEvent($eventType)
    {
        return in_array((string)$eventType, $this->getSubscribedEvents());
    }
}