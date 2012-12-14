<?php
namespace Scalr\Service\Aws\DataType;

use Scalr\Service\Aws\AbstractDataType;

/**
 * List data type
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     23.09.2012
 */
class ListDataType extends AbstractDataType implements \Iterator, \Countable, \ArrayAccess
{

	/**
	 * @var array
	 */
	private $aListData;

	/**
	 * @var string
	 */
	private $dataClassName;

	/**
	 * @var string
	 */
	private $propertyName;

	/**
	 * Result list
	 *
	 * @var array
	 */
	private $list;

	/**
	 * Position is used for Iterator implementation
	 *
	 * @var int
	 */
	private $listPosition = 0;

	/**
	 * Next token
	 *
	 * @var string
	 */
	private $nextToken = null;

	/**
	 * {@inheritdoc}
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($offset)
	{
		return isset($this->aListData[$offset]);
	}

	/**
	 * {@inheritdoc}
	 * @see ArrayAccess::offsetGet()
	 */
	public function offsetGet($offset)
	{
		return isset($this->aListData[$offset]) ? $this->aListData[$offset] : null;
	}

	/**
	 * {@inheritdoc}
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($offset, $value)
	{
		if (!$this->isValidObject($value)) {
			throw new \InvalidArgumentException('If object is passed it must be instance of ' . $this->dataClassName);
		}
		$this->setExternalIdentifiersRecursively($value);
		if (is_null($offset)) {
			$this->aListData[] = $value;
		} else if (is_numeric($offset)) {
			$this->aListData[$offset] = $value;
		} else {
			throw new \InvalidArgumentException('It is not allowed to use string offset keys.');
		}
	}

	/**
	 * {@inheritdoc}
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($offset)
	{
		unset($this->aListData[$offset]);
	}

	/**
	 * {@inheritdoc}
	 * @see \Iterator::current()
	 */
	public function current()
	{
		return $this->aListData[$this->listPosition];
	}

	/**
	 * {@inheritdoc}
	 * @see \Iterator::key()
	 */
	public function key()
	{
		$key = $this->listPosition;
		return $key;
	}

	/**
	 * {@inheritdoc}
	 * @see \Iterator::next()
	 */
	public function next()
	{
		++$this->listPosition;
	}

	/**
	 * {@inheritdoc}
	 * @see \Iterator::rewind()
	 */
	public function rewind()
	{
		$this->listPosition = 0;
		//It is necessary if we unset object at special position.
		$this->aListData = array_values($this->aListData);
	}

	/**
	 * {@inheritdoc}
	 * @see \Iterator::valid()
	 */
	public function valid()
	{
		return isset($this->aListData[$this->listPosition]);
	}

	/**
	 * {@inheritdoc}
	 * @see \Countable::count()
	 */
	public function count()
	{
		return count($this->aListData);
	}

