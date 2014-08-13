<?php
namespace Scalr\Service\OpenStack\Type;

use Scalr\Service\OpenStack\OpenStack;

/**
 * AbstractFilterType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (14.01.2014)
 */
abstract class AbstractFilterType extends AbstractInitType
{
    public function __call($method, $args)
    {
        $refl = $this->getReflectionClass();
        $prefix = strtolower(substr($method, 0, 3));
        $property = lcfirst(substr($method, 3));
        $properties = $this->_getReflectionProperties();

        /* @var $prop \ReflectionProperty */
        if (isset($properties[$property])) {
            $prop = $properties[$property];
            if ($prefix === 'get') {
                return $prop->getValue($this);
            } elseif ($prefix === 'set') {
                $prop->setValue($this, array());
                return !isset($args[0]) ? $this : $this->_addPropertyValue($property, $args[0]);
            } elseif ($prefix === 'add') {
                return $this->_addPropertyValue($property, $args[0]);
            }
        }

        if ($refl->getParentClass()->hasMethod('__call')) {
            return parent::__call($method, $args);
        } else {
            throw new \BadFunctionCallException(sprintf(
                'Method "%s" does not exist for object "%s".', $method, get_class($this)
            ));
        }
    }

    /**
     * Adds property's value
     *
     * This method expects the property to be array type
     *
     * @param   string       $name     The name of the property add to
     * @param   array|string $value    The value to add
     * @param   \Closure     $typeCast optional Type casting closrure
     * @return  AbstractFilterType
     */
    protected function _addPropertyValue($name, $value, \Closure $typeCast = null)
    {
        $reflProperties = $this->_getReflectionProperties();

        if (!isset($reflProperties[$name])) {
            throw new \InvalidArgumentException(sprintf(
                'Property "%s" does not exist for "%s"',
                $name, get_class($this)
            ));
        }

        $prop = $reflProperties[$name];

        if (($property = $prop->getValue($this)) === null) {
            $property = array();
        }

        if (!is_array($value) && !($value instanceof \Traversable)) {
            $value = array($value);
        }

        foreach ($value as $v) {
            if ($typeCast !== null) {
                $property[] = $typeCast($v);
            } else {
                $property[] = (string)$v;
            }
        }

        $prop->setValue($this, $property);

        return $this;
    }

    /**
     * Gets query data array
     *
     * @return array Returns query data array
     */
    public function getQueryData()
    {
        $options = array();
        foreach ($this->_getReflectionProperties() as $prop) {
            $value = $this->{"get" . ucfirst($prop->name)}();
            if ($value !== null) {
                if ($value instanceof BooleanType || is_scalar($value)) {
                    $value = (string) $value;
                } else if ($value instanceof \DateTime) {
                    $value = $value->format('c');
                } else {
                    $value = (string) $value;
                }
                $options[OpenStack::decamelize($prop->name)] = $value;
            }
        }
        return $options;
    }

    /**
     * Gets the query string for the fields
     *
     * @param   array  $fields The fields list looks like (fild1, field2, .. or fieldN => uriParameterAlias)
     * @return  string Returns the query string
     */
    protected function _getQueryStringForFields(array $fields = null)
    {
        $str = '';
        $reflProperties = $this->_getReflectionProperties();

        if ($fields === null) {
            //Trying to determine fields from reflection class
            $fields = array();
            foreach ($reflProperties as $prop) {
                $fields[$prop->getName()] = OpenStack::decamelize($prop->getName());
            }
        }

        foreach ($fields as $index => $prop) {
            if (!is_numeric($index)) {
                $uriProp = $prop;
                $prop = $index;
            } else {
                $uriProp = $prop;
            }
            if (!isset($reflProperties[$prop])) continue;
            $refProp = $reflProperties[$prop];
            $value = $refProp->getValue($this);
            if ($value !== null) {
                if (is_array($value) || $value instanceof \Traversable) {
                    foreach ($value as $v) {
                        if ($v instanceof \DateTime) {
                            $v = $v->format('c');
                        }
                        $str .= '&' . $uriProp . '=' . rawurlencode((string)$v);
                    }
                } else {
                    if ($value instanceof \DateTime) {
                        $value = $value->format('c');
                    }
                    $str .= '&' . $uriProp . '=' . rawurlencode((string)$value);
                }
            }
            unset($uriProp);
        }

        return $str;
    }

    /**
     * Gets a query string
     *
     * @return string Returns a query string
     */
    public function getQueryString()
    {
        return ltrim($this->_getQueryStringForFields(), '&');
    }
}