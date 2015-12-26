<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * WinListenerData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.6.8
 *
 */
class WinListenerData extends AbstractDataType
{
    /**
     * Specifies the protocol of listener.
     *
     * @var string
     */
    public $protocol;

    /**
     * Specifies URL of the certificate with which new Virtual Machines is provisioned.
     *
     * @var string
     */
    public $certificateUrl;

    /**
     * Constructor
     *
     * @param   string    $protocol      Specifies the protocol of listener.
     */
    public function __construct($protocol)
    {
        $this->protocol = $protocol;
    }

}