<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * VirtualInstanceViewData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\VmAgentData  $vmAgent
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\DiskList  $disks
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\StatusList  $statuses
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\ExtensionList  $extensions
 *
 */
class VirtualInstanceViewData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['vmAgent', 'disks', 'statuses', 'extensions'];

    /**
     * Specifies the update domain of the virtual machine.
     *
     * @var int
     */
    public $platformUpdateDomain;

    /**
     * Specifies the fault domain of the virtual machine.
     *
     * @var int
     */
    public $platformFaultDomain;

    /**
     * Sets vm Agent data
     *
     * @param   array|VmAgentData $vmAgent
     * @return  VirtualInstanceViewData
     */
    public function setVmAgent($vmAgent = null)
    {
        if (!($vmAgent instanceof VmAgentData)) {
            $vmAgent = VmAgentData::initArray($vmAgent);
        }

        return $this->__call(__FUNCTION__, [$vmAgent]);
    }

    /**
     * Sets disks
     *
     * @param   array|DiskList $disks
     * @return  VirtualInstanceViewData
     */
    public function setDisks($disks = null)
    {
        if (!($disks instanceof DiskList)) {
            $diskList = new DiskList();

            foreach ($disks as $disk) {
                if (!($disk instanceof DiskData)) {
                    $diskData = DiskData::initArray($disk);
                } else {
                    $diskData = $disk;
                }

                $diskList->append($diskData);
            }
        } else {
            $diskList = $disks;
        }

        return $this->__call(__FUNCTION__, [$diskList]);
    }

    /**
     * Sets statuses
     *
     * @param   array|StatusList $statuses
     * @return  VirtualInstanceViewData
     */
    public function setStatuses($statuses = null)
    {
        if (!($statuses instanceof StatusList)) {
            $statusList = new StatusList();

            foreach ($statuses as $status) {
                if (!($status instanceof StatusData)) {
                    $statusData = StatusData::initArray($status);
                } else {
                    $statusData = $status;
                }

                $statusList->append($statusData);
            }
        } else {
            $statusList = $statuses;
        }

        return $this->__call(__FUNCTION__, [$statusList]);
    }

    /**
     * Sets extensions
     *
     * @param   array|ExtensionList $extensions
     * @return  VirtualInstanceViewData
     */
    public function setExtensions($extensions = null)
    {
        if (!($extensions instanceof ExtensionList)) {
            $extensionsList = new ExtensionList();

            foreach ($extensions as $extension) {
                if (!($extension instanceof ExtensionData)) {
                    $extensionData = ExtensionData::initArray($extension);
                } else {
                    $extensionData = $extension;
                }

                $extensionsList->append($extensionData);
            }
        } else {
            $extensionsList = $extensions;
        }

        return $this->__call(__FUNCTION__, [$extensionsList]);
    }

}