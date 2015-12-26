<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;
use Scalr\Service\Azure\Services\Compute\DataType\VirtualMachineData;

/**
 * InterfaceProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\InterfaceIpConfigurationsList  $ipConfigurations
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\VirtualMachineData  $virtualMachine
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SecurityGroupData  $networkSecurityGroup
 *
 */
class InterfaceProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['ipConfigurations', 'virtualMachine', 'networkSecurityGroup'];

    /**
     * Provisioning state of the Network Interface Card
     *
     * @var string
     */
    public $provisioningState;

    /**
     * The media access control (MAC) address of the network interface
     *
     * @var string
     */
    public $macAddress;

    /**
     * List of DNS servers IP addresses to use for this NIC
     * Example Format: ['dnsServers' => ["10.0.0.4", "10.0.0.5"]]
     *
     * @var array
     */
    public $dnsSettings;

    /**
     * Constructor
     *
     * @param   array|InterfaceIpConfigurationsList $ipConfigurations    Specifies ip configurations
     */
    public function __construct($ipConfigurations)
    {
        $this->setIpConfigurations($ipConfigurations);
    }

    /**
     * Sets ipConfigurations
     *
     * @param   array|InterfaceIpConfigurationsList $ipConfigurations
     * @return  InterfaceProperties
     */
    public function setIpConfigurations($ipConfigurations = null)
    {
        if (!($ipConfigurations instanceof InterfaceIpConfigurationsList)) {
            $ipConfigurationList = new InterfaceIpConfigurationsList();

            foreach ($ipConfigurations as $ipConfiguration) {
                if (!($ipConfiguration instanceof InterfaceIpConfigurationsData)) {
                    $ipConfigurationData = InterfaceIpConfigurationsData::initArray($ipConfiguration);
                } else {
                    $ipConfigurationData = $ipConfiguration;
                }

                $ipConfigurationList->append($ipConfigurationData);
            }
        } else {
            $ipConfigurationList = $ipConfigurations;
        }

        return $this->__call(__FUNCTION__, [$ipConfigurationList]);
    }

    /**
     * Sets virtual machine
     *
     * @param   array|VirtualMachineData $virtualMachine
     * @return  InterfaceProperties
     */
    public function setProperties($virtualMachine = null)
    {
        if (!($virtualMachine instanceof VirtualMachineData)) {
            $virtualMachine = VirtualMachineData::initArray($virtualMachine);
        }

        return $this->__call(__FUNCTION__, [$virtualMachine]);
    }

    /**
     * Sets network Security Group
     *
     * @param   array|SecurityGroupData $networkSecurityGroup  Only id property is required
     * @return  InterfaceProperties
     */
    public function setNetworkSecurityGroup($networkSecurityGroup = null)
    {
        if (!($networkSecurityGroup instanceof SecurityGroupData)) {
            $networkSecurityGroup = SecurityGroupData::initArray($networkSecurityGroup);
        }

        return $this->__call(__FUNCTION__, [$networkSecurityGroup]);
    }

}