<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * SubnetProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.6.8
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SecurityGroupData  $networkSecurityGroup
 *
 */
class SubnetProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['networkSecurityGroup'];

    /**
     * Provisioning State of the subnet
     *
     * @var string
     */
    public $provisioningState;

    /**
     * Address prefix for the subnet
     *
     * @var string
     */
    public $addressPrefix;

    /**
     * Collection of IP Configurations with IPs within this subnet.
     * Example Format: [
     *      ["id" => "/subscriptions/{guid}/../Microsoft.Network/networkInterfaces/vm1nic1/ipConfigurations/ip1"],
     *      ["id" => "/subscriptions/{guid}/../microsoft.network/loadBalancers/lb1/frontendIpConfigurations/ip1"],
     *      ["id" => "/subscriptions/{guid}/../microsoft.network/vpnGateways/gw1/ipConfigurations/ip1"]
     * ]
     *
     * @var array
     */
    public $ipConfigurations;

    /**
     * Sets network Security Group
     *
     * @param   array|SecurityGroupData $networkSecurityGroup
     * @return  SubnetProperties
     */
    public function setNetworkSecurityGroup($networkSecurityGroup = null)
    {
        if (!($networkSecurityGroup instanceof SecurityGroupData)) {
            $networkSecurityGroup = SecurityGroupData::initArray($networkSecurityGroup);
        }

        return $this->__call(__FUNCTION__, [$networkSecurityGroup]);
    }

}