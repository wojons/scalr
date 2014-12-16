<?php
namespace Scalr\Service\Aws;

/**
 * Amazon Route53 web service interface
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 * @property  \Scalr\Service\Aws\Route53\Handler\HealthHandler $health
 *            Gets HealthHandler
 *
 * @property  \Scalr\Service\Aws\Route53\Handler\RecordHandler $record
 *            Gets RecordHandler
 *
 * @property  \Scalr\Service\Aws\Route53\Handler\ZoneHandler $zone
 *            Gets ZoneHandler
 *
 * @method    \Scalr\Service\Aws\Route53\V20130401\Route53Api getApiHandler()
 *            getApiHandler()
 *            Gets API handler
 */
class Route53 extends AbstractService implements ServiceInterface
{

    /**
     * API Version 20130401
     */
    const API_VERSION_20130401 = '20130401';

    /**
     * Current version of the API
     */
    const API_VERSION_CURRENT = self::API_VERSION_20130401;

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
            self::API_VERSION_20130401
        );
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractService::getUrl()
     */
    public function getUrl()
    {
        return 'route53.amazonaws.com';

        /* $region = $this->getAws()->getRegion();


        if ($region == Aws::REGION_US_GOV_WEST_1) {
            return 'route53.us-gov-west-1.amazonaws.com';
        } elseif ($region == Aws::REGION_CN_NORTH_1) {
            return 'route53.cn-north-1.amazonaws.com.cn';
        }


        return 'route53.' . $region . '.amazonaws.com';
        */
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractService::getAllowedEntities()
     */
    public function getAllowedEntities()
    {
        return array('zone', 'record', 'health');
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractService::getName()
     */
    public function getName()
    {
        return 'route53';
    }
}
