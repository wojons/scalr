<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * HardwareProfile
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class HardwareProfile extends AbstractDataType
{
    /**
     * Specifies the size of the virtual machine.
     *
     * @var string
     */
    public $vmSize;

    /**
     * Constructor
     *
     * @param   string  $vmSize   Specifies the size of the virtual machine.
     */
    public function __construct($vmSize)
    {
        $this->vmSize = $vmSize;
    }

}