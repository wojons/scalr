<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws;
/**
 * Amazon CloudWatch web service interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     24.10.2012
 * @property-read  \Scalr\Service\Aws\CloudWatch\Handler\MetricHandler $metric A Metric handler that is the layer for the related api calls.
 * @method \Scalr\Service\Aws\CloudWatch\V20100801\CloudWatchApi getApiHandler() getApiHandler()
 */
class CloudWatch extends AbstractService implements ServiceInterface
{

    /**
     * API Version 20100801
     */
    const API_VERSION_20100801 = '20100801';

    /**
     * Current version of the API
     */
    const API_VERSION_CURRENT = self::API_VERSION_20100801;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractService::getCurrentApiVersion()
     */
    public function getCurrentApiVersion()
    {
        return self::API_VERSION_CURRENT;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractService::getAvailableApiVersions()
     */
    public function getAvailableApiVersions()
    {
        return array(
            self::API_VERSION_20100801
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractService::getUrl()
     */
    public function getUrl()
    {
        $region = $this->getAws()->getRegion();
        if ($region == Aws::REGION_US_GOV_WEST_1) {
            return 'monitoring.us-gov-west-1.amazonaws.com';
        } elseif ($region == Aws::REGION_CN_NORTH_1) {
            return 'monitoring.cn-north-1.amazonaws.com.cn';
        }
        
        return 'monitoring.' . $region . '.amazonaws.com';
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractService::getAllowedEntities()
     */
    public function getAllowedEntities()
    {
        return array('metric', 'alarm');
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractService::getName()
     */
    public function getName()
    {
        return 'monitoring';
    }
}