	/**
	 * Checks if required object is valid.
	 *
	 * @param    mixed    $object
	 * @return   boolean  Returns TRUE if object is an instancse of correct data class
	 */
	public function isValidObject($object)
	{
		if ($this->dataClassName !== null && is_object($object)) {
			if (!($object instanceof $this->dataClassName)) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Gets an original object at position N
	 *
	 * @param    int    $position optional A position at the list. If null it will use current position.
	 * @return   mixed  Returns an original object at position N in the list.
	 */
	public function get($position = null)
	{
		if ($position === null) {
			$position = $this->listPosition;
		}
		return isset($this->aListData[$position]) ? $this->aListData[$position] : null;
	}

	/**
	 * Constructor
	 *
	 * @param     string|array|object $aListData     An array or single element of the list. It can be array of
	 *                                               the objects of dataClassName class or a single object of
	 *                                               the mentioned class.
	 * @param     string|array        $propertyName  optional A public property name of the object that is used as
	 *                                               out for query string.
	 * @param     string              $dataClassName optional A data class name of the objects which form a list.
	 */
	public function __construct($aListData = null, $propertyName = null, $dataClassName = null)
	{
		$this->aListData = ($aListData === null ? array() : (!is_array($aListData) ? ($aListData instanceof self ? $aListData->getComputed() : array(
			$aListData
		)) : $aListData));
		$this->dataClassName = $dataClassName;
		$this->propertyName = $propertyName;
		//It turns strings or arrays into dataClass objects and set appropriate properties.
		if ($this->dataClassName != null && is_array($this->propertyName)) {
			$cnt = count($this->propertyName);
			if ($cnt == 0) {
				throw new \InvalidArgumentException('Invalid propertyName argument. It must not be empty.');
			}
			foreach ($this->aListData as $k => $v) {
				if (is_string($v) || is_numeric($v)) {
					$t = new $dataClassName();
					$prop = $this->propertyName[0];
					$t->$prop = $v;
					$this->aListData[$k] = $t;
					unset($t);
				} else if (is_array($v)) {
					$t = new $dataClassName();
					foreach ($this->propertyName as $prop) {
						if (!array_key_exists($prop, $v)) {
							throw new \InvalidArgumentException('Could not find index ' . $prop . ' in ' . var_export($v, true));
						}
						$t->$prop = $v[$prop];
					}
					$this->aListData[$k] = $t;
					unset($t);
				}
			}
		}
	}

	/**
	 * Refreshes the list
	 *
	 * @throws \InvalidArgumentException
	 */
	private function refresh()
	{
		$this->list = array();
		foreach ($this->aListData as $v) {
			if (is_string($v) || is_numeric($v)) {
				$this->list[] = $v;
			} else if (is_array($v)) {
				if ($this->propertyName === null) {
					$this->list[] = $v;
				} else if (is_array($this->propertyName)) {
					$arr = array();
					foreach ($this->propertyName as $sName) {
						if (!array_key_exists($sName, $v)) {
							throw new \InvalidArgumentException(sprintf('Could not find %s index in array', $sName));
						}
						$arr[$sName] = $v[$sName];
					}
					$this->list[] = $arr;
					unset($arr);
				} else if (array_key_exists($this->propertyName, $v)) {
					$this->list[] = $v[$this->propertyName];
				} else {
					throw new \InvalidArgumentException(sprintf('Could not find %s index in array', $this->propertyName));
				}
			} else if (is_object($v)) {
				if ($this->dataClassName !== null && !($v instanceof $this->dataClassName)) {
					throw new \InvalidArgumentException('Invalid List Data argument. It must be instance of ' . $this->dataClassName);
				}
				if ($this->propertyName === null) {
					$this->list[] = $v;
				} else if (is_array($this->propertyName)) {
					$arr = array();
					foreach ($this->propertyName as $sName) {
						if (property_exists($v, $sName)) {
							$arr[$sName] = $v->{$sName};
						} else {
							$method = 'get' . ucfirst($sName);
							$arr[$sName] = $v->{$method};
						}
					}
					$this->list[] = $arr;
					unset($arr);
				} else {
					if (property_exists($v, $this->propertyName)) {
						$this->list[] = $v->{$this->propertyName};
					} else {
						$method = 'get' . ucfirst($this->propertyName);
						$this->list[] = $v->$method();
					}
				}
			}
		}
	}

	/**
	 * Appends item to the list
	 *
	 * @param   mixed   $item
	 */
	public function append($item)
	{
		if (isset($this->dataClassName) && !($item instanceof $this->dataClassName)) {
			throw new \InvalidArgumentException('Invalid value. Item must be instance of ' . $this->dataClassName . ' class.');
		}
		$this->setExternalIdentifiersRecursively($item);
		$this->aListData[] = $item;
	}

	/**
	 * Gets property name of object or a key of assocciative array
	 *
	 * @return string|array|null Returns property name of object or a key of assocciative array
	 */
	public function getPropertyName()
	{
		return $this->propertyName;
	}

	/**
	 * Gets data class name restriction
	 *
	 * @return string|null Returns data class name
	 */
	public function getDataClassName()
	{
		return $this->dataClassName;
	}

	/**
	 * Gets computed list
	 *
	 * @return array  Returns computed array of values that represent list.
	 */
	public function getComputed()
	{
		$this->refresh();
		return $this->list === null ? array() : $this->list;
	}

	/**
	 * Gets original list.
	 *
	 * Gets original data array that has been passed as argument in constructor.
	 *
	 * @return array Returns original data array that has been passed as first argument in constructor.
	 */
	public function getOriginal()
	{
		return $this->aListData;
	}

	/**
	 * Gets query parameters array.
	 *
	 * @param    string    $uriParameterName  optional Parameter name. If null it will use class property name
	 *                                                 that is provided in constructor.
	 * @return   string    Returns query parameters array looks like array ( 'parameterName.member.N' => value )
	 *                     Values are not url encoded.
	 */
	public function getQueryArray($uriParameterName = null)
	{
		$this->refresh();
		if ($uriParameterName === null) {
			if ($this->propertyName === null || is_array($this->propertyName)) {
				$uriParameterName = 'Undefined';
			} else {
				$uriParameterName = $this->uppercaseProperty($this->propertyName);
			}
		}
		$n = 1;
		$arr = array();
		foreach ($this->list as $v) {
			if (is_array($this->propertyName)) {
				foreach ((array) $this->propertyName as $prop) {
					$arr[$uriParameterName . '.member.' . $n . '.' . $this->uppercaseProperty($prop)] = $v[$prop];
				}
				$n++;
			} else {
				$arr[$uriParameterName . '.member.' . $n++] = $v;
			}
		}
		return $arr;
	}

	/**
	 * Gets uppercased property
	 *
	 * @param    string    $property
	 * @return   string    Returns uppercased property
	 */
	private function uppercaseProperty($property)
	{
		if (preg_match("/^(ssl)/", $property, $m)) {
			$ret = strtoupper($m[1]) . substr($property, strlen($m[1]));
		} else {
			$ret = ucfirst($property);
		}
		return $ret;
	}

	/**
	 * Gets an external identifier keys that are associated with this object.
	 *
	 * @return   array  Returns the list of the external identifiers.
	 */
	public function getExternalIdentifiers()
	{
		return $this->_externalKeys;
	}

	/**
	 * Sets nextToken.
	 *
	 * @param   string  $nextToken Next Token
	 */
	public function setNextToken ($nextToken)
	{
		$this->nextToken = $nextToken;
	}

	/**
	 * Gets nextToken.
	 *
	 * @return  string Returns nextToken.
	 */
	public function getNextToken ()
	{
		return $this->nextToken;
	}
}