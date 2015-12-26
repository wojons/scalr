<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * VirtualNetworkProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SubnetList  $subnets
 *
 */
class VirtualNetworkProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['subnets'];

    /**
     * Provisioning state of the Virtual Network
     *
     * @var string
     */
    public $provisioningState;

    /**
     * AddressSpace contains an array of IP address ranges that can be used by subnets of the virtual network.
     * Example Format: ["addressPrefixes" => ["10.1.0.0/16", "10.2.0.0/16"]]
     *
     * @var array
     */
    public $addressSpace;

    /**
     * DhcpOptions contains an array of DNS servers available to VMs deployed in the virtual network.
     * Example Format: ['dnsServers' => ["10.0.0.4", "10.0.0.5"]]
     *
     * @var array
     */
    public $dhcpOptions;

    /**
     * Constructor
     *
     * @param   array|SubnetList $subnets Specifies properties
     */
    public function __construct($subnets)
    {
        $this->setSubnets($subnets);
    }

    /**
     * Sets subnets
     *
     * @param   array|SubnetList $subnets
     * @return  VirtualNetworkProperties
     */
    public function setSubnets($subnets = null)
    {
        if (!($subnets instanceof SubnetList)) {
            $subnetList = new SubnetList();

            foreach ((array) $subnets as $subnet) {
                if (!($subnet instanceof SubnetData)) {
                    $subnetData = SubnetData::initArray($subnet);
                } else {
                    $subnetData = $subnet;
                }

                $subnetList->append($subnetData);
            }
        } else {
            $subnetList = $subnets;
        }

        return $this->__call(__FUNCTION__, [$subnetList]);
    }

}