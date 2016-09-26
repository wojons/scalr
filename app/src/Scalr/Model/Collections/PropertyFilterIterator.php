<?php

namespace Scalr\Model\Collections;

use FilterIterator;
use Iterator;

/**
 * Class PropertyFilterIterator
 *
 * @author N.V.
 */
class PropertyFilterIterator extends FilterIterator
{

    /**
     * Property name
     *
     * @var string
     */
    protected $property;

    /**
     * Filtering value
     *
     * @var mixed
     */
    protected $value;

    /**
     * Constructor
     *
     * @param Iterator $iterator The iterator that is being filtered
     * @param string   $property Property name on which filtered
     * @param mixed    $value    Filtering value
     */
    public function __construct(Iterator $iterator, $property, $value)
    {
        parent::__construct($iterator);
        $this->property = $property;
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     * @see FilterIterator::accept()
     */
    public function accept()
    {
        $entity = $this->getInnerIterator()->current();

        if (is_object($entity)) {
            $ret = property_exists($entity, $this->property) && $entity->{$this->property} == $this->value;
        } else if (is_array($entity)) {
            $ret = array_key_exists($this->property, $entity) ?
                $entity[$this->property] == $this->value :
                $this->value === null;
        } else {
            $ret = $entity == $this->value;
        }

        return $ret;
    }
}