<?php

namespace Scalr\Model\Objects;

use Scalr\Model\AbstractEntity;
use Scalr\Api\DataType\ApiEntityAdapter;

/**
 * BaseAdapter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.4.0  (27.02.2015)
 */
class BaseAdapter implements AdapterInterface, \Iterator, \OuterIterator
{
    /**
     * Defines conversion rules from Entity to Data object and vice verse
     */
    const RULE_TYPE_TO_DATA = 'toData';

    /**
     * Action to convert a field value from the Entity into Object
     */
    const ACT_CONVERT_TO_OBJECT = 1;

    /**
     * Action to convert a field value from the Object into the Entity
     */
    const ACT_CONVERT_TO_ENTITY = 2;

    /**
     * Action to return the filter criteria for the specified field
     */
    const ACT_GET_FILTER_CRITERIA = 3;

    /**
     * The class name of the Entity
     *
     * @var string
     */
    protected $entityClass;

    /**
     * The class name for the result data
     *
     * @var string
     */
    protected $dataClass = 'stdClass';

    /**
     * The converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data restul object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        //entityProperty is treated as method to call if it starts from the underscore.
        'toData'   => null,
    ];

    /**
     * The inner iterator of the entities
     *
     * @var \Iterator
     */
    private $innerIterator;

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::getEntityClass()
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::setEntityClass()
     */
    public function setEntityClass($class)
    {
        $this->entityClass = $class;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::getDataClass()
     */
    public function getDataClass()
    {
        return $this->dataClass;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::setDataClass()
     */
    public function setDataClass($class)
    {
        $this->dataClass = $class;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::getRules()
     */
    public function getRules()
    {
        return $this->rules;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::setRules()
     */
    public function setRules($rules)
    {
        $this->rules = $rules;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::toData()
     */
    public function toData($entity)
    {
        $dataClass = $this->getDataClass();

        $result = new $dataClass;

        if (!($entity instanceof AbstractEntity)) {
            throw new \InvalidArgumentException(sprintf(
                "%s expects first argument to be instance of AbstractEntity class.", __METHOD__
            ));
        }

        $it = $entity->getIterator();

        $bconvert = $this instanceof ApiEntityAdapter;

        $converterRules = $this->getRules();

        if (!empty($converterRules[static::RULE_TYPE_TO_DATA])) {
            foreach ($converterRules[static::RULE_TYPE_TO_DATA] as $key => $property) {
                //This is necessary when result data key does not match the property name of the entity
                $key = is_int($key) ? $property : $key;

                if ($key[0] === '_' && method_exists($this, $key)) {
                    //It is callable
                    $this->{$key}($entity, $result, self::ACT_CONVERT_TO_OBJECT);
                } else {
                    $result->$property = isset($entity->$key) ?
                        ($bconvert ? ApiEntityAdapter::convertOutputValue($it->getField($key)->column->type, $entity->$key) : $entity->$key) : null;
                }
            }
        } else {
            foreach ($it->fields() as $field) {
                $result->{$field->name} = ($bconvert ? ApiEntityAdapter::convertOutputValue($field->column->type, $entity->{$field->name}) : $entity->{$field->name});
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::toEntity()
     */
    public function toEntity($data)
    {
        $entityClass = $this->getEntityClass();

        $entity = new $entityClass;

        $converterRules = $this->getRules();

        $bconvert = $this instanceof ApiEntityAdapter;

        $it = $entity->getIterator();

        if (!is_object($data)) {
            $data = (object) $data;
        }

        if (!empty($converterRules[static::RULE_TYPE_TO_DATA])) {
            foreach ($converterRules[static::RULE_TYPE_TO_DATA] as $key => $property) {
                $key = is_int($key) ? $property : $key;
                if (isset($data->$property)) {
                    if ($key[0] === '_' && method_exists($this, $key)) {
                        //It is callable
                        $this->{$key}($data, $entity, self::ACT_CONVERT_TO_ENTITY);
                    } else {
                        $entity->$key = ($bconvert ? ApiEntityAdapter::convertInputValue($it->getField($key)->column->type, $data->$property) : $data->$property);
                    }
                }
            }
        } else {
            foreach ($it->fields() as $field) {
                /* @var $field \Scalr\Model\Loader\Field */
                $key = $field->name;

                if ($key[0] === '_' && method_exists($this, $key)) {
                    //It is callable
                    $this->{$key}($data, $entity, self::ACT_CONVERT_TO_ENTITY);
                } elseif (isset($data->$key)) {
                    $entity->$key = ($bconvert ? ApiEntityAdapter::convertInputValue($field->column->type, $data->$key) : $data->$key);
                }
            }
        }

        return $entity;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Model\Objects\AdapterInterface::setInnerIterator()
     */
    public function setInnerIterator(\Iterator $iterator)
    {
        $this->innerIterator = $iterator;
    }

    /**
     * {@inheritdoc}
     * @see OuterIterator::getInnerIterator()
     */
    public function getInnerIterator()
    {
        if (!($this->innerIterator instanceof \Iterator)) {
            throw new \Exception(sprintf("Either inner iterator has not been set or it is an invalid type."));
        }

        return $this->innerIterator;
    }

    /**
     * {@inheritdoc}
     * @see Iterator::current()
     */
    public function current()
    {
        return $this->toData($this->getInnerIterator()->current());
    }

    /**
     * {@inheritdoc}
     * @see Iterator::key()
     */
    public function key()
    {
        return $this->getInnerIterator()->key();
    }

    /**
     * {@inheritdoc}
     * @see Iterator::next()
     */
    public function next()
    {
        $this->getInnerIterator()->next();
    }

    /**
     * {@inheritdoc}
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        $this->getInnerIterator()->rewind();
    }

    /**
     * {@inheritdoc}
     * @see Iterator::valid()
     */
    public function valid()
    {
        return $this->getInnerIterator()->valid();
    }
}