<?php

namespace Scalr\Service\Azure\Services;

use Scalr\Service\Azure;
use Scalr\Service\Azure\AbstractService;
use Scalr\Service\AzureException;

/**
 * Azure storage service interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 *
 * @property \Scalr\Service\Azure\Services\Storage\Api\Accounts $account
 */
class StorageService extends AbstractService
{
    /**
     * Part of a endpoint url for Compute Service
     */
    const ENDPOINT_MICROSOFT_STORAGE = '/providers/Microsoft.Storage';

    /**
     * Service name for Storage Account.
     */
    const SERVICE_ACCOUNT = 'account';

    /**
     * Api version of the resource
     */
    const STORAGE_API_VERSION = '2015-05-01-preview';

    /**
     * List of instances of Storage services.
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
        return self::STORAGE_API_VERSION;
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
            self::SERVICE_ACCOUNT => self::SERVICE_ACCOUNT
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
                $apiPath = __NAMESPACE__ . '\\Storage\\Api\\' . ucfirst($services[$name]) . 's';
                $this->services[$name] = new $apiPath ($this);
            }

            return $this->services[$name];
        }

        throw new AzureException(sprintf('Invalid Service name "%s" for Storage', $name));
    }

}