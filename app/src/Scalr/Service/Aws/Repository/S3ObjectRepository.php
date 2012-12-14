<?php
namespace Scalr\Service\Aws\Repository;

use Scalr\Service\Aws\S3\DataType\ObjectData;
use Scalr\Service\Aws\AbstractRepository;

/**
 * S3ObjectRepository
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     20.11.2012
 */
class S3ObjectRepository extends AbstractRepository
{

	/**
	 * Reflection class name.
	 * @var string
	 */
	private static $reflectionClassName = 'Scalr\\Service\\Aws\\S3\\DataType\\ObjectData';

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.AbstractRepository::getReflectionClassName()
	 */
	public function getReflectionClassName()
	{
		return self::$reflectionClassName;
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.AbstractRepository::getIdentifier()
	 */
	public function getIdentifier ()
	{
		return 'objectName';
	}

	/**
	 * Finds one element in entity manager by id
	 *
	 * @param    string       $name   A object name
	 * @return   ObjectData   Returns ObjectData or NULL if nothing found.
	 */
	public function find ($name)
	{
		return parent::find($name);
	}
}