<?php
namespace Scalr\Service\OpenStack\Services;

use Scalr\Service\OpenStack\OpenStack;
use Scalr\Service\OpenStack\Client\RestClientResponse;
use Scalr\Service\OpenStack\Exception\RestClientException;

/**
 * OpenStack Object Storage service
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.2 (26.02.2014)
 *
 * @method   \Scalr\Service\OpenStack\Services\Swift\V1\SwiftApi getApiHandler()
 *           getApiHandler()
 *           Gets an Object Store API handler for the specific version
 */
class SwiftService extends AbstractService implements ServiceInterface
{

    const VERSION_V1 = 'V1';

    const VERSION_DEFAULT = self::VERSION_V1;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::getType()
     */
    public static function getType()
    {
        return OpenStack::SERVICE_OBJECT_STORE;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Services.ServiceInterface::getVersion()
     */
    public function getVersion()
    {
        return self::VERSION_DEFAULT;
    }

    /**
     * GET Service action
     *
     * @return  RestClientResponse     Returns raw response
     * @throws  RestClientException
     */
    public function describeService()
    {
        return $this->getApiHandler()->describeService();
    }

    /**
     * POST Service action
     *
     * @param   array      $options    The array of the options to set
     * @return  RestClientResponse     Returns raw response
     * @throws  RestClientException
     */
    public function updateService($options)
    {
        return $this->getApiHandler()->updateService($options);
    }
}