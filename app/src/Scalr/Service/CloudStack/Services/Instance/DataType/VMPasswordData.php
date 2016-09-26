<?php
namespace Scalr\Service\CloudStack\Services\Instance\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * VMPasswordData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class VMPasswordData extends AbstractDataType
{

    /**
     * The encrypted password of the VM
     *
     * @var string
     */
    public $encryptedpassword;

}