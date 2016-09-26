<?php
namespace Scalr\DataType\Iterator;

use RecursiveIterator, Countable;

/**
 * AggregationRecursiveIterator
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (24.03.2014)
 */
class AggregationRecursiveIterator implements RecursiveIterator, Countable
{

    /**
     * The data
     *
     * @var array
     */
    private $data;

    /**
     * The keys
     *
     * @var array
     */
    private $keys;

    /**
     * Current index
     *
     * @var int
     */
    private $index;

    /**
     * The item associated with the data
     *
     * @var string
     */
    private $item;

    /**
     * Constructor
     *
     * @param   array   $data The data array
     * @param   string  $item optional An item associated with data
     */
    public function __construct(array $data, $item = null)
    {
        $this->data = $data;
        $this->keys = !empty($this->data['data']) && is_array($this->data['data']) ? array_keys($this->data['data']) : [];
        $this->index = !empty($this->keys) ? 0 : null;
        $this->item = $item ?: (!empty($this->data['subtotals'][0]) ? $this->data['subtotals'][0] : null);
    }

    /**
     * Gets Item associated with the data
     * @return  string
     */
    public function getItem()
    {
        return $this->item;
    }

    /**
     * {@inheritdoc}
     * @see Countable::count()
     */
    public function count()
    {
        return isset($this->data['data']) && count($this->data['data']);
    }

    /**
     * {@inheritdoc}
     * @see RecursiveIterator::current()
     */
    public function current()
    {
        return $this->data['data'][$this->keys[$this->index]];
    }

    /**
     * {@inheritdoc}
     * @see RecursiveIterator::key()
     */
    public function key()
    {
        return $this->keys[$this->index];
    }

    /**
     * {@inheritdoc}
     * @see RecursiveIterator::next()
     */
    public function next()
    {
        $this->index++;
    }

    /**
     * {@inheritdoc}
     * @see RecursiveIterator::rewind()
     */
    public function rewind()
    {
        $this->index = 0;
    }

    /**
     * {@inheritdoc}
     * @see RecursiveIterator::valid()
     */
    public function valid()
    {
        return isset($this->keys[$this->index]);
    }

    /**
     * {@inheritdoc}
     * @see RecursiveIterator::getChildren()
     */
    public function getChildren()
    {
        $class = get_class($this);
        $current = $this->current();
        return new $class($current);
    }

    /**
     * {@inheritdoc}
     * @see RecursiveIterator::hasChildren()
     */
    public function hasChildren()
    {
        $current = $this->current();
        return !empty($current['data']);
    }
}