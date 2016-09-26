<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * VirtualMachineProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\HardwareProfile  $hardwareProfile
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\StorageProfile  $storageProfile
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\OsProfile       $osProfile
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\VirtualInstanceViewData  $instanceView
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\AvailabilitySetData       $availabilitySet
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\NetworkProfile       $networkProfile
 *
 */
class VirtualMachineProperties extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['hardwareProfile', 'storageProfile', 'osProfile', 'instanceView', 'availabilitySet', 'networkProfile'];

    /**
     * Provisioning State
     *
     * @var string
     */
    public $provisioningState;

    /**
     * Constructor
     *
     * @param   array|HardwareProfile   $hardwareProfile     Specifies the size of the virtual machine.
     * @param   array|NetworkProfile    $networkProfile      Specifies the network interfaces of the virtual machine.
     * @param   array|StorageProfile    $storageProfile      Specifies the storage profile.
     * @param   array|OsProfile         $osProfile           Specifies the os profile.
     */
    public function __construct($hardwareProfile, $networkProfile, $storageProfile, $osProfile)
    {
        $this->setHardwareProfile($hardwareProfile);
        $this->setNetworkProfile($networkProfile);
        $this->setOsProfile($osProfile);
        $this->setStorageProfile($storageProfile);
    }

    /**
     * Sets hardware profile data
     *
     * @param array|HardwareProfile $hardwareProfile
     * @return VirtualMachineProperties
     */
    public function setHardwareProfile($hardwareProfile = null)
    {
        if (!($hardwareProfile instanceof HardwareProfile)) {
            $hardwareProfile = HardwareProfile::initArray($hardwareProfile);
        }

        return $this->__call(__FUNCTION__, [$hardwareProfile]);
    }

    /**
     * Sets network profile data
     *
     * @param array|NetworkProfile $networkProfile
     * @return VirtualMachineProperties
     */
    public function setNetworkProfile($networkProfile = null)
    {
        if (!($networkProfile instanceof NetworkProfile)) {
            $networkProfile = NetworkProfile::initArray($networkProfile);
        }

        return $this->__call(__FUNCTION__, [$networkProfile]);
    }

    /**
     * Sets StorageProfile
     *
     * @param   array|StorageProfile $storageProfile
     * @return  VirtualMachineProperties
     */
    public function setStorageProfile($storageProfile = null)
    {
        if (!($storageProfile instanceof StorageProfile)) {
            $storageProfile = StorageProfile::initArray($storageProfile);
        }

        return $this->__call(__FUNCTION__, [$storageProfile]);
    }

    /**
     * Sets OsProfile
     *
     * @param   array|OsProfile    $osProfile
     * @return  VirtualMachineProperties
     */
    public function setOsProfile($osProfile = null)
    {
        if (!($osProfile instanceof OsProfile)) {
            $osProfile = OsProfile::initArray($osProfile);
        }

        return $this->__call(__FUNCTION__, [$osProfile]);
    }

    /**
     * Sets instance view data
     *
     * @param array|VirtualInstanceViewData $instanceView
     * @return VirtualMachineProperties
     */
    public function setInstanceView($instanceView = null)
    {
        if (!($instanceView instanceof VirtualInstanceViewData)) {
            $instanceView = VirtualInstanceViewData::initArray($instanceView);
        }

        return $this->__call(__FUNCTION__, [$instanceView]);
    }

    /**
     * Sets availability set data
     *
     * @param array|AvailabilitySetData $availabilitySet
     * @return VirtualMachineProperties
     */
    public function setAvailabilitySet($availabilitySet = null)
    {
        if (!($availabilitySet instanceof AvailabilitySetData)) {
            $availabilitySet = AvailabilitySetData::initArray($availabilitySet);
        }

        return $this->__call(__FUNCTION__, [$availabilitySet]);
    }

}