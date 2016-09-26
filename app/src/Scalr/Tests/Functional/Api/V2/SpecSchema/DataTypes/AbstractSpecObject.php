<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes;

/**
 * Abstract Object from Api specifications
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (03.12.2015)
 */
abstract class AbstractSpecObject extends \stdClass
{
    /**
     * @var string
     */
    protected $objectName;

    /**
     * AbstractSpecObject constructor.
     * @param string $name
     */
    public function __construct($name = '')
    {
        $this->objectName = $name;
    }

    /**
     * Returns current object name
     *
     * @return string
     */
    public function getObjectName()
    {
        return $this->objectName;
    }

    /**
     * Initializes the object depending of the name in Api specifications
     *
     * @param string $name object name
     * @return ApiEntity|DetailsResponse|ListResponse|ObjectEntity|Property
     */
    public static function init($name)
    {
        if (preg_match('#^.*(List|Details)(Response)$#', $name, $match)) {
            return $match[1] == 'List' ? new ListResponse($name) : new DetailsResponse($name);
        }
        return ctype_lower($name[0]) ?  new Property($name) : (preg_match('#^Api#', $name) ? new ApiEntity($name) : new ObjectEntity($name));
    }

    /**
     * Magic setter. If the name begins with the 'x-' function will delete this section
     *
     * @param string $name  property name
     * @param mixed  $value property value
     */
    public function __set($name, $value)
    {
        $name = ltrim($name, 'x-');
        $this->$name = $value;
    }
}