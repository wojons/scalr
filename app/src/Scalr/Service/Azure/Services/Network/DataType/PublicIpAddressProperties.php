<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * PublicIpAddressProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\DnsSettings  $settings
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\InterfaceIpConfigurationsData  $ipConfiguration
 *
 */
class PublicIpAddressProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['settings', 'ipConfiguration'];

    /**
     * Provisioning state of the Public IP Address
     *
     * @var string
     */
    public $provisioningState;

    /**
     * Public ip address
     *
     * @var string
     */
    public $ipAddress;

    /**
     * Defines whether the IP address is stable or dynamic. Options are Static or Dynamic
     *
     * @var string
     */
    public $publicIPAllocationMethod;

    /**
     * Specifies the timeout for the TCP idle connection. The value can be set between 4 and 30 minutes
     *
     * @var int
     */
    public $idleTimeoutInMinutes;

    /**
     * Constructor
     *
     * @param   string     $publicIPAllocationMethod     Defines whether the IP address is stable or dynamic. Options are Static or Dynamic
     */
    public function __construct($publicIPAllocationMethod)
    {
        $this->publicIPAllocationMethod = $publicIPAllocationMethod;
    }

    /**
     * Sets settings
     *
     * @param   array|DnsSettings $settings
     * @return  PublicIpAddressProperties
     */
    public function setSettings($settings = null)
    {
        if (!($settings instanceof DnsSettings)) {
            $settings = DnsSettings::initArray($settings);
        }

        return $this->__call(__FUNCTION__, [$settings]);
    }

    /**
     * Sets ip configuration
     *
     * @param   array|InterfaceIpConfigurationsData $ipConfiguration
     * @return  PublicIpAddressProperties
     */
    public function setIpConfiguration($ipConfiguration = null)
    {
        if (!($ipConfiguration instanceof InterfaceIpConfigurationsData)) {
            $ipConfiguration = InterfaceIpConfigurationsData::initArray($ipConfiguration);
        }

        return $this->__call(__FUNCTION__, [$ipConfiguration]);
    }

}