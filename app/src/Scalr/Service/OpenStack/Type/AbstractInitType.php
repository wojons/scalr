<?php
namespace Scalr\Service\OpenStack\Type;

/**
 * AbstractInitType
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    07.12.2012
 */
abstract class AbstractInitType
{

    /**
     * ReflectionClass instance
     *
     * @var \ReflectionClass
     */
    private $reflection;

    /**
     * The list of the all private properties
     * @var array
     */
    private $reflectionProperties;

    /**
     * Gets reflection class object
     *
     * @return  \ReflectionClass Returns reflection class instance
     */
    public function getReflectionClass()
    {
        if (!isset($this->reflection)) {
            $this->reflection = new \ReflectionClass(get_class($this));
        }

        return $this->reflection;
    }

    /**
     * Gets the list of the properties for an subset of the classes
     *
     * @return  array Returns array of the ReflectionProperty
     */
    public function _getReflectionProperties()
    {
        if (is_null($this->reflectionProperties)) {
            $this->reflectionProperties = array();
            $f = false;
            $classes = array();
            foreach (array_reverse(class_parents($this)) as $class) {
                if (!$f && !($f = is_subclass_of($class, __CLASS__))) continue;
                $classes[] = new \ReflectionClass($class);
            }
            $classes[] = new \ReflectionClass(get_class($this));
            foreach ($classes as $refl) {
                foreach ($refl->getProperties(\ReflectionProperty::IS_PRIVATE) as $refp) {
                    if (substr($refp->getName(), 0, 1) == '_') continue;
                    /* @var $refp \ReflectionProperty */
                    $refp->setAccessible(true);
                    $this->reflectionProperties[$refp->getName()] = $refp;
                }
            }
        }
        return $this->reflectionProperties;
    }

    /**
     * Initializes a new object of the class
     *
     * @return AbstractInitType
     */
    public static function init()
    {
        $class = get_called_class();
        $obj = new $class;
        $args = func_get_args();
        if (!empty($args)) {
            call_user_func_array(array($obj, '__construct'), $args);
        }
        return $obj;
    }

    /**
     * Initializes a new object of the class with the specified array
     *
     * Array should look like array('property' => value) where for an each
     * property in the specified array, the method setProperty must exist.
     *
     * @param   array|\Traversable  $array  The properties
     * @throws  \BadFunctionCallException
     */
    public static function initArray($array)
    {
        $class = get_called_class();
        if (!is_array($array) && !($array instanceof \Traversable)) {
            throw new \BadFunctionCallException(sprintf(
                'Invalid argument for the field. Either "%s" or array is accepted.',
                $class
            ));
        }
        $obj = new $class;
        $refProperties = $obj->_getReflectionProperties();
        foreach ($array as $opt => $val) {
            $methodName = "set" . ucfirst($opt);
            if (!method_exists($obj, $methodName)) {
                if (isset($refProperties[$opt])) {
                    $refProperties[$opt]->setValue($obj, $val);
                } else if (property_exists($obj, $opt)) {
                    $obj->$opt = $val;
                } else {
                    throw new \BadFunctionCallException(sprintf(
                        'Neither method "%s" nor property "%s" does exist for the %s class.',
                        $methodName, $opt, $class
                    ));
                }
            } else {
                $obj->$methodName($val);
            }
        }
        return $obj;
    }
}