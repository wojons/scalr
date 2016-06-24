<?php

namespace Scalr\Model\Collections;

use ArrayIterator;
use BadMethodCallException;
use Iterator;
use IteratorIterator;
use Scalr\Model\AbstractEntity;

/**
 * ArrayCollection
 *
 * This is the collection of the AbstractEntity objects
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (13.03.2014)
 */
class ArrayCollection extends ArrayIterator
{
    /**
     * The total number of records without limit
     *
     * This property is set after use find() method
     *
     * @var int
     */
    public $totalNumber;

    /**
     * Constructor
     * @param   array   $array  optional
     */
    public function __construct($array = array())
    {
        parent::__construct($array);
    }

    /**
     * Implements some convenient magic methods
     *
     * @param   string   $name  The method name
     * @param   array    $args  The arguments
     * @return  AbstractEntity Returns array of the found entities
     * @throws  BadMethodCallException
     */
    public function __call($name, $args)
    {
        //Implements findByProperty method set
        if (strpos($name, 'filterBy') === 0) {
            $property = lcfirst(substr($name, 8));

            $value = isset($args[0]) ? $args[0] : null;

            return new static(array_filter($this->getArrayCopy(), function ($entity) use ($property, $value) {
                if (is_object($entity)) {
                    $ret = property_exists($entity, $property) &&
                           $entity->$property == $value;
                } else if (is_array($entity)) {
                    $ret = array_key_exists($property, $entity) ?
                           $entity[$property] == $value :
                           $value === null;
                } else {
                    $ret = $entity == value;
                }

                return $ret;
            }));
        }

        throw new BadMethodCallException(sprintf(
            "Could not find method %s for class %s", $name, get_class($this)
        ));
    }

    /**
     * This method is used for compatibility with IteratorAggregate
     *
     * @see IteratorAggregate::getIterator()
     *
     * @return  Iterator
     */
    public function getIterator()
    {
        return new IteratorIterator($this);
    }
}