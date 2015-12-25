<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * LinuxConfiguration
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\SshData   $ssh
 *            Specifies the SSH public keys to use with the Virtual Machine.
 *
 */
class LinuxConfiguration extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['ssh'];

    /**
     * Specifies if password authentication is disabled.
     *
     * @var bool
     */
    public $disablePasswordAuthentication;

    /**
     * Sets SshData
     *
     * @param   array|SshData  $ssh  Specifies the SSH public keys to use with the Virtual Machine.
     * @return  LinuxConfiguration
     */
    public function setSsh($ssh = null)
    {
        if (!($ssh instanceof SshData)) {
            $ssh = SshData::initArray($ssh);
        }

        return $this->__call(__FUNCTION__, [$ssh]);
    }

}