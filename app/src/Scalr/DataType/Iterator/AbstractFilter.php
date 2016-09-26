<?php
namespace Scalr\DataType\Iterator;

use Iterator;
use InvalidArgumentException;

/**
 * AbstractFilter iterator
 *
 * Note that SPL's \FilterIterator is found to be not stable for php-5.5 ubuntu
 * so we have to use this workaround.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (25.04.2014)
 */
abstract class AbstractFilter implements Iterator
{

    /**
     * Inner iterator
     *
     * @var Iterator
     */
    private $iterator;

    /**
     * Constructor
     *
     * @param    Iterator     $iterator  Inner iterator
     * @throws   \InvalidArgumentException
     */
    public function __construct(Iterator $iterator)
    {
        $this->iterator = $iterator;

        if (!($iterator instanceof Iterator)) {
            throw new InvalidArgumentException(sprintf("First argument must implement Iterator interface"));
        }
    }

    /**
     * Filter function
     *
     * @return   bool  Returns true to accept value or false otherwise
     */
    abstract public function accept();

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     */
    public function current()
    {
        return $this->iterator->current();
    }

    /**
     * {@inheritdoc}
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * {@inheritdoc}
     * @see Iterator::next()
     */
    public function next()
    {
        do {
            $this->iterator->next();
        } while ($this->valid() && !$this->accept());
    }

    /**
     * {@inheritdoc}
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->iterator->rewind();
        if ($this->valid() && !$this->accept()) {
            $this->next();
        }
    }

    /**
     * {@inheritdoc}
     * @see Iterator::valid()
     */
    public function valid()
    {
        return $this->iterator->valid();
    }
}
