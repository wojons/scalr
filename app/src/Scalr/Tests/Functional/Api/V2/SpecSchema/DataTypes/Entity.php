<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;

/**
 * Class Entity
 * @package Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes
 */
class Entity extends SpecObject
{

    /**
     * @var array
     */
    public $required = [];

    /**
     * @var array
     */
    public $filterable = [];

    /**
     * @var array
     */
    public $createOnly = [];

    /**
     * @var array
     */
    protected $properties = [];


    /**
     * @param $name
     * @return bool
     */
    public function __get($name)
    {
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        }
        return false;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $name = ltrim($name, 'x-');
        if (!property_exists($this, $name)) {
            $this->properties[$name] = $value;
        } else {
            parent::__set($name, $value);
        }
    }

}