<?php

namespace Scalr\Logger;

use Scalr\Logger\AuditLog\Exception\AuditLogException;
use Scalr\Logger\AuditLog\AuditLogTags;
use Scalr\DependencyInjection\Container;
use Scalr\Logger\AuditLog\LogRecord;
use Scalr\Logger\AuditLog\Documents\AbstractAuditLogDocument;
use \Mongo;
use \MongoCursor;
use \MongoCollection;

/**
 * Audit log service.
 *
 * @author   Vitaliy Demidov   <zend@i.ua>
 * @since    31.10.2012
 */
class AuditLog implements AuditLogInterface
{
	/**
	 * User instance
	 *
	 * @var \Scalr_Account_User
	 */
	private $user;

	/**
	 * Logger storage
	 *
	 * @var LoggerStorageInterface
	 */
	private $storage;

	/**
	 * Miscellaneous options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor
	 *
	 * @param   \Scalr_Account_User    $user    A Scalr_Account_User instance.
	 * @param   LoggerStorageInterface $storage A database storage provider.
	 * @param   array                  $options A required options array.
	 *                                          It should look like array('option' => value).
	 * @throws  \InvalidArgumentException
	 */
	public function __construct(\Scalr_Account_User $user, LoggerStorageInterface $storage, array $options = array())
	{
		$this->user = $user;
		$this->storage = $storage;
		$this->options = $this->getDefaultOptions();
		foreach ($this->getRequiredOptions() as $identifier) {
			if (!array_key_exists($identifier, $options)) {
				throw new \InvalidArgumentException(sprintf(
					'Missing required option. "%s" must be provided',
					$identifier
				));
			}
		}
		$this->options = array_replace_recursive($this->options, $options);
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Logger.AuditLogInterface::getUser()
	 * @return \Scalr_Account_User Returns user instance
	 */
	public function getUser()
	{
		return $this->user;
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Logger.AuditLogInterface::find()
	 */
	public function find(array $criteria, array $order, $limit)
	{
		return $this->storage->find($criteria, $order, $limit);
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Logger.AuditLogInterface::log()
	 */
	public function log($message, $tags, $object = null)
	{
		if (!$this->isEnabled()) return true;

		$user = $this->getUser();

		if (!$this->getContainer()->initialized('request') ||
			!($this->getContainer()->request instanceof \Scalr_UI_Request)) {
			$ip = '127.0.0.1';
			$envid = 0;
		} else {
			/* @var $request \Scalr_UI_Request */
			$request = $this->getContainer()->request;
			$ip = $request->getClientIp();
			if ($request->getEnvironment() === null) {
				$envid = 0;
			} else {
				$envid = $request->getEnvironment()->id;
			}
		}

		if (!($tags instanceof AuditLogTags)) {
			$tags = new AuditLogTags(!empty($tags) && is_array($tags) ? $tags : null);
		}

		$record = new LogRecord();
		$record
			->setEnvid($envid)
			->setUserid($user->getId())
			->setEmail($user->getEmail())
			->setAccountid($user->getAccountId())
			->setIp(ip2long($ip))
			->setTime(new \DateTime(null, new \DateTimeZone('UTC')))
			->setMessage($message)
			->setTags($tags)
		;
		if ($object !== null) {
			if ($object instanceof AbstractAuditLogDocument) {
				$record->setData($object);
			} else {
				$record->setData($this->getObjectDocument($object));
			}
		}

		try {
			$res = $this->storage->write($record);
		} catch (\Exception $e) {
			error_log(sprintf('AuditLog::log() failed. %s %s. Record: %s',
				get_class($e), $e->getMessage(), serialize($record)));
			$res = true;
		}

		return $res;
	}

	/**
	 * Gets document that is associated with given object.
	 *
	 * @param  object   $object  An object. This object needs to be supported with document.
	 * @return AbstractAuditLogDocument Returns appropriate document.
	 * @throws AuditLogException
	 */
	private function getObjectDocument ($object)
	{
		$mapping = self::getObjectMapping();
		$class = get_class($object);
		if (!isset($mapping[$class])) {
			throw new AuditLogException(sprintf(
				'Can not find appropriate document for the object "%s". '
			  . 'You must update %s::getObjectMapping() method.', $class, __CLASS__
			));
		}
		$documentClass = __NAMESPACE__ . '\\AuditLog\\Documents\\' . $mapping[$class] . 'Document';
		$basename = preg_replace('#^(.+\\\\)?([^\\\\]+)$#', '\\2', $class);
		if (!is_callable($documentClass . '::createFrom' . $basename)) {
			throw new AuditLogException(sprintf(
				'Cannot find method %s to obtain document.', $documentClass . '::createFrom' . $basename
			));
		}
		return call_user_func($documentClass . '::createFrom' . $basename, $object);
	}

	/**
	 * Sets container
	 * @param     Container    $container DI Container
	 * @return    AuditLog
	 */
	public function setContainer(Container $container)
	{
		$this->container = $container;
		return $this;
	}

	/**
	 * Gets container
	 *
	 * @return Container
	 */
	public function getContainer()
	{
		return $this->container;
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Logger\AuditLog.AuditLogInterface::getDefaultOptions()
	 */
	public function getDefaultOptions()
	{
		return array('enabled' => true);
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Logger\AuditLog.AuditLogInterface::getRequiredOptions()
	 */
	public function getRequiredOptions()
	{
		return array();
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Logger.AuditLogInterface::isEnabled()
	 */
	public function isEnabled()
	{
		return $this->options['enabled'] ? true : false;
	}

	/**
	 * Gets object mapping.
	 *
	 * @return array Returns associative array that contains mapping for the objects and
	 *                       evaluates appropriate documents.
	 */
	public static function getObjectMapping ()
	{
		$map = array(
			'DBFarm'  => 'Farm',
		);
		return $map;
	}
}