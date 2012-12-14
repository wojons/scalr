<?php

namespace Scalr\Logger\AuditLog\Documents;

use \MongoDate;

/**
 * abstract document
 *
 * @author   Vitaliy Demidov   <zend@i.ua>
 * @since    31.10.2012
 */
abstract class AbstractAuditLogDocument
{

	/**
	 * @var string
	 */
	protected $datatype;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		//It's required to nested objects support
		$this->datatype = preg_replace('#^(.+\\\\)?([^\\\\]+)Document$#', '\\2', get_class($this));
	}

	/**
	 * Gets an datatype
	 *
	 * @return string Returns data type
	 */
	public function getDatatype()
	{
		return $this->datatype;
	}

	/**
	 * Transforms object to array an returns its value.
	 *
	 * @return   array Returns object as array.
	 */
	public function toArray()
	{
		$result = array();
		$reflectionClass = new \ReflectionClass($this);
		/* @var $refProperty \ReflectionProperty */
		foreach ($reflectionClass->getProperties() as $refProperty) {
			$propertyname = $refProperty->getName();
			$getfn = 'get' . ucfirst($propertyname);
			/* @var $reflectionMethod \ReflectionMethod */
			if ($refProperty->isPublic()) {
				$r = $refProperty->getValue($this);
			} elseif (method_exists($this, $getfn) &&
				      ($reflectionMethod = $reflectionClass->getMethod($getfn)) &&
				       $reflectionMethod->isPublic()) {
				$r = $reflectionMethod->invoke($this);
				unset($reflectionMethod);
			} else continue;
			if ($r instanceof AbstractAuditLogDocument) {
				$r = $r->toArray();
			} elseif ($r instanceof \DateTime) {
				$r = $r->getTimestamp();
			} elseif (is_object($r)) {
				$r = (array) $r;
			}
			$result[$propertyname] = $r;
			unset($r);
		}
		return $result;
	}

	/**
	 * Creates document from given array
	 *
	 * @param   array    $data
	 * @return  AbstractAuditLogDocument Returns new document
	 */
	public static function createFromArray(array $data)
	{
		$class = get_called_class();
		$document = new $class;
		$refclass = new \ReflectionClass($document);
		foreach ($data as $k => $v) {
			$setm = 'set' . ucfirst($k);
			if (method_exists($document, $setm) &&
				($refmethod = $refclass->getMethod($setm)) !== null &&
				$refmethod->isPublic()) {
				$pars = $refmethod->getParameters();
				//It also supports nested objects
				if (isset($pars[0]) && $pars[0]->getClass() !== null) {
					if (is_a($pars[0]->getClass()->getName(), __CLASS__) && isset($v['datatype'])) {
						$dclass = __NAMESPACE__ . '\\' . $v['datatype'] . 'Document';
						$v = $dclass::createFromArray($v);
					} else if ($pars[0]->getClass()->getName() == 'DateTime') {
						if ($v instanceof MongoDate) {
							$t = $v->sec;
						} elseif (!is_numeric($v)) {
							$t = strtotime($v);
						} else {
							$t = $v;
						}
						$v = new \DateTime(null, new \DateTimeZone('UTC'));
						$v->setTimestamp($t);
						unset($t);
					}
				}
				$document->$setm($v);
				unset($refmethod);
			} elseif (property_exists($document, $k) && $refclass->getProperty($k)->isPublic()) {
				$document->$k = $v;
			}
		}
		return $document;
	}
}