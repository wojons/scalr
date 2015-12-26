<?php

namespace Scalr\Tests\Functional\Api\V2;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Wrapper for composite filters
 *
 * @author N.V.
 */
class FilterRule implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * Key-value array like [filter_name => filter_value, ...]
     *
     * @var array
     */
    protected $filters;

    /**
     * FilterRule
     *
     * @param   array   $filters    optional Key-value array like [filter_name => filter_value, ...]
     */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Gets filters
     *
     * @return  array   Array of filters like [filter_name => filter_value, ...]
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        return isset($this->filters[$offset]);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetGet()
     */
    public function &offsetGet($offset)
    {
        return $this->filters[$offset];
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value)
    {
        $this->filters[$offset === null ? count($this->filters) : $offset] = $value;
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        unset($this->filters[$offset]);
    }

    /**
     * {@inheritdoc}
     * @see Countable::count()
     */
    public function count()
    {
        return count($this->filters);
    }

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new ArrayIterator($this->filters);
    }

    /**
     * Magic getter
     *
     * @param   mixed   $name   Filter name
     *
     * @return  mixed   Returns filter value
     */
    public function &__get($name)
    {
        return $this->offsetGet($name);
    }

    /**
     * Magic setter
     *
     * @param   mixed   $name   Filter name
     * @param   mixed   $value  Filter value
     */
    public function __set($name, $value)
    {
        $this->offsetSet($name, $value);
    }

    /**
     * Gets string representation of object for comparison purposes
     *
     * @return  string
     */
    public function __toString()
    {
        return http_build_query($this->filters, null, '&', PHP_QUERY_RFC3986);
    }
}