<?php

namespace Scalr\Upgrade;

use Scalr\Exception;
use \IteratorAggregate;

/**
 * AbstractEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (10.10.2013)
 */
abstract class AbstractEntity extends AbstractGetter implements IteratorAggregate
{

    /**
     * Actual data as it is in the storage
     *
     * @var  \stdClass
     */
    private $actual;

    /**
     * Changes iterator
     *
     * @var EntityChangesIterator
     */
    private $changesIterator;

    /**
     * Properties iterator
     *
     * @var EntityPropertiesIterator
     */
    private $iterator;

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        if (!$this->iterator) {
            $this->iterator = new EntityPropertiesIterator($this);
        }
        return $this->iterator;
    }

    /**
     * Returns actual state of the entity
     *
     * @return  stdClass
     */
    public function getActual()
    {
        if ($this->actual === null) {
            $this->actual = new \stdClass;
        }
        return $this->actual;
    }

    /**
     * Loads an entity from array or object
     *
     * @param   array|object  $obj  Source data
     */
    public function load($obj)
    {
        $this->actual = new \stdClass();
        $isObject = is_object($obj);

        foreach ($this->getIterator() as $property => $v) {
            if ($isObject && !isset($obj->$property) || !$isObject && !isset($obj[$property])) {
                continue;
            } else {
                $value = $isObject ? $obj->$property : $obj[$property];
            }
            $this->$property = $value;
            $this->actual->$property = $value;
        }
    }

    /**
     * Saves entity to storage
     */
    abstract public function save();

    /**
     * Gets primary key
     *
     * @return  string Returns property name which is primary key
     */
    abstract public function getPrimaryKey();

    /**
     * Gets changes bewteen real and database state of the entity
     *
     * @return EntityChangesIterator Returns iterator over the changes
     */
    protected function getChanges()
    {
        if (!isset($this->changesIterator)) {
            $this->changesIterator = new EntityChangesIterator($this->getIterator());
        }
        return $this->changesIterator;
    }

    public function __set($prop, $value)
    {
        if (property_exists($this, $prop)) {
            $this->$prop = $value;
            return $this;
        }
        throw new Exception\UpgradeException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }

    public function __unset($prop)
    {
        if (property_exists($this, $prop)) {
            if (isset($this->$prop)) {
                $this->$prop = null;
            }
        }
        throw new Exception\UpgradeException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }

    public function __call($method, $args)
    {
        $prefix = substr($method, 0, 3);
        if ($prefix == 'get') {
            $prop = lcfirst(substr($method, 3));
            if (property_exists($this, $prop)) {
                return $this->$prop;
            }
        } else if ($prefix == 'set') {
            $prop = lcfirst(substr($method, 3));
            if (property_exists($this, $prop)) {
                $this->$prop = isset($args[0]) ? $args[0] : null;
                return $this;
            }
        } else {
            throw new \BadMethodCallException(sprintf(
                'Could not find method "%s" for the class "%s".',
                $method, get_class($this)
            ));
        }

        throw new Exception\UpgradeException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }
}