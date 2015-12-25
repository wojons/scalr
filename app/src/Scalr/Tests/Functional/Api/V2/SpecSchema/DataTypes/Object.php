<?php
/**
 * Created by PhpStorm.
 * User: andriy
 * Date: 30.11.15
 * Time: 10:07
 */

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;


class Object extends SpecObject
{

    /**
     * @var array
     */
    protected $_objectProperties;

    protected static $properties = [
        'required',
        'x-createOnly',
        'x-filterable',
        'x-usedIn',
        'x-derived',
        'readOnly',
        'items',
        'x-references',
        'references'
    ];

    public function __get($name)
    {
        if ($this->propertyExist($name)) {
            return $this->_objectProperties[$name];
        }
        return false;
    }

    public function getObjectProperties()
    {
        return $this->_objectProperties;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (in_array($name, static::$properties)) {
            $name = ltrim($name, 'x-');
            $this->$name = $value;
        } else {
            $this->_objectProperties[$name] = $value;
        }

    }

    public function propertyExist($name)
    {
        return isset($this->_objectProperties[$name]) || in_array($name, self::$properties);
    }




}