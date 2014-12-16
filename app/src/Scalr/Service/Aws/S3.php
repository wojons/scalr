<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Eucalyptus;
use Scalr\Service\Aws;

/**
 * Amazon Simple Storage Service (S3) interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
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
     * @see \Scalr\Service\Aws\AbstractService::getName()
     */
    public function getName()
    {
        return 's3';
    }

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
        $aws = $this->getAws();
        if ($aws instanceof Eucalyptus) {
            $url = rtrim($aws->getUrl('s3'), '/ ');
        } else {
            $region = $this->getAws()->getRegion();
            if ($region == Aws::REGION_CN_NORTH_1)
                $url = 's3.' . $region . '.amazonaws.com.cn';
            else
                $url = 's3' . ($region && $region != Aws::REGION_US_EAST_1 ? '-' . $region : '') . '.amazonaws.com';
        }
        return $url;
    }
}
