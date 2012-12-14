<?php
namespace Scalr\Service\Aws;

/**
 * AbstractRepository
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     03.10.2012
 */
abstract class AbstractRepository
{
	/**
	 * Storage of the intances
	 *
	 * @var array
	 */
	private static $instance;

	/**
	 * Gets an EntityManager
	 *
	 * @return EntityManager
	 */
	public function getEntityManager()
	{
		return EntityManager::getInstance();
	}

	protected function __construct()
	{
	}

	/**
	 * Gets reflection class name.
	 *
	 * The name of the class that represents entity object.
	 *
	 * @return    string  Returns reflection class name
	 */
	abstract public function getReflectionClassName();

	/**
	 * Gets an identifier name(s)
	 *
	 * @return string|array Returns the Identifier
	 */
	abstract public function getIdentifier();

	/**
	 * Finds one element in entity manager by its id
	 *
	 * @param    string      $id  Element Id (Public property of entity)
	 * @return   object|null Returns one object or NULL if nothing found.
	 */
	public function find($id)
	{
		$id = (array) $id;
		$em = $this->getEntityManager();
		$storage = $em->getStorage($this->getReflectionClassName());
		return isset($storage) ? $storage->find($id) : null;
	}

	/**
	 * Finds one element by required criteria.
	 *
	 * @param     array    $criteria An assoc array with search query. It looks like array (propertyname => value)
	 * @return    object|null Returns an entity or null if nothing found.
	 */
	public function findOneBy(array $criteria)
	{
		$em = $this->getEntityManager();
		$storage = $em->getStorage($this->getReflectionClassName());
		foreach ($storage as $obj) {
			$c = true;
			foreach ($criteria as $propertyName => $value) {
				if ($obj->{$propertyName} !== $value) {
					$c = false;
					break;
				}
			}
			if ($c === true) {
				return $obj;
			}
		}
		return null;
	}

	/**
	 * Gets an instance
	 *
	 * @return AbstractRepository
	 */
	public static function getInstance ()
	{
		$class = get_called_class();
		if (!isset(self::$instance[$class])) {
			self::$instance[$class] = new $class;
		}
		return self::$instance[$class];
	}
}