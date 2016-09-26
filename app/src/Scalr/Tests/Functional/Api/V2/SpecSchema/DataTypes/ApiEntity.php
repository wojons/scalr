<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;

/**
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (03.12.2015)
 */
class ApiEntity extends AbstractSpecObject
{
    /**
     * The list of the paths where uses this object
     *
     * @var array
     */
    public $usedIn = [];

    /**
     * The list of the readOnly properties
     *
     * @var array
     */
    public $readOnly = [];

    /**
     * The list of object properties
     *
     * @var Property[]
     */
    protected $properties = [];

    /**
     * @param string $name property name
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        }

        return false;
    }

    /**
     * Return object properties
     *
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * {@inheritdoc}
     * @see AbstractSpecObject::__set()
     */
    public function __set($name, $value)
    {
        $name = ltrim($name, 'x-');
        if ($value instanceof Property) {
            $this->properties[$name] = $value;
            if ($value->readOnly) {
                $this->readOnly[] = $name;
            }
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * Unset properties
     *
     * @param string $name property name
     */
    public function __unset($name)
    {
        unset($this->properties[$name]);
    }

    /**
     * Check if element exist in properties
     * @param string $name property name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->properties[$name]);
    }
}