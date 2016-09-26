<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * ImageReference
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class ImageReference extends AbstractDataType
{
    /**
     * Specified publisher of the image used to create the virtual machine.
     *
     * @var string
     */
    public $publisher;

    /**
     * @var string
     */
    public $offer;

    /**
     * Specified SKU of the image used to create the virtual machine.
     *
     * @var string
     */
    public $sku;

    /**
     * Specified version of the image used to create the virtual machine.
     *
     * @var string
     */
    public $version;

    /**
     * Constructor
     *
     * @param   string     $publisher     Specified publisher of the image used to create the virtual machine.
     * @param   string     $offer         Specified offer of the image used to create the virtual machine
     * @param   string     $sku           Specified SKU of the image used to create the virtual machine.
     * @param   string     $version       Specified version of the image used to create the virtual machine.
     */
    public function __construct($publisher, $offer, $sku, $version)
    {
        $this->publisher = $publisher;
        $this->offer = $offer;
        $this->sku = $sku;
        $this->version = $version;
    }

}