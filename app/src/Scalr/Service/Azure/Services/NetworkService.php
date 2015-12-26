<?php

namespace Scalr\Service\Azure\Services;

use Scalr\Service\Azure;
use Scalr\Service\Azure\AbstractService;
use Scalr\Service\AzureException;

/**
 * Azure network service interface class
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 *
 * @property \Scalr\Service\Azure\Services\Network\Api\Interfaces $interface
 *
 * @property \Scalr\Service\Azure\Services\Network\Api\Subnets $subnet
 *
 * @property \Scalr\Service\Azure\Services\Network\Api\PublicIPAddresses $publicIPAddress
 *
 * @property \Scalr\Service\Azure\Services\Network\Api\VirtualNetworks $virtualNetwork
 *
 * @property \Scalr\Service\Azure\Services\Network\Api\SecurityGroups $securityGroup
 *
 * @property \Scalr\Service\Azure\Services\Network\Api\SecurityRules $securityRule
 *
 */
class NetworkService extends AbstractService
{
    /**
     * Part of a endpoint url for Network Service
     */
    const ENDPOINT_MICROSOFT_NETWORK = '/providers/Microsoft.Network';

    /**
     * Service name for Network interface.
     */
    const SERVICE_INTERFACE = 'interface';

    /**
     * Service name for subnet.
     */
    const SERVICE_SUBNET = 'subnet';

    /**
     * Service name for publicIPAddress.
     */
    const SERVICE_PUBLIC_IP_ADDRESS = 'publicIPAddress';

    /**
     * Service name for virtualNetwork.
     */
    const SERVICE_VIRTUAL_NETWORK = 'virtualNetwork';

    /**
     * Service name for securityGroup.
     */
    const SERVICE_SECURITY_GROUP = 'securityGroup';

    /**
     * Service name for securityRule.
     */
    const SERVICE_SECURITY_RULE = 'securityRule';

    /**
     * Api version of the resource
     */
    const NETWORK_API_VERSION = '2015-05-01-preview';

    /**
     * List of instances of Network services.
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
        return self::NETWORK_API_VERSION;
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
            self::SERVICE_INTERFACE         => self::SERVICE_INTERFACE,
            self::SERVICE_PUBLIC_IP_ADDRESS => self::SERVICE_PUBLIC_IP_ADDRESS,
            self::SERVICE_SUBNET            => self::SERVICE_SUBNET,
            self::SERVICE_VIRTUAL_NETWORK   => self::SERVICE_VIRTUAL_NETWORK,
            self::SERVICE_SECURITY_GROUP    => self::SERVICE_SECURITY_GROUP,
            self::SERVICE_SECURITY_RULE     => self::SERVICE_SECURITY_RULE
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
                $apiPath = __NAMESPACE__ . '\\Network\\Api\\' . ucfirst($services[$name]);

                if ($name !== self::SERVICE_PUBLIC_IP_ADDRESS) {
                    $apiPath .= 's';
                } else {
                    $apiPath .= 'es';
                }

                $this->services[$name] = new $apiPath ($this);
            }

            return $this->services[$name];
        }

        throw new AzureException(sprintf('Invalid Service name "%s" for ResourceManager', $name));
    }

}