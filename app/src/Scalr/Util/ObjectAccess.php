<?php

namespace Scalr\Util;

use ArrayAccess;
use ArrayObject;
use IteratorAggregate;
use ArrayIterator;
use Countable;
use JsonSerializable;
use Serializable;
use stdClass;
use Traversable;

/**
 * Class ObjectAccess
 *
 * @package Utils\Traversable
 */
class ObjectAccess implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable, Serializable
{

    /**
     * @var array
     */
    protected $data;

    /**
     * @var mixed
     */
    protected $default;

    /**
     * Check that the object is iterable
     *
     * @param   mixed $ref
     * @return  bool
     */
    public static function isIterable($ref)
    {
        return is_array($ref) || $ref instanceof Traversable || $ref instanceof stdClass;
    }

    /**
     * Cast to native array
     *
     * @param   mixed $ref
     * @return  array
     */
    public static function toArray($ref)
    {
        return ($ref instanceof ObjectAccess || $ref instanceof ArrayObject || $ref instanceof ArrayIterator) ?
            $ref->getArrayCopy() : (array) $ref;
    }

    /**
     * Check that variable is stringable
     *
     * @param   mixed $ref
     * @return  bool
     */
    public static function isStringable($ref)
    {
        return is_string($ref) || is_object($ref) && method_exists($ref, '__toString');
    }

    /**
     * Cast to primitive type
     *
     * @param   mixed $ref
     * @param   bool  $toupper [optional]
     * @return  int|string
     */
    public static function asIndex($ref, $toupper = false)
    {
        return is_numeric($ref) ? $ref :
            (static::isStringable($ref) ? ($toupper ? strtoupper(trim((string) $ref)) : trim((string) $ref)) : $ref);
    }

    /**
     * ObjectAccess
     *
     * @param   array|object $data    [optional]
     * @param   mixed        $default [optional]
     */
    public function __construct(&$data = array(), $default = null)
    {
        if (is_array($data)) {
            $this->data = &$data;
        } else if (static::isIterable($data)) {
            $this->data = static::toArray($data);
        } else if ($data === null) {
            $this->data = &$data;
            $this->data = array();
        } else {
            $this->data = array(&$data);
        }

        $this->default = $default;
    }

    /**
     * ObjectAccess
     *
     * @param   array|object $obj
     * @param   mixed        $default  [optional]
     * @param   bool         $populate [optional]
     * @return  ObjectAccess
     */
    public static function wrap($obj, $default = null, $populate = false)
    {
        return new static($obj, $default, $populate);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetGet()
     */
    public function &offsetGet($offset)
    {
        $val = &$this->data[$offset];

        if (!$this->offsetExists($offset)) {
            $val = $this->default;
        }

        return $val;
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }


    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    public function &__get($name)
    {
        return $this->offsetGet($name);
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * @return array
     */
    public function getArrayCopy()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     * @see Countable::count()
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * {@inheritdoc}
     * @see JsonSerializable::jsonSerialize()
     */
    function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     * @see Serializable::serialize()
     */
    public function serialize()
    {
        return serialize($this->data);
    }

    /**
     * {@inheritdoc}
     * @see Serializable::unserialize()
     */
    public function unserialize($serialized)
    {
        $this->data = unserialize($serialized);
    }
}
