<?php

namespace Scalr\Service\Aws\Plugin;

use Scalr\Service\Aws\Event\EventType;
use Scalr\Service\Aws\Event\EventInterface;
use Scalr\Service\Aws\Exception;

/**
 * AWS EventObserver
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     26.09.2013
 *
 * @method    bool hasStatistics()
 *            hasStatistics()
 *            Checks whether Statistic plugin is defined and enabled
 *
 * @method    \Scalr\Service\Aws\Plugin\Handlers\StatisticsPlugin getStatistics()
 *            getStatistics()
 *            Gets statistic plugin
 */
class EventObserver
{

    /**
     * Aws instance
     *
     * @var \Scalr\Service\Aws
     */
    private $aws;

    /**
     * The list of the plugins
     *
     * @var \ArrayObject
     */
    private $plugins;

    /**
     * The list of the enabled plugins
     *
     * @var array
     */
    private $enabled;


    /**
     * List of the subscriptions to the events
     *
     * @var array
     */
    private $subscriptions;

    /**
     * Fires an AWS Client Event
     *
     * @param   EventInterface $event An AWS Client Event
     */
    public function fireEvent(EventInterface $event)
    {
        if (!empty($this->subscriptions[$event->getName()])) {
            $subscribed = $this->subscriptions[$event->getName()];
            foreach ($subscribed as $lcPlugin => $priority) {
                if ($event->isPropagationStopped()) {
                    break;
                }
                if ($this->enabled[$lcPlugin]) {
                    $plugin = $this->plugins[$lcPlugin];
                    if ($plugin instanceof PluginInterface) {
                        $plugin->handle($event);
                    }
                }
            }
        }
    }

    /**
     * Checks whether the observer has subscritpions to specified event
     *
     * @param   EventType|string   $eventType  The type of the AWS client event
     * @return  bool  Returns true if the observer has subscriptions to specified event
     */
    public function isSubscribed($eventType)
    {
        $eventName = (string)$eventType;
        return !empty($this->subscriptions[$eventName]);
    }

    /**
     * Constructor
     */
    public function __construct(\Scalr\Service\Aws $aws)
    {
        $this->plugins = new \ArrayObject(array());
        $this->aws = $aws;

        $cfEnabled = $this->aws->getContainer()->config('scalr.aws.plugins.enabled');
        if (!is_array($cfEnabled)) {
            $cfEnabled = (array)$cfEnabled;
        }

        foreach (EventType::getAllowedValues() as $eventName) {
            $this->subscriptions[$eventName] = array();
        }

        foreach (glob(__DIR__ . DIRECTORY_SEPARATOR . 'Handlers' . DIRECTORY_SEPARATOR . '*Plugin.php', GLOB_NOSORT) as $filename) {
            $plugin = substr(basename($filename), 0, -4);
            $class = __NAMESPACE__ . '\\Handlers\\' . $plugin;

            $p = new $class;
            if (!($p instanceof PluginInterface)) {
                trigger_error(sprintf('AWS client plugin "%s" must implement PluginInterface.', $class), E_USER_WARNING);
                continue;
            }

            $p->setAws($this->aws);
            $p->setContainer($this->aws->getContainer());
            $subscribed = $p->getSubscribedEvents();
            if (empty($subscribed)) {
                continue;
            }

            $lcPlugin = strtolower(substr($plugin, 0, -6));

            foreach ($subscribed as $eventName) {
                if (isset($this->subscriptions[$eventName])) {
                    $this->subscriptions[$eventName][$lcPlugin] = $p->priority;
                } else {
                    throw new Exception\PluginException(sprintf('AWS client plugin "%s" is subscribed to unknown event "%s".', $class, $eventName));
                }
            }

            $this->plugins[$lcPlugin] = $p;
            $this->enabled[$lcPlugin] = in_array($lcPlugin, $cfEnabled) ? true : null;
        }

        //Sorts subscriptions by plugin priority
        foreach ($this->subscriptions as $eventName => $v) {
            if (!empty($v)) {
                arsort($this->subscriptions[$eventName]);
            }
        }
    }

    /**
     * Checks whether the specified plugin is defined and enabled
     *
     * @param   string  $plugin The name of the plugin provided in the lower case
     * @return  bool    Returns true if plugin is defined and enabled
     */
    public function has($plugin)
    {
        return isset($this->plugins[$plugin]) && !empty($this->enabled[$plugin]);
    }

    /**
     * Gets plugin from the container
     *
     * @param   string          $plugin The name of the plugin provided in the lower case
     * @return  PluginInterface Returns plugin
     * @throws  \Scalr\Service\Aws\Exception\PluginException
     */
    public function get($plugin)
    {
        if (!isset($this->plugins[$plugin])) {
            throw new Exception\PluginException(sprintf(
                'AWS client plugin "%s" does not exist.', $plugin
            ));
        } elseif (empty($this->enabled[$plugin])) {
            throw new Exception\PluginException(sprintf(
                'AWS client plugin "%s" has not been allowed.', $plugin
            ));
        } else {
            return $this->plugins[$plugin];
        }
    }

    /**
     * Enables plugin
     *
     * @param   string      $plugin  The name of the AWS client plugin
     * @throws  Exception\PluginException
     * @return  \Scalr\Service\Aws\Plugin\EventObserver
     */
    public function enable($plugin)
    {
        if (!isset($this->plugins[$plugin])) {
            throw new Exception\PluginException(sprintf(
                'AWS client plugin "%s" does not exist.', $plugin
            ));
        }
        $this->enabled[$plugin] = true;

        return $this;
    }

    /**
     * Disables plugin
     *
     * @param   string      $plugin  The name of the AWS client plugin
     * @throws  Exception\PluginException
     * @return  \Scalr\Service\Aws\Plugin\EventObserver
     */
    public function disable($plugin)
    {
        if (!isset($this->plugins[$plugin])) {
            throw new Exception\PluginException(sprintf(
                'AWS client plugin "%s" does not exist.', $plugin
            ));
        }
        $this->enabled[$plugin] = null;

        return $this;
    }

    /**
     * Gets all defined plugins.
     *
     * @return   \ArrayObject  Returns all defined plugins
     */
    public function all()
    {
        return $this->plugins;
    }

    public function __call($method, $args)
    {
        $prefix = substr($method, 0, 3);
        $pluginName = strtolower(substr($method, 3));
        if ($prefix == 'has') {
            return $this->has($pluginName);
        } else if ($prefix == 'get') {
            return $this->get($pluginName);
        } else if ($prefix == 'let') {
            return $this->enable($pluginName);
        }
        throw new \BadFunctionCallException(sprintf(
            'Method "%s" has not been defined for %s', $method, get_class($this)
        ));
    }
}
