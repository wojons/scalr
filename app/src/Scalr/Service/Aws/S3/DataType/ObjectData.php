<?php
namespace Scalr\Service\Aws\S3\DataType;

use Scalr\Service\Aws\Client\ClientResponseInterface;
use Scalr\Service\Aws\S3Exception;
use Scalr\Service\Aws\S3\AbstractS3DataType;

/**
 * ObjectData
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     20.11.2012
 * @method    \Scalr\Service\Aws\S3\DataType\OwnerData       getOwner()      getOwner()                 Returns an object owner.
 * @method    \Scalr\Service\Aws\S3\DataType\ObjectData      setOwner()      setOwner(OwnerData $owner) Sets an object owner.
 * @method    string                                         getBucketName() getBucketName()            Gets a bucket name which object relates to.
 * @method    \Scalr\Service\Aws\S3\DataType\ObjectData      setBucketName() setBucketName($bucketName) Sets a bucket name which object relates to.
 */
class ObjectData extends AbstractS3DataType
{
	/**
	 * List of external identifier names.
	 *
	 * @var array
	 */
	protected $_externalKeys = array('bucketName');

	/**
	 * List of the public properties
	 * which is managed by magic getter and setters internally.
	 *
	 * @var  array
	 */
	protected $_properties = array('owner');

	/**
	 * The object's key
	 *
	 * @var string
	 */
	public $objectName;

	/**
	 * The entity tag is an MD5 hash of the object.
	 * The ETag only reflects changes to the contents of an object, not its metadata.
	 *
	 * @var string
	 */
	public $eTag;

	/**
	 * Date and time the object was last modified.
	 *
	 * @var \DateTime
	 */
	public $lastModified;

	/**
	 * Size in bytes of the object
	 *
	 * @var string
	 */
	public $size;

	/**
	 * Always STANDARD
	 *
	 * @var string
	 */
	public $storageClass;

	/**
	 * {@inheritdoc}
	 * @see Scalr\Service\Aws\S3.AbstractS3DataType::throwExceptionIfNotInitialized()
	 */
	protected function throwExceptionIfNotInitialized()
	{
		parent::throwExceptionIfNotInitialized();
		if ($this->objectName === null) {
			throw new S3Exception(sprintf(
				'Property objectName has not been initialized yet for the %s', get_class($this)
			));
		}
		if ($this->getBucketName() === null) {
			throw new S3Exception(sprintf(
				'Property bucketName has not been initialized yet for the %s', get_class($this)
			));
		}
	}

	/**
	 * Gets an object
	 *
	 * This implementation of the GET operation retrieves objects from Amazon S3.
	 * To use GET, you must have READ access to the object.
	 * If you grant READ access to the anonymous user, you can return the object
	 * without using an authorization header.
	 *
	 * @param   array      $requestPars    optional An additional request query parameters. It accepts only allowed params.
	 * @param   array      $requestHeaders opitional An optional request headers. It accepts only allowed headers.
	 * @return  ClientResponseInterface    Returns response
	 * @throws  S3Exception
 	 */
	public function download (array $requestPars = null, array $requestHeaders = null)
	{
		$this->throwExceptionIfNotInitialized();
		return $this->getS3()->object->download($this->getBucketName(), $this->objectName, $requestPars, $requestHeaders);
	}

	/**
	 * DELETE Object action.
	 *
	 * The DELETE operation removes the null version (if there is one) of an object and inserts a delete marker,
	 * which becomes the latest version of the object. If there isn't a null version, Amazon S3 does not remove
	 * any objects
	 *
	 * To remove a specific version, you must be the bucket owner and you must use the versionId
	 * subresource. Using this subresource permanently deletes the version. If the object deleted is a Delete
	 * Marker, Amazon S3 sets the response header, x-amz-delete-marker, to true.
	 * If the object you want to delete is in a bucket where the bucket versioning configuration is MFA Delete
	 * enabled, you must include the x-amz-mfa request header in the DELETE versionId request. Requests
	 * that include x-amz-mfa must use HTTPS.
	 *
	 * @param   string     $versionId  optional To remove a specific version of the object it must be used.
	 * @param   string     $xAmfMfa    optional The value is the concatenation of the authentication device's
	 *                                 serial number, a space, and the value displayed on your authentication device.
	 * @return  ClientResponseInterface Returns response on success or throws an exception
	 * @throws  ClientException
	 * @throws  S3Exception
	 */
	public function delete($versionId = null, $xAmfMfa = null)
	{
		$this->throwExceptionIfNotInitialized();
		return $this->getS3()->object->delete($this->getBucketName(), $this->objectName, $versionId, $xAmfMfa);
	}
}