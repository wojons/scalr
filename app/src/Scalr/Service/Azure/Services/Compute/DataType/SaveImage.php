<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * SaveImage
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 */
class SaveImage extends AbstractDataType
{
    /**
     * Specifies the prefix in the name of the blobs that will constitute the storage profile of the image.
     *
     * @var string
     */
    public $vhdPrefix;

    /**
     * Specifies the name of the container inside which the vhds constituting the image will reside.
     *
     * @var string
     */
    public $destinationContainerName;

    /**
     * Specifies if an existing vhd with same prefix inside the destination container is overwritten.
     *
     * @var bool
     */
    public $overwriteVhds;

    /**
     * Constructor
     *
     * @param   string  $vhdPrefix                      Specifies the prefix in the name of the blobs that will constitute the storage profile of the image.
     * @param   string  $destinationContainerName       Specifies the name of the container inside which the vhds constituting the image will reside.
     * @param   bool  $overwriteVhds                    Specifies if an existing vhd with same prefix inside the destination container is overwritten.
     */
    public function __construct($vhdPrefix, $destinationContainerName, $overwriteVhds)
    {
        $this->vhdPrefix = $vhdPrefix;
        $this->destinationContainerName = $destinationContainerName;
        $this->overwriteVhds = $overwriteVhds;
    }

}