<?php
namespace Scalr\Service\Aws;

/**
 * Amazon Key Management Service interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     5.9    (18.06.2015)
 *
 * @property  \Scalr\Service\Aws\Kms\Handler\KeyHandler $key
 *            Gets Key handler
 *
 * @property  \Scalr\Service\Aws\Kms\Handler\AliasHandler $alias
 *            Gets Alias handler
 *
 * @property  \Scalr\Service\Aws\Kms\Handler\GrantHandler $grant
 *            Gets Grant handler
 *
 * @method    \Scalr\Service\Aws\Kms\V20141101\KmsApi getApiHandler() getApiHandler()  Gets an KmsApi handler
 */
class Kms extends AbstractService implements ServiceInterface
{

    /**
     * API Version 20141101
     */
    const API_VERSION_20141101 = '20141101';

    /**
     * Current version of the API
     */
    const API_VERSION_CURRENT = self::API_VERSION_20141101;

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractService::getName()
     */
    public function getName()
    {
        return 'kms';
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAllowedEntities()
     */
    public function getAllowedEntities()
    {
        return ['key', 'alias', 'grant'];
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAvailableApiVersions()
     */
    public function getAvailableApiVersions()
    {
        return [self::API_VERSION_20141101];
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\ServiceInterface::getCurrentApiVersion()
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
            return 'kms.' . $region . '.amazonaws.com.cn';
        } else {
            return 'kms' . (empty($region) ? '' : '.' . $region) . '.amazonaws.com';
        }
    }
}
