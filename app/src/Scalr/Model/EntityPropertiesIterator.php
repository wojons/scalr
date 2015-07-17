<?php

namespace Scalr\Model;

use Iterator,
    ArrayObject,
    ArrayIterator,
    ReflectionClass,
    ReflectionProperty;
use Scalr\Model\Loader\Field;

/**
 * EntityPropertiesIterator
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (18.10.2013)
 */
class EntityPropertiesIterator implements Iterator
{
    /**
     * The entity object
     *
     * @var AbstractEntity
     */
    private $entity;

    /**
     * Reflection class for the AbstractEntity
     *
     * @var ReflectionClass
     */
    private $refl;

    /**
     * Reflection properties array
     *
     * @var  ArrayObject
     */
    private $refProps;

    /**
     * Position
     *
     * @var int
     */
    private $position;

    /**
     * The position of the each property in refProps
     *
     * @var array
     */
    private $propertyPositions;

    /**
     * Primary key
     *
     * @var array
     */
    private $primaryKey;

    /**
     * Cache of the fields
     *
     * @var array
     */
    private static $cache = array();

    /**
     * @var ArrayIterator
     */
    private $_fieldsIterator;

    /**
     * Constructor
     *
     * @param   AbstractEntity   $entity An entity
     */
    public function __construct(AbstractEntity $entity)
    {
        $this->position = 0;
        $this->entity = $entity;
        $this->refl = $entity->_getReflectionClass();
        $this->refProps = new ArrayObject(array());
        $this->primaryKey = array();

        $entityAnnotation = $entity->getEntityAnnotation();

        $fieldsIterator = array();
        $pos = 0;

        $properties = $this->refl->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC);
        $staticProperties = $this->refl->getProperties(ReflectionProperty::IS_STATIC);

        foreach (array_diff($properties, $staticProperties) as $refProp) {
            /* @var $refProp \ReflectionProperty */
            /* @var $field \Scalr\Model\Loader\Field */
            if (substr($refProp->name, 0, 1) != '_') {
                if (!isset(self::$cache[$refProp->class][$refProp->name])) {
                    //executes only once
                    $loader = \Scalr::getContainer()->get('model.loader');
                    $loader->load($refProp);

                    $field = $refProp->annotation;

                    $field->setEntityAnnotation($entityAnnotation);

                    $typeClass = __NAMESPACE__ . '\\Type\\' . ucfirst($field->column->type) . 'Type';

                    $refProp->annotation->setType(new $typeClass($field));

                    self::$cache[$refProp->class][$refProp->name] = $field;

                    unset($field);
                }

                $refProp->annotation = self::$cache[$refProp->class][$refProp->name];

                $fieldsIterator[$refProp->annotation->name] = $refProp->annotation;

                if (isset($refProp->annotation->id)) {
                    $this->primaryKey[] = $refProp->name;
                }

                $this->refProps->append($refProp);

                $this->propertyPositions[$refProp->name] = $pos++;
            }
        }

        $this->_fieldsIterator = new ArrayIterator($fieldsIterator);
    }

    public function __clone()
    {
        //After cloning EntityPropertiesIterator we should specify a new entity
        $this->entity = null;
        $this->position = 0;
    }

    /**
     * Gets iterator of the fields
     *
     * @return   ArrayIterator
     */
    public function fields()
    {
        return $this->_fieldsIterator;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->refProps[$this->position]->name;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::next()
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::valid()
     */
    public function valid()
    {
        return isset($this->refProps[$this->position]);
    }

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     */
    public function current()
    {
        $property = $this->refProps[$this->position]->name;
        return $this->entity->$property;
    }

    /**
     * Gets list of the propeties
     *
     * @return ArrayObject Returns the list of the reflection properties
     */
    public function getProperties()
    {
        return $this->refProps;
    }

    /**
     * Get property by its name
     *
     * @param   string    $name The name
     * @return  ReflectionProperty  Returns reflection property by its name on success or null
     */
    public function getProperty($name)
    {
        return isset($this->propertyPositions[$name]) ? $this->refProps[$this->propertyPositions[$name]] : null;
    }

    /**
     * Get field by its name
     *
     * @param   string    $name The name
     * @return  Field    Returns Field by its name on success or null
     */
    public function getField($name)
    {
        return isset($this->propertyPositions[$name]) ? $this->refProps[$this->propertyPositions[$name]]->annotation : null;
    }

    /**
     * Gets current property
     *
     * @return ReflectionProperty
     */
    public function getCurrentProperty()
    {
        return $this->refProps[$this->position];
    }

    /**
     * Gets primary key for the entity
     *
     * @return   array Returns primary key
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Gets entity
     *
     * @return  \Scalr\Upgrade\AbstractEntity Returns an entity object
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Sets entity to internal object
     *
     * @param   AbstractEntity   $entity  An entity
     * @return  EntityPropertiesIterator
     */
    public function setEntity(AbstractEntity $entity)
    {
        $this->entity = $entity;
        return $this;
    }
}