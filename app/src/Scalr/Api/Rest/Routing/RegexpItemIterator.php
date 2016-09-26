<?php

namespace Scalr\Api\Rest\Routing;

/**
 * RegexpItemIterator
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (13.02.2015)
 */
class RegexpItemIterator extends \IteratorIterator
{
    /**
     * Inner iterator
     *
     * @var \Iterator
     */
    private $iterator;

    /**
     * Constructor
     *
     * @param  \Iterator  Iterator of the Item objects
     */
    public function __construct($iterator)
    {
        if (!($iterator instanceof \Iterator)) {
            throw new \InvalidArgumentException(sprintf("Instance of the Iterator class is expected."));
        }

        $this->iterator = $iterator;
    }

    /**
     * {@inheritdoc}
     * @see IteratorIterator::current()
     * @return  \Scalr\Api\Rest\Routing\Item Returns current item
     */
    public function current()
    {
        return $this->iterator->current();
    }

    /**
     * {@inheritdoc}
     * @see IteratorIterator::getInnerIterator()
     */
    public function getInnerIterator()
    {
        return $this->iterator;
    }

    /**
     * {@inheritdoc}
     * @see IteratorIterator::key()
     * @return  string Returns the part of the path
     */
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * {@inheritdoc}
     * @see IteratorIterator::next()
     */
    public function next()
    {
        do {
            $this->iterator->next();
        } while (!$this->accept());
    }

    /**
     * {@inheritdoc}
     * @see IteratorIterator::rewind()
     */
    public function rewind()
    {
        $this->iterator->rewind();

        if (!$this->accept()) {
            $this->next();
        }
    }

    /**
     * {@inheritdoc}
     * @see IteratorIterator::valid()
     */
    public function valid()
    {
        return $this->iterator->valid();
    }

    /**
     * Verifies current item
     *
     * @return  boolean Returns true if Items is not string
     */
    protected function accept()
    {
        return !$this->valid() || ($this->getInnerIterator()->current() instanceof Item &&
               !$this->getInnerIterator()->current()->getPathPart()->isString());
    }
}