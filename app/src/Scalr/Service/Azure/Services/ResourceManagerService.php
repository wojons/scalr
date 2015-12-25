<?php

namespace Scalr\Service\Azure\Services;

use Scalr\Service\Azure;
use Scalr\Service\Azure\AbstractService;
use Scalr\Service\AzureException;

/**
 * Azure resource manager service interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 *
 * @property \Scalr\Service\Azure\Services\ResourceManager\Api\ResourceGroups $resourceGroup
 */
class ResourceManagerService extends AbstractService
{
    /**
     * Service name for ResourceManager.
     */
    const SERVICE_RESOURCE_GROUP = 'resourceGroup';

    /**
     * Api version of the resource
     */
    const RESOURCE_MANAGER_API_VERSION = '2015-01-01';

    /**
     * List of instances of ResourceManager services.
     *
     * @var array
     */
    private $services = [];

    /**
     * Returns API version that this client use.
     *
     * @return string API Version
     */
    public function getApiVersion()
    {
        return self::RESOURCE_MANAGER_API_VERSION;
    }

    /**
     * Gets url for each service
     *
     * @return string
     */
    public function getServiceUrl()
    {
        return Azure::URL_MANAGEMENT_WINDOWS;
    }

    /**
     * List of all available services.
     *
     * @return array Service names
     */
    private function getAvailableServices()
    {
        return [
            self::SERVICE_RESOURCE_GROUP => self::SERVICE_RESOURCE_GROUP
        ];
    }

    /**
     * Magic getter.
     *
     * @param string $name
     * @return AbstractService
     * @throws AzureException
     */
    public function __get($name)
    {
        $services = $this->getAvailableServices();

        if (isset($services[$name])) {
            if (!isset($this->services[$name])) {
                $apiPath = __NAMESPACE__ . '\\ResourceManager\\Api\\' . ucfirst($services[$name]) . 's';
                $this->services[$name] = new $apiPath ($this);
            }

            return $this->services[$name];
        }

        throw new AzureException(sprintf('Invalid Service name "%s" for ResourceManager', $name));
    }

}