<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * PublicKeyData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class PublicKeyData extends AbstractDataType
{
    /**
     * Specifies the full path of a file, on the Virtual Machine, where the SSH public key is stored. If the file already exists, the specified key is appended to the file. Example: /home/user/.ssh/authorized_keys.
     *
     * @var string
     */
    public $path;

    /**
     * Specifies a base-64 encoding of the public key used to SSH into the Virtual Machine.
     *
     * @var string
     */
    public $keyData;

    /**
     * Constructor
     *
     * @param   string     $path       Specifies the full path of a file, on the Virtual Machine, where the SSH public key is stored. If the file already exists, the specified key is appended to the file. Example: /home/user/.ssh/authorized_keys.
     * @param   string     $keyData    Specifies a base-64 encoding of the public key used to SSH into the Virtual Machine.
     */
    public function __construct($path, $keyData)
    {
        $this->name      = $path;
        $this->publisher = $keyData;
    }

}