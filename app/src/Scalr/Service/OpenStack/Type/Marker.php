<?php
namespace Scalr\Service\OpenStack\Type;

/**
 * Marker
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    07.12.2012
 */
class Marker extends AbstractInitType
{
    /**
     * Maximum number of items at time (<=1000)
     * @var int
     */
    private $limit;

    /**
     * The ID of the last item in the previous list.
     * @var string
     */
    private $marker;

    /**
     * Adds property's value
     *
     * This method expects the property to be array type
     *
     * @param   string       $name     PropertyName
     * @param   array|string $value    value
     * @param   \Closure     $typeCast optional Type casting closrure
     * @return  Marker
     */
    protected function _addPropertyValue($name, $value, \Closure $typeCast = null)
    {
        $refl = $this->getReflectionClass();

        if (!$refl->hasProperty($name)) {
            throw new \InvalidArgumentException(sprintf(
                'Property "%s" does not exist in "%s"',
                $name, get_class($this)
            ));
        }

        $prop = $refl->getProperty($name);

        if ($prop->isPrivate()) {
            $prop->setAccessible(true);
        }

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
     * Convenient constuctor
     *
     * @param   strinng    $marker  optional The ID of the last item in the previous list.
     * @param   int        $limit   optional Maximum number of items at time (<=1000)
     */
    public function __construct($marker = null, $limit = null)
    {
        $this
            ->setLimit($limit)
            ->setMarker($marker)
        ;
    }

    /**
     * getLimit
     *
     * @return  number Maximum number of items at time (<=1000)
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * getMarker
     *
     * @return  string The ID of the last item in the previous list.
     */
    public function getMarker()
    {
        return $this->marker;
    }

    /**
     * setLimit
     *
     * @param   number $limit Maximum number of items at time (<=1000)
     * @return  Marker
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * setMarker
     *
     * @param   string $marker The ID of the last item in the previous list.
     * @return  Marker
     */
    public function setMarker($marker)
    {
        $this->marker = $marker;
        return $this;
    }

    /**
     * Initializes new object
     *
     * @param   strinng    $marker  optional The ID of the last item in the previous list.
     * @param   int        $limit   optional Maximum number of items at time (<=1000)
     * @return  Marker Returns new Marker
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }

    /**
     * Gets query data array
     *
     * @return array Returns query data array
     */
    public function getQueryData()
    {
        $options = array();

        if ($this->getMarker() !== null) {
            $options['marker'] = (string) $this->getMarker();
        }
        if ($this->getLimit() !== null) {
            $options['limit'] = (int) $this->getLimit();
        }

        return $options;
    }

    /**
     * Gets the query string for the fields
     *
     * @param   array  $fields The fields list looks like (fild1, field2, .. or fieldN => uriParameterAlias)
     * @param   array  $class  optional The called class
     * @return  string Returns the query string
     */
    protected function _getQueryStringForFields(array $fields = null, $class = null)
    {
        $str = '';
        if ($class === null || $class == get_class($this)) {
            $refl = $this->getReflectionClass();
        } else {
            $refl = new \ReflectionClass($class);
        }

        if ($fields === null) {
            //Trying to determine fields from reflection class
            $fields = array_map(function(\ReflectionProperty $prop){
                return $prop->getName();
            }, $refl->getProperties());
        }

        foreach ($fields as $index => $prop) {
            if (!is_numeric($index)) {
                $uriProp = $prop;
                $prop = $index;
            } else {
                $uriProp = $prop;
            }
            if (!$refl->hasProperty($prop)) continue;
            $refProp = $refl->getProperty($prop);
            if ($refProp->isPrivate()) {
                $refProp->setAccessible(true);
            }
            $value = $refProp->getValue($this);
            if ($value !== null) {
                if (is_array($value) || $value instanceof \Traversable) {
                    foreach ($value as $v) {
                        $str .= '&' . $uriProp . '=' . rawurlencode((string)$v);
                    }
                } else {
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
        return ltrim($this->_getQueryStringForFields(array('marker', 'limit'), __CLASS__), '&');
    }
}