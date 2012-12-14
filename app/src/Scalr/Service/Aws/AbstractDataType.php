<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws\Elb\AbstractElbListDataType;
use Scalr\Service\Aws\Elb\AbstractElbDataType;
use Scalr\Service\Aws\DataType\ListDataType;

/**
 * AbstractDataType
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     10.10.2012
 */
abstract class AbstractDataType extends AbstractServiceRelatedType
{

	/**
	 * List of external identifier names.
	 *
	 * @var array
	 */
	protected $_externalKeys = array();

	/**
	 * List of the public properties
	 * which is managed by magic getter and setters internally.
	 *
	 * @var  array
	 */
	protected $_properties = array();

	/**
	 * Reflection class of this object
	 *
	 * @var \ReflectionClass
	 */
	private $reflectionClass;

	/**
	 * Data for the properties that is managed internally.
	 *
	 * @var array
	 */
	private $propertiesData = array();

	/**
	 * List of identifier values
	 *
	 * @var array
	 */
	private $externalIdentifierValues = array();

	/**
	 * Known hashes
	 *
	 * @var array
	 */
	private $known = array();

	/**
	 * Original xml that is received from service
	 *
	 * @var string
	 */
	private $originalXml;

	/**
	 * Sets original xml that is received in response from service
	 *
	 * @param   string     $xml  XML string
	 * @return  AbstractDataType
	 */
	public function setOriginalXml($xml)
	{
		$this->originalXml = $xml;
		return $this;
	}

	/**
	 * Gets an original XML that is received in response from service.
	 *
	 * @return string Returns XML
	 */
	public function getOriginalXml()
	{
		return $this->originalXml;
	}

