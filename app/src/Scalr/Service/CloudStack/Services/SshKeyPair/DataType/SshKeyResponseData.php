<?php
namespace Scalr\Service\CloudStack\Services\SshKeyPair\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * SshKeyResponseData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class SshKeyResponseData extends AbstractDataType
{

    /**
     * Fingerprint of the public key
     *
     * @var string
     */
    public $fingerprint;

    /**
     * Name of the keypair
     *
     * @var string
     */
    public $name;

}