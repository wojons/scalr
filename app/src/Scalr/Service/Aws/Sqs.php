<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws;
/**
 * Amazon Simple Queue Service (SQS) interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     06.11.2012
 * @property-read  \Scalr\Service\Aws\Sqs\Handler\QueueHandler   $queue    A SQS Queue handler.
 * @property-read  \Scalr\Service\Aws\Sqs\Handler\MessageHandler $message  A SQS Message handler.
 * @method \Scalr\Service\Aws\Sqs\V20111001\SqsApi getApiHandler() getApiHandler() Gets an SqsApi handler.
 */
class Sqs extends AbstractService implements ServiceInterface
{

    /**
     * API Version 20111001
     */
    const API_VERSION_20111001 = '20111001';

    /**
     * Current version of the API
     */
    const API_VERSION_CURRENT = self::API_VERSION_20111001;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAllowedEntities()
     */
    public function getAllowedEntities()
    {
        return array('queue', 'message');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAvailableApiVersions()
     */
    public function getAvailableApiVersions()
    {
        return array(self::API_VERSION_20111001);
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
        $region = $this->getAws()->getRegion();

        if (strpos($region, 'cn-') === 0) {
            return 'sqs.' . $region . '.amazonaws.com.cn';
        } else {
            return 'sqs.' . $region . '.amazonaws.com';
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractService::getName()
     */
    public function getName()
    {
        return 'sqs';
    }
}
