<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * AbstractDataType
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
abstract class AbstractDataType
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
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array();

    /**
     * Data for the properties that is managed internally.
     *
     * @var array
     */
    private $propertiesData = array();

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
     * @param  string  $name  property name
     * @return mixed
     */
    public function __get($name)
    {
        if (in_array($name, $this->_properties)) {
            return array_key_exists($name, $this->propertiesData) ? $this->propertiesData[$name] : null;
        }
        throw new \InvalidArgumentException(
            sprintf('Unknown property "%s" for the object %s', $name, get_class($this))
        );
    }

    /**
     * @param  string  $name
     * @return boolean
     */
    public function __isset($name)
    {
        if (in_array($name, $this->_properties)) {
            return isset($this->propertiesData[$name]);
        }
        throw new \InvalidArgumentException(
            sprintf('Unknown property "%s" for the object %s', $name, get_class($this))
        );
    }

    /**
     *
     * @param unknown_type $name
     */
    public function __unset($name)
    {
        if (in_array($name, $this->_properties) && isset($this->propertiesData[$name])) {
            unset($this->propertiesData[$name]);
        } else {
            throw new \InvalidArgumentException(
                sprintf('Unknown property "%s" for the object %s', $name, get_class($this))
            );
        }
    }

    /**
     * @param   string     $name
     * @param   mixed      $data
     */
    public function __set($name, $data)
    {
        if (in_array($name, $this->_properties)) {
            $setfn = 'set' . ucfirst($name);
            if (method_exists($this, $setfn)) {
                //makes it possible to cast argument value type for an explicitly defined setter methods
                $this->$setfn($data);
            } else {
                $this->propertiesData[$name] = $data;
            }
        } else {
            throw new \InvalidArgumentException(
                sprintf('Unknown property "%s" for the object %s', $name, get_class($this))
            );
        }
    }

    /**
     * It allows to get|set an internal property value
     *
     * @param  string   $name
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $m = array($name, substr($name, 0, 3), substr($name, 3));
        if (($m[1] == 'get' || $m[1] == 'set') && !empty($m[2])) {
            $identifier = lcfirst($m[2]);
            if ($m[1] == 'set' && count($arguments) !== 1) {
                if (count($arguments) !== 1) {
                    throw new \InvalidArgumentException(sprintf(
                        'One argument is expected for %s method of %s class.', $name, get_class($this)
                    ));
                }
            } else if (in_array($identifier, $this->_properties)) {
                if ($m[1] == 'get') {
                    return array_key_exists($identifier, $this->propertiesData) ? $this->propertiesData[$identifier] : null;
                } else {
                    //Set property is expected to be here.
                    $this->propertiesData[$identifier] = $arguments[0];
                    return $this;
                }
            } else {
                if ($this->getReflectionClass()->hasProperty($identifier)) {
                    $prop = $this->getReflectionClass()->getProperty($identifier);
                    if ($prop instanceof \ReflectionProperty && $prop->isPublic()) {
                        if ($m[1] == 'get') {
                            return $prop->getValue($this);
                        } else {
                            //Set property is expected to be here.
                            $prop->setValue($this, $arguments[0]);
                            return $this;
                        }
                    }
                }
            }
        }
        throw new \BadFunctionCallException(sprintf(
            'Method "%s" does not exist for the class "%s".', $name, get_class($this)
        ));
    }

    /**
     * Transforms list to Json string
     *
     * @return  string  Returns JSON ecoded string
     */
    public function toJson()
    {
        return json_encode((array)$this);
    }

    /**
     * Transforms list to Array
     *
     * @return  Array Returns array
     */
    public function toArray()
    {
        $result = ObjectProperties::get($this);

        if (count($this->_properties) > 0) {
            foreach($this->_properties as $property) {
                if ($this instanceof PaginationType) {
                    $result[$property] = $this->{$property};
                } else if (!empty($this->{$property})) {
                    if (($this->{$property} instanceof AbstractDataType) || ($this->{$property} instanceof AbstractListDataType)) {
                        $result[$property] = $this->{$property}->toArray();
                    } else {
                        $result[$property] = $this->{$property};
                    }

                }
            }
        }

        return $result;
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
        $rClass = new \ReflectionClass($class);
        $constructor = $rClass->getConstructor();
        if ($constructor !== null) {
            $args = $constructor->getParameters();
            if (count($args) > 0) {
                foreach ($args as $arg) {
                    $name = $arg->name;
                    $requiredArgs[] = !empty($array[$name]) ? $array[$name] : null;
                    unset($array[$name]);
                }
            }
        }
        if (!empty($requiredArgs)) {
            $obj = $rClass->newInstanceArgs($requiredArgs);
        }
        else {
            $obj = new $class;
        }

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