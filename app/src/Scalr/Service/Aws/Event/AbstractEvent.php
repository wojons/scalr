<?php

namespace Scalr\Service\Aws\Event;

use Scalr\Service\Aws\Exception;

/**
 * AbstractEvent
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     27.09.2013
 */
abstract class AbstractEvent
{

    const PRIORITY_MEDIUM =  0x66;
    const PRIORITY_LOW    =  0x10;
    const PRIORITY_HIGH   = 0xfff;

    /**
     * An event name
     *
     * @var string
     */
    private $name;

    /**
     * Priority
     *
     * @var int
     */
    public $priority = self::PRIORITY_MEDIUM;

    /**
     * False for event which is propagated
     *
     * @var bool
     */
    private $propagation = true;

    /**
     * Constructor
     *
     * @param  array  $data The event data
     */
    public function __construct($data)
    {
        $this->name = preg_replace('/^.+\\\\(.+)Event$/', '\\1', get_class($this));
        if (!empty($data) && is_array($data)) {
            foreach ($data as $property => $value) {
                if (property_exists($this, $property)) {
                    $this->$property = $value;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Event.EventInterface::getName()
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Event.EventInterface::stopPropagation()
     */
    public function stopPropagation()
    {
        $this->propagation = false;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Event.EventInterface::isPropagationStopped()
     */
    public function isPropagationStopped()
    {
        return !$this->propagation;
    }

    public function __isset($property)
    {
        return property_exists($this, $property);
    }

    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }

        throw new Exception\EventException(sprintf('Property "%s" does not exist for the object "%s"', $property, get_class($this)));
    }
}