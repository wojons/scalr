<?php
namespace Scalr\Service\CloudStack\Services\SshKeyPair\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * SshPrivateKeyResponseData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class SshPrivateKeyResponseData extends AbstractDataType
{
    /**
     * Key name
     *
     * @var string
     */
    public $name;

    /**
     * Fingerprint
     *
     * @var string
     */
    public $fingerprint;

    /**
     * Private key
     *
     * @var string
     */
    public $privatekey;

}