	/**
	 * Resets object including internal properties values
	 * keys for which are defined in protected $_properties array.
	 */
	public function resetObject()
	{
		$props = $this->getReflectionClass()->getProperties(\ReflectionProperty::IS_PUBLIC);
		/* @var $prop \ReflectionProperty */
		foreach ($props as $prop) {
			$prop->setValue($this, null);
		}
		//Resets an internal properties as well
		foreach ($this->_properties as $prop) {
			if (isset($this->propertiesData[$prop])) {
				unset($this->propertiesData[$prop]);
			}
		}
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
		return parent::__get($name);
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
			$this->setExternalIdentifiersRecursively($data);
			$this->propertiesData[$name] = $data;
		} else {
			throw new \InvalidArgumentException(
				sprintf('Unknown property "%s" for the object %s', $name, get_class($this))
			);
		}
	}

	/**
	 * Sets external identifiers recursively
	 *
	 * @param  mixed  $inner
	 */
	protected function setExternalIdentifiersRecursively(&$inner)
	{
		$this->known = array();
		$this->_setExternalIdentifiersRecursively($this, $inner);
	}

	/**
	 * Sets external identifiers recursively
	 *
	 * @param   object  $holder
	 * @param   object  $inner
	 */
	private function _setExternalIdentifiersRecursively(&$holder, &$inner)
	{
		if ($inner !== null) {
			if ($inner instanceof ListDataType) {
				$dataSet = array_merge(array($inner), $inner->getOriginal());
			} else {
				$dataSet = array($inner);
			}
			//Property inheritance pattern
			foreach ($dataSet as $object) {
				if (is_object($object) && method_exists($object, 'getExternalIdentifiers')) {
					$hash = spl_object_hash($object);
					//Prevents from endless loops.
					if (isset($this->known[$hash])) return;
					$this->known[$hash] = true;
					//Distributes Service interface instances
					if (($object instanceof AbstractServiceRelatedType)) {
						$ins = $object->getServiceNames();
						if (!empty($ins)) {
							$ths = $this->getServiceNames();
							if ($holder instanceof AbstractServiceRelatedType) {
								$hs = $holder->getServiceNames();
							} else $hs = array();
							if (!empty($ths) || !empty($hs)) {
								foreach ($ins as $sn) {
									$fgetsn = 'get' . ucfirst($sn);
									$fsetsn = 'set' . ucfirst($sn);
									if (in_array($sn, $ths) && $this->$fgetsn() !== null) {
										$object->$fsetsn($this->$fgetsn());
									} else if (in_array($sn, $hs) && $holder->$fgetsn() !== null) {
										$object->$fsetsn($holder->$fgetsn());
									}
								}
							}
							unset($ths);
							unset($hs);
						}
						unset($ins);
					}
					$externalIds = $object->getExternalIdentifiers();
					foreach ($externalIds as $key) {
						$property = ucfirst($key);
						$setProperty = 'set' . $property;
						$getProperty = 'get' . $property;
						if (property_exists($holder, $key) && $holder->$key !== null) {
							$object->$setProperty($holder->$key);
						} else if ((in_array($key, $holder->getPropertiesForInheritance()) ||
							        in_array($key, $holder->getExternalIdentifiers())) && $holder->$getProperty() !== null) {
							$object->$setProperty($holder->$getProperty());
						}
					}
					if ($object instanceof AbstractDataType) {
						$props = $object->getPropertiesForInheritance();
						foreach ($props as $p) {
							$getProperty = 'get' . $p;
							$sub = $object->$getProperty();
							if (is_object($sub)) {
								$this->_setExternalIdentifiersRecursively($object, $sub);
							}
							unset($sub);
						}
					}
				}
			}
		}
	}

	/**
	 * It allows to get|set an external identifier value or internal property value
	 *
	 * @param  string   $name
	 * @return mixed
	 */
	public function __call($name, $arguments)
	{
		if (preg_match('/^(get|set)([A-Za-z0-9_]+)$/', $name, $m)) {
			$identifier = lcfirst($m[2]);
			if ($m[1] == 'set' && count($arguments) !== 1) {
				if (count($arguments) !== 1) {
					throw new \InvalidArgumentException('Only 1 argument is expected for this method.');
				}
			}
			if (in_array($identifier, $this->getServiceNames())) {
				//Ensures availability the service interfaces by getters and setters
				return parent::__call($name, $arguments);
			} else if (in_array($identifier, $this->_externalKeys)) {
				if ($m[1] == 'get') {
					return array_key_exists($identifier, $this->externalIdentifierValues) ? $this->externalIdentifierValues[$identifier] : null;
				} else {
					//Set is expected to be here.
					$this->externalIdentifierValues[$identifier] = $arguments[0];
					return $this;
				}
			} else if (in_array($identifier, $this->_properties)) {
				if ($m[1] == 'get') {
					return array_key_exists($identifier, $this->propertiesData) ? $this->propertiesData[$identifier] : null;
				} else {
					//Set property is expected to be here.
					$this->setExternalIdentifiersRecursively($arguments[0]);
					$this->propertiesData[$identifier] = $arguments[0];
					return $this;
				}
			} else {
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
		throw new \RuntimeException('Method ' . $name . ' does not exist in ' . __CLASS__);
	}

	/**
	 * Gets a reflection class of this object
	 *
	 * @return \ReflectionClass
	 */
	public function getReflectionClass()
	{
		if (!isset($this->reflectionClass)) {
			$this->reflectionClass = new \ReflectionClass($this);
		}
		return $this->reflectionClass;
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
	 * Get the properties which are used for inheritance properties purposes.
	 *
	 * @return array Returns a list of the public properties
	 *               which is managed by magic getter and setters internally.
	 */
	public function getPropertiesForInheritance()
	{
		return $this->_properties;
	}

	/**
	 * Gets data as array.
	 *
	 * @return array Returns data as array
	 */
	public function toArray(&$known = null)
	{
		$arr = array();
		if (is_null($known)) {
			$known = array();
		}
		$id = spl_object_hash($this);
		if (array_key_exists($id, $known)) return '**recursion**';
		$known[$id] = true;
		$trait = function (&$val) use($known)
		{
			if (is_object($val)) {
				if ($val instanceof AbstractDataType) {
					$val = $val->toArray($known);
				} else {
					$val = (array) $val;
				}
			}
		};
		if ($this instanceof ListDataType) {
			foreach ($this->getOriginal() as $val) {
				$trait($val);
				$arr[] = $val;
			}
		} else {
			$props = $this->getReflectionClass()->getProperties(\ReflectionProperty::IS_PUBLIC);
			/* @var $prop \ReflectionProperty */
			foreach ($props as $prop) {
				$val = $prop->getValue($this);
				$trait($val);
				$arr[$prop->getName()] = $val;
			}
			//Resets an internal properties as well
			foreach ($this->_properties as $prop) {
				if (isset($this->propertiesData[$prop])) {
					$val = $this->propertiesData[$prop];
					$trait($val);
				} else
					$val = null;
				$arr[$prop] = $val;
			}
		}
		return $arr;
	}

	/**
	 * Gets data as XML document
	 *
	 * @return  string Returns object as XML document
	 */

	/**
	 * Gets object as XML
	 *
	 * @param   bool       $returnAsDom  optional Should it return DOMDocument object or plain xml string.
	 *                                            If it's true it will return DOMDocument.
	 * @param   array      $known        optional It's for internal usage
	 * @return  \DOMDocument|string      Returns object converted into either XML string or DOMDocument, depends on
	 *                                   returnAsDom option.
	 */
	public function toXml($returnAsDom = false, &$known = null)
	{
		//TODO this method should be implemented
		throw new \Exception('This method has not been implemented yet.');
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.AbstractServiceRelatedType::getServiceNames()
	 */
	public function getServiceNames()
	{
		//This method is supposed to be overridden, but sometimes it does not need.
		return array();
	}
}