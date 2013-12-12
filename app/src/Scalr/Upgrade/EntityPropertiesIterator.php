<?php

namespace Scalr\Upgrade;

use \Iterator;
use \ReflectionClass;
use \ReflectionProperty;

/**
 * EntityPropertiesIterator
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (18.10.2013)
 */
class EntityPropertiesIterator implements Iterator
{
    /**
     * The entity object
     *
     * @var AbstractEntity
     */
    private $entity;

    /**
     * Reflection class for the AbstractEntity
     *
     * @var ReflectionClass
     */
    private $refl;

    /**
     * Reflection properties array
     *
     * @var  array
     */
    private $refProps;

    /**
     * Position
     *
     * @var int
     */
    private $position;

    /**
     * Constructor
     *
     * @param   AbstractEntity   $entity An entity
     */
    public function __construct(AbstractEntity $entity)
    {
        $this->position = 0;
        $this->entity = $entity;
        $this->refl = new ReflectionClass(get_class($entity));
        $this->refProps = $this->refl->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC);
    }

	/**
     * {@inheritdoc}
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->refProps[$this->position]->getName();
    }

	/**
     * {@inheritdoc}
     * @see Iterator::next()
     */
    public function next()
    {
        ++$this->position;
    }

	/**
     * {@inheritdoc}
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->position = 0;
    }

	/**
     * {@inheritdoc}
     * @see Iterator::valid()
     */
    public function valid()
    {
        return isset($this->refProps[$this->position]);
    }

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     */
    public function current()
    {
        $property = $this->refProps[$this->position]->getName();
        return $this->entity->$property;
    }

    /**
     * Gets entity
     *
     * @return  \Scalr\Upgrade\AbstractEntity Returns an entity object
     */
    public function getEntity()
    {
        return $this->entity;
    }
}