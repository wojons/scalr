<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * WindowsConfiguration
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\WinRM   $winRM
 *            Contains configuration settings for the Windows Remote Management service on the Virtual Machine. This enables remote Windows PowerShell.
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\AdditionalUnattendContent   $additionalUnattendContent
 *            Specifies additional base-64 encoded XML formatted information that can be included in the Unattend.xml file, which is used by Windows Setup.
 */
class WindowsConfiguration extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['winRM', 'additionalUnattendContent'];

    /**
     * Indicates whether virtual machine agent should be provisioned on the virtual machine.
     *
     * @var bool
     */
    public $provisionVMAgent;

    /**
     * Indicates whether virtual machine is enabled for automatic updates.
     *
     * @var bool
     */
    public $enableAutomaticUpdates;

    /**
     * Sets WinRM
     *
     * @param   array|WinRM $winRM Contains configuration settings for the Windows Remote Management service on the Virtual Machine.
     *                             This enables remote Windows PowerShell.
     * @return  WindowsConfiguration
     */
    public function setWinRM($winRM = null)
    {
        if (!($winRM instanceof WinRM)) {
            $winRM = WinRM::initArray($winRM);
        }

        return $this->__call(__FUNCTION__, [$winRM]);
    }

    /**
     * Sets AdditionalUnattendContent
     *
     * @param   array|AdditionalUnattendContent $additionalUnattendContent Specifies additional base-64 encoded XML formatted information that can be included in the Unattend.xml file,
     *                                                                     which is used by Windows Setup.
     * @return  WindowsConfiguration
     */
    public function setAdditionalUnattendContent($additionalUnattendContent = null)
    {
        if (!($additionalUnattendContent instanceof AdditionalUnattendContent)) {
            $additionalUnattendContent = AdditionalUnattendContent::initArray($additionalUnattendContent);
        }

        return $this->__call(__FUNCTION__, [$additionalUnattendContent]);
    }

}