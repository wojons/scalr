<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;

/**
 * Class SpecObject
 * @package Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes
 */
abstract class SpecObject extends \stdClass
{
    /**
     * @var
     */
    public $objectName;

    /**
     * SpecObject constructor.
     * @param $name
     */
    public function __construct($name)
    {
        $this->objectName = $name;
    }

    /**
     * @return mixed
     */
    public function getObjectName()
    {
        return $this->objectName;
    }

    public static function init($name)
    {
        if(preg_match('#^.*(List|Detail)(Response)$#', $name, $match)) {
            return $match[1] == 'List' ? new ListResponse($name) : new DetailResponse($name);
        } else {
            return new Entity($name);
        }
    }

    /**
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $name = ltrim($name, 'x-');
        $this->$name = $value;
    }
}