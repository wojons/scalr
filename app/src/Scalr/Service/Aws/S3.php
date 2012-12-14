<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws\S3\Handler\ObjectHandler;
use Scalr\Service\Aws\S3\Handler\BucketHandler;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\DataType\ErrorData;
use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Client\QueryClient;
use Scalr\Service\Aws;
use Scalr\Service\Aws\S3\V20060301\S3Api;

/**
 * Amazon Simple Storage Service (S3) interface
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     12.11.2012
 * @method    \Scalr\Service\Aws\S3\V20060301\S3Api getApiHandler() getApiHandler() Gets an S3Api handler.
 * @property  \Scalr\Service\Aws\S3\Handler\BucketHandler $bucket An Bucket service interface handler.
 * @property  \Scalr\Service\Aws\S3\Handler\ObjectHandler $object An Object service interface handler.
 */
class S3 extends AbstractService implements ServiceInterface
{

	/**
	 * API Version 20060301
	 */
	const API_VERSION_20060301 = '20060301';

	/**
	 * Current version of the API
	 */
	const API_VERSION_CURRENT = self::API_VERSION_20060301;

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.ServiceInterface::getAllowedEntities()
	 */
	public function getAllowedEntities()
	{

		return array('bucket', 'object');
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.ServiceInterface::getAvailableApiVersions()
	 */
	public function getAvailableApiVersions()
	{
		return array(self::API_VERSION_20060301);
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.ServiceInterface::getCurrentApiVersion()
	 */
	public function getCurrentApiVersion()
	{
		return self::API_VERSION_CURRENT;
	}

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws.ServiceInterface::getUrl()
	 */
	public function getUrl()
	{
		return 's3.amazonaws.com';
	}
}

/*
 TODO Following Simple Storage Service API actions need to be implemented:
		GET Bucket Object versions
		PUT Bucket versioning
		LIST Multipart Uploads
 */