<?php

namespace Scalr\Upgrade;

use FilterIterator;

/**
 * EntityChangesIterator
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (17.10.2013)
 *
 * @method   \Scalr\Upgrade\EntityPropertiesIterator getInnerIterator()
 *           getInnerIterator()
 *           Gets EntityPropertiesIterator
 */
class EntityChangesIterator extends FilterIterator
{
    /**
     * Is a new record
     *
     * @var bool
     */
    private $new;

    /**
     * Constructor
     *
     * @param   EntityPropertiesIterator   $iterator An entity properties iterator
     */
    public function __construct(EntityPropertiesIterator $iterator)
    {
        parent::__construct($iterator);
        $this->position = 0;
        $pk = $iterator->getEntity()->getPrimaryKey();
        $this->new = !$iterator->getEntity()->getActual()->$pk;
    }

    /**
     * Synchronizes actual state of the entity with the real
     */
    public function synchronize()
    {
        $actual = $this->getInnerIterator()->getEntity()->getActual();
        foreach ($this as $prop => $val) {
            $actual->$prop = $val;
        }
    }

    /**
     * {@inheritdoc}
     * @see FilterIterator::accept()
     */
    public function accept()
    {
        $iterator = $this->getInnerIterator();
        $prop = $iterator->key();
        $actual = $iterator->getEntity()->getActual();

        return ($this->new || ($prop != $iterator->getEntity()->getPrimaryKey()) &&
               ($iterator->current() !== (isset($actual->$prop) ? $actual->$prop : null)));
    }
}