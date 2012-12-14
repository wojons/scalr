<?php
namespace Scalr\Service\Aws\Repository;

use Scalr\Service\Aws\S3\DataType\BucketData;
use Scalr\Service\Aws\AbstractRepository;

/**
 * S3BucketRepository
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     12.11.2012
 */
class S3BucketRepository extends AbstractRepository
{

	/**
	 * Reflection class name.
	 * @var string
	 */
	private static $reflectionClassName = 'Scalr\\Service\\Aws\\S3\\DataType\\BucketData';

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
		return 'bucketName';
	}

	/**
	 * Finds one element in entity manager by id
	 *
	 * @param    string       $name   A bucket name
	 * @return   BucketData   Returns BucketData or NULL if nothing found.
	 */
	public function find ($name)
	{
		return parent::find($name);
	}
}