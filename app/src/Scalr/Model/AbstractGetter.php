<?php

namespace Scalr\Model;

use ReflectionClass;

/**
 * AbstractGetter
 *
 * Handles access to protected properties of the class.
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (10.10.2013)
 */
abstract class AbstractGetter
{

    /**
     * Reflections static cache
     *
     * @var ReflectionClass
     */
    private $reflection;

    /**
     * Gets reflection class
     *
     * @return ReflectionClass
     */
    public function _getReflectionClass()
    {
        if ($this->reflection === null) {
            $this->reflection = new ReflectionClass(get_class($this));
        }

        return $this->reflection;
    }

    public function __get($prop)
    {
        $refl = $this->_getReflectionClass();

        if ($refl->hasProperty($prop) && ($refProp = $refl->getProperty($prop)) &&
            $refProp->class == $refl->getName() &&
            ($refProp->isProtected() || $refProp->isPublic())) {
            return $refProp->getValue($this);
        }

        throw new \InvalidArgumentException(sprintf(
            'Property "%s" does not exist for the class "%s".',
            $prop, get_class($this)
        ));
    }

    public function __isset($prop)
    {
        $refl = $this->_getReflectionClass();

        if ($refl->hasProperty($prop) && ($refProp = $refl->getProperty($prop)) &&
            $refProp->class == $refl->getName() &&
            ($refProp->isProtected() || $refProp->isPublic())) {
            return $refProp->getValue($this) !== null;
        }

        return false;
    }
}