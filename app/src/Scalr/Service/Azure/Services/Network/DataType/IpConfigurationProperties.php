<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * IpConfigurationProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\PublicIpAddressData  $publicIPAddress
 *            Reference to a public IP Address to associate with this NIC
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SubnetData  $subnet
 *            Reference to a subnet in which this NIC will be created
 *
 */
class IpConfigurationProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['publicIPAddress', 'subnet'];

    /**
     * Provisioning state of the IP Configuration
     *
     * @var string
     */
    public $provisioningState;

    /**
     * Static IP Address
     *
     * @var string
     */
    public $privateIPAddress;

    /**
     * Defines how a private IP address is assigned. Options are Static or Dynamic.
     *
     * @var string
     */
    public $privateIPAllocationMethod;

    /**
     * Reference to a Load Balancer Backend Address Pool containing this NIC
     * Example Format: [
     *      ["id" => "/subscriptions/{guid}/../Microsoft.Network/loadBalancers/mylb1/backendAddressPools/pool1"]
     * ]
     *
     * @var array
     */
    public $loadBalancerBackendAddressPools;

    /**
     * Reference to a Load Balancer Inbound Nat Rule containing this NIC
     * Example Format: [
     *      ["id" => "/subscriptions/{guid}/../Microsoft.Network/loadBalancers/mylb1/inboundNatRules/rdp"]
     * ]
     *
     * @var array
     */
    public $loadBalancerInboundNatRules;

    /**
     * Constructor
     *
     * @param   array|SubnetData    $subnet                      Reference to a subnet in which this NIC will be created
     * @param   string              $privateIPAllocationMethod   Defines how a private IP address is assigned. Options are Static or Dynamic.
     */
    public function __construct($subnet, $privateIPAllocationMethod)
    {
        $this->setSubnet($subnet);
        $this->privateIPAllocationMethod = $privateIPAllocationMethod;
    }

    /**
     * Sets properties
     *
     * @param   array|PublicIpAddressData $publicIPAddress
     * @return  IpConfigurationProperties
     */
    public function setPublicIPAddress($publicIPAddress = null)
    {
        if (!($publicIPAddress instanceof PublicIpAddressData)) {
            $publicIPAddress = PublicIpAddressData::initArray($publicIPAddress);
        }

        return $this->__call(__FUNCTION__, [$publicIPAddress]);
    }

    /**
     * Sets subnet
     *
     * @param   array|SubnetData $subnet
     * @return  IpConfigurationProperties
     */
    public function setSubnet($subnet = null)
    {
        if (!($subnet instanceof SubnetData)) {
            $subnet = SubnetData::initArray($subnet);
        }

        return $this->__call(__FUNCTION__, [$subnet]);
    }

}