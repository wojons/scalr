<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * OsProfile
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\WindowsConfiguration   $windowsConfiguration
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\LinuxConfiguration     $linuxConfiguration
 *
 */
class OsProfile extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['windowsConfiguration', 'linuxConfiguration'];

    /**
     * Specifies the computer name.
     *
     * @var string
     */
    public $computerName;

    /**
     * Specifies the admin username.
     *
     * @var string
     */
    public $adminUsername;

    /**
     * Specifies the admin password.
     *
     * @var string
     */
    public $adminPassword;

    /**
     * Specifies a base-64 encoded string of custom data.
     * The base-64 encoded string is decoded to a binary array that is saved as a file on the Virtual Machine.
     * The maximum length of the binary array is 65535 bytes.
     *
     * @var string
     */
    public $customData;

    /**
     * Specifies set of certificates that should be installed onto the virtual machine.
     *
     * @var array
     */
    public $secrets;

    /**
     * Constructor
     *
     * @param   string     $adminUsername    Specifies the admin username.
     * @param   string     $adminPassword    Specifies the admin password.
     */
    public function __construct($adminUsername, $adminPassword)
    {
        $this->adminUsername  = $adminUsername;
        $this->adminPassword  = $adminPassword;
    }

    /**
     * Sets WindowsConfiguration
     *
     * @param   array|WindowsConfiguration $windowsConfiguration
     * @return  OsProfile
     */
    public function setWindowsConfiguration($windowsConfiguration = null)
    {
        if (!($windowsConfiguration instanceof WindowsConfiguration)) {
            $windowsConfiguration = WindowsConfiguration::initArray($windowsConfiguration);
        }

        return $this->__call(__FUNCTION__, [$windowsConfiguration]);
    }

    /**
     * Sets LinuxConfiguration
     *
     * @param   array|LinuxConfiguration $linuxConfiguration
     * @return  OsProfile
     */
    public function setLinuxConfiguration($linuxConfiguration = null)
    {
        if (!($linuxConfiguration instanceof LinuxConfiguration)) {
            $linuxConfiguration = LinuxConfiguration::initArray($linuxConfiguration);
        }

        return $this->__call(__FUNCTION__, [$linuxConfiguration]);
    }

}