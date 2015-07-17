<?php

namespace Scalr\Model\Collections;

use ArrayObject;
use BadMethodCallException;
use Scalr\Model\AbstractEntity;

/**
 * EntityIterator
 *
 * This is the iterator of the AbstractEntity objects
 * It is actually outer iterator for ADORecordSet_mysqli
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0 (13.03.2014)
 *
 * @method
 */
class EntityIterator extends \IteratorIterator implements \Countable
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
     * The name of the class of the Entity
     *
     * @var string
     */
    private $entityClass;

    /**
     * The ADO record set
     *
     * @var \ADORecordSet_mysqli
     */
    private $recordSet;

    /**
     * {@inheritdoc}
     * @see IteratorIterator::current()
     * @return \Scalr\Model\AbstractEntity
     */
    public function current()
    {
        /* @var $entity \Scalr\Model\AbstractEntity */
        $entity = new $this->entityClass;
        $entity->load($this->getInnerIterator()->current());

        return $entity;
    }

    /**
     * Constructor
     *
     * @param string               $entityClass  The name of the class of the Entity in the collection
     * @param \ADORecordSet_mysqli $recordSet    Result record set
     */
    public function __construct($entityClass, $recordSet)
    {
        $this->entityClass = $entityClass;

        if (!($recordSet instanceof \ADORecordSet_mysqli)) {
            throw new \InvalidArgumentException(sprintf("Argument must be instance of ADORecordSet class"));
        }

        $this->recordSet = $recordSet;

        parent::__construct($recordSet->getIterator());
    }

    /**
     * {@inheritdoc}
     * @see Countable::count()
     */
    public function count()
    {
        return $this->recordSet->RowCount();
    }

    /**
     * Creates a copy of the ArrayObject.
     *
     * @return     array    Returns copy as an array
     */
    public function getArrayCopy()
    {
        $ret = [];

        foreach ($this as $entity) {
            $ret[] = $entity;
        }

        return $ret;
    }

    /**
     * Implements some convenient magic methods
     *
     * @param   string $name The method name
     * @param   array  $args The arguments
     *
     * @return  ArrayCollection
     *
     * @throws  BadMethodCallException
     */
    public function __call($name, $args)
    {
        //Implements filterBy{PropertyName} method set
        if (strpos($name, 'filterBy') === 0) {
            $property = lcfirst(substr($name, 8));

            $value = isset($args[0]) ? $args[0] : null;

            return new ArrayCollection(iterator_to_array(new PropertyFilterIterator(
                $this->getInnerIterator(),
                $property,
                $value
            )));
        }

        throw new BadMethodCallException(sprintf(
            "Could not find method %s for class %s", $name, get_class($this)
        ));
    }
}