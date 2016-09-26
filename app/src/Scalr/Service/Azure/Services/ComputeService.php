<?php

namespace Scalr\Service\Azure\Services;

use Scalr\Service\Azure;
use Scalr\Service\Azure\AbstractService;
use Scalr\Service\AzureException;

/**
 * Azure compute service interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 *
 * @property \Scalr\Service\Azure\Services\Compute\Api\VirtualMachines $virtualMachine
 *
 * @property \Scalr\Service\Azure\Services\Compute\Api\ResourceExtensions $resourceExtension
 *
 * @property \Scalr\Service\Azure\Services\Compute\Api\AvailabilitySets $availabilitySet
 *
 * @property \Scalr\Service\Azure\Services\Compute\Api\Locations $location
 *
 */
class ComputeService extends AbstractService
{
    /**
     * Part of a endpoint url for Compute Service
     */
    const ENDPOINT_MICROSOFT_COMPUTE = '/providers/Microsoft.Compute';

    /**
     * Service name for Compute.
     */
    const SERVICE_VIRTUAL_MACHINE = 'virtualMachine';

    /**
     * Service name for Compute.
     */
    const SERVICE_RESOURCE_EXTENSION = 'resourceExtension';

    /**
     * Service name for Compute.
     */
    const SERVICE_AVAILABILITY_SET = 'availabilitySet';

    /**
     * Service name for Compute.
     */
    const SERVICE_LOCATION = 'location';

    /**
     * List of instances of Compute services.
     *
     * @var array
     */
    private $services = [];

    /**
     * Api version of the resource
     */
    const RESOURCE_API_VERSION = '2015-05-01-preview';

    /**
     * Returns API version that this client use.
     *
     * @return string API Version
     */
    public function getApiVersion()
    {
        return self::RESOURCE_API_VERSION;
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
            self::SERVICE_VIRTUAL_MACHINE       => self::SERVICE_VIRTUAL_MACHINE,
            self::SERVICE_RESOURCE_EXTENSION    => self::SERVICE_RESOURCE_EXTENSION,
            self::SERVICE_AVAILABILITY_SET      => self::SERVICE_AVAILABILITY_SET,
            self::SERVICE_LOCATION              => self::SERVICE_LOCATION
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
                $apiPath = __NAMESPACE__ . '\\Compute\\Api\\' . ucfirst($services[$name]) . 's';
                $this->services[$name] = new $apiPath ($this);
            }

            return $this->services[$name];
        }

        throw new AzureException(sprintf('Invalid Service name "%s" for Compute', $name));
    }

}