<?php

namespace Scalr\Model\Objects;

use Scalr\Model\AbstractEntity;

/**
 * AdapterInterface
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0  (27.02.2015)
 */
interface AdapterInterface
{
    /**
     * Gets the name of the class of the Entity
     *
     * @return   string Returns the name of the entity's class
     */
    public function getEntityClass();

    /**
     * Sets the name of the class of the entity
     *
     * @param   string    $class  The name of the class of the entity
     */
    public function setEntityClass($class);

    /**
     * Gets the name of the class for the result
     *
     * @return   string Retusn the name of the class for the result
     */
    public function getDataClass();

    /**
     * Sets the name of the class for the result data
     *
     * @param   string    $class  The name of the class of the result
     */
    public function setDataClass($class);

    /**
     * Gets the rules.
     *
     * Which properties are allowed to be in the converted data array,
     * and which are accepted in reverse dirrection
     *
     * @return   arrray  Returns array looks like ['toData' => ['property1', 'property2', ...], 'toEntity' => []]
     */
    public function getRules();

    /**
     * Sets the rules
     *
     * @param array    $rules  The converter rules set
     */
    public function setRules($rules);

    /**
     * Converts entity to a data object
     *
     * It is used getDataClassName() for the result set type
     *
     * @param   AbstractEntity       $entity  The entity to convert into data
     * @return  mixed                Returns the converted object that is of getDataClassName() type
     */
    public function toData($entity);

    /**
     * Converts data to entity
     *
     * It is used getDataClassName() for the result set type
     *
     * @param   object           $data  The data to convert into entity
     * @return  AbstractEntity   Returns the Entity
     */
    public function toEntity($data);

    /**
     * Sets iterator of the entities.
     *
     * @param   \Iterator   $iterator  Iterator of the entities
     */
    public function setInnerIterator(\Iterator $iterator);
}