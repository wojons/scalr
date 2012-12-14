<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws\Client\QueryClient;
use Scalr\Service\Aws\Client\QueryClient\S3QueryClient;

/**
 * AbstractService
 *
 * Ensures to provide common properties and behaviour for all Services.
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     10.10.2012
 */
abstract class AbstractService
{

	/**
	 * AWS Instance
	 *
	 * @var \Scalr\Service\Aws
	 */
	protected $aws;

	/**
	 * Entity Manager
	 *
	 * @var EntityManager
	 */
	protected $em;

	/**
	 * API Version
	 *
	 * @var string
	 */
	protected $apiVersion;

	/**
	 * Child class name
	 *
	 * @var string
	 */
	private $class;

	/**
	 * Misc. instances
	 *
	 * @var array
	 */
	private $instances;

	/**
	 * ELB API Handler intance
	 *
	 * @var ElbApi
	 */
	protected $apiHandler;

	/**
	 * Gets an API version
	 *
	 * @return string Returns an API Version
	 */
	public function getApiVersion()
	{
		return $this->apiVersion;
	}

	/**
	 * Gets low-level api handler for AWS Service
	 *
	 * @return   mixed Returns low-level api handler
	 */
	public function getApiHandler()
	{
		if (!isset($this->apiHandler)) {
			$class = get_class($this);
			$serviceName = preg_replace('/^.+\\\\([^\\\\]+)$/', '\\1', $class);
			$clientClass = __NAMESPACE__ . '\\Client\\QueryClient';
			//Some services, like Simple Storage Service, may use different query client.
			if (file_exists(__DIR__ . '/Client/QueryClient/' . $serviceName . 'QueryClient.php')) {
				$clientClass = $clientClass . '\\' . $serviceName . 'QueryClient';
			}
			$client = new $clientClass ($this->aws->getAccessKeyId(), $this->aws->getSecretAccessKey(), $this->getApiVersion(), $this->getUrl());
			$apiHandlerClass = $class
			  . '\\V' . $this->getApiVersion()
			  . '\\' . $serviceName . 'Api';
			$this->apiHandler = new $apiHandlerClass($this, $client);
		}
		return $this->apiHandler;
	}

	/**
	 * Sets API version
	 *
	 * @param    string   $apiVersion  API Version
	 * @throws   ElbException
	 */
	public function setApiVersion($apiVersion)
	{
		if (!in_array($apiVersion, $this->getAvailableApiVersions())) {
			throw new ElbException(sprintf('Version %d does not support yet.', $apiVersion));
		}
		$this->apiVersion = $apiVersion;
	}

	/**
	 * Constructor
	 *
	 * @param    \Scalr\Service\Aws   $aws  AWS Instance for the specified region and
	 *                                      that is associated with this service.
	 * @throws   ElbException
	 */
	public function __construct(\Scalr\Service\Aws $aws)
	{
		$this->aws = $aws;
		$this->class = get_class($this);
		$this->setApiVersion($this->getCurrentApiVersion());
		$this->em = EntityManager::getInstance();
	}

	/**
	 * Gets an AWS entity manager
	 *
	 * @return \Scalr\Service\Aws\EntityManager Returns an AWS entity manager
	 */
	public function getEntityManager()
	{
		return $this->em;
	}

	/**
	 * Ensures getting datatype handlers
	 *
	 * @param   string   $entityname
	 */
	public function __get($entityname)
	{
		if (in_array($entityname, $this->getAllowedEntities())) {
			$class = $this->class . '\\Handler\\' . ucfirst($entityname) . 'Handler';
			if (!isset($this->instances[$class])) {
				$this->instances[$class] = new $class($this);
			}
			return $this->instances[$class];
		}
		return null;
	}

	/**
	 * Gets an Aws instance
	 *
	 * @return \Scalr\Service\Aws Returns an AWS instance
	 */
	public function getAws()
	{
		return $this->aws;
	}
}
