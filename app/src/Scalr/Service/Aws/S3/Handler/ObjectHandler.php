<?php
namespace Scalr\Service\Aws\S3\Handler;

use Scalr\Service\Aws\Client\ClientResponseInterface;
use Scalr\Service\Aws\S3\DataType\ObjectData;
use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\S3Exception;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\S3\AbstractS3Handler;

/**
 * ObjectHandler
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     12.11.2012
 */
class ObjectHandler extends AbstractS3Handler
{

	/**
	 * GET Bucket (List Objects) action
	 *
	 * This implementation of the GET operation returns some or all (up to 1000) of the objects in a bucket.
	 * You can use the request parameters as selection criteria to return a subset of the objects in a bucket.
	 * To use this implementation of the operation, you must have READ access to the bucket.
	 *
	 * @param   string     $bucketName A bucket Name.
	 * @param   string     $delimiter  optional A delimiter is a character you use to group keys.
	 *                                 All keys that contain the same string between the prefix, if specified,
	 *                                 and the first occurrence of the delimiter after the prefix are grouped
	 *                                 under a single result element, CommonPrefixes. If you don't specify
	 *                                 the prefix parameter, then the substring starts at the beginning of the
	 *                                 key. The keys that are grouped under CommonPrefixes result element
	 *                                 are not returned elsewhere in the response.
	 * @param   string     $marker     optional Specifies the key to start with when listing objects in a bucket.
	 *                                 Amazon S3 lists objects in alphabetical order.
	 * @param   string     $maxKeys    optional Sets the maximum number of keys returned in the response body.
	 *                                 The response might contain fewer keys but will never contain more.
	 *                                 If there are additional keys that satisfy the search criteria
	 *                                 but were not returned because max-keys was exceeded, the response contains
	 *                                 <IsTruncated>true</IsTruncated>.To return the additional keys.
	 * @param   string     $prefix     optional Limits the response to keys that begin with the specified prefix.
	 *                                 You can use prefixes to separate a bucket into different groupings of keys.
	 *                                 (You can think of using prefix to make groups in the same way you'd use
	 *                                 a folder in a file system.)
	 * @return  ObjectList  Returns list of Objects
	 * @throws  S3Exception
	 * @throws  ClientException
	 */
	public function getList ($bucketName, $delimiter = null, $marker = null, $maxKeys = null, $prefix = null)
	{
		return $this->getS3()->getApiHandler()->listObjects($bucketName, $delimiter, $marker, $maxKeys, $prefix);
	}

	/**
	 * Gets an object
	 *
	 * This implementation of the GET operation retrieves objects from Amazon S3.
	 * To use GET, you must have READ access to the object.
	 * If you grant READ access to the anonymous user, you can return the object
	 * without using an authorization header.
	 *
	 * @param   string     $bucketName     An bucket name.
	 * @param   string     $objectName     An object key name.
	 * @param   array      $requestPars    optional An additional request query parameters. It accepts only allowed params.
	 * @param   array      $requestHeaders opitional An optional request headers. It accepts only allowed headers.
	 * @return  ClientResponseInterface    Returns response
	 * @throws  S3Exception
 	 */
	public function download ($bucketName, $objectName, array $requestPars = null, array $requestHeaders = null)
	{
		return $this->getS3()->getApiHandler()->getObject($bucketName, $objectName, $requestPars, $requestHeaders);
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
	 * @param   string     $bucketName A bucket name.
	 * @param   string     $objectName A object name.
	 * @param   string     $versionId  optional To remove a specific version of the object it must be used.
	 * @param   string     $xAmfMfa    optional The value is the concatenation of the authentication device's
	 *                                 serial number, a space, and the value displayed on your authentication device.
	 * @return  ClientResponseInterface Returns response on success or throws an exception
	 * @throws  ClientException
	 * @throws  S3Exception
	 */
	public function delete($bucketName, $objectName, $versionId = null, $xAmfMfa = null)
	{
		return $this->getS3()->getApiHandler()->deleteObject($bucketName, $objectName, $versionId, $xAmfMfa);
	}

	/**
	 * Gets ObjectData from Entity Storage.
	 *
	 * @param   string     $objectName   An object name.
	 * @return  ObjectData Returns ObjectData from entity storage if it exists or null otherwise.
	 */
	public function get ($objectName)
	{
		return $this->getS3()->getEntityManager()->getRepository('S3:Object')->find($objectName);
	}
}