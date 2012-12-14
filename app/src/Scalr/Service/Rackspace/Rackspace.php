<?php
namespace Scalr\Service\Rackspace;

use Scalr\Service\Rackspace\Services\ServersService;
use Scalr\Service\Rackspace\Exception\RackspaceException;
use GlobIterator;
use FilesystemIterator;

/**
 * Rackspace api library
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    04.12.2012
 *
 * @property ServersService $servers  A Next Generation Cloud Servers service interface
 */
class Rackspace
{
	/**
	 * Available services
	 * @var array
	 */
	private static $availableServices;

	/**
	 * Service instances cache
	 * @var array
	 */
	private $serviceInstances;

	/**
	 * Gets a list of available services
	 *
	 * @return  array Returns the list of available services looks like array(serviceName => className)
	 */
	public static function getAvailableServices()
	{
		if (!isset(self::$availableServices)) {
			$ns = __NAMESPACE__ . '\\Services';
			$iterator = new GlobIterator(__DIR__ . '/Services/*Service.php', FilesystemIterator::KEY_AS_FILENAME);
			/* @var $item \SplFileInfo */
			foreach ($iterator as $item) {
				$class = $ns . '\\' . substr($iterator->key(), 0, -4);
				if (get_parent_class($class) == $ns . '\\AbstractService') {
					self::$availableServices[$class::getName()] = $class;
				}
			}
		}
		return self::$availableServices;
	}

	/**
	 * It's used to retrieve service interface instances as public properties
	 */
	public function __get($name)
	{
		$available = self::getAvailableServices();
		if (isset($available[$name])) {
			if (!isset($this->serviceInstances[$name])) {
				$this->serviceInstances[$name] = new $available[$name];
			}
			return $this->serviceInstances[$name];
		}
		throw new RackspaceException(sprintf('Invalid Service name "%s" for the Rackspace', $name));
	}
}