<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * OsDisk
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class OsDisk extends AbstractDataType
{
    /**
     * Specifies the Operating System type.
     *
     * @var string
     */
    public $osType;

    /**
     * Specifies the disk name.
     *
     * @var string
     */
    public $name;

    /**
     * Specifies the vhd uri.
     * Example Format: ["uri" => "http://storageAccount.blob.core.windows.net/vhds/osDisk.vhd"]
     *
     * @var array
     */
    public $vhd;

    /**
     * Specifies the image uri.
     * Example Format: ["uri" => "http://kayrg24960.blob.core.windows.net/system/Microsoft.Compute/Images/capturedvm/captured-osDisk.5694e9af-f84d-4dc5-ab72-bc0bc5d67497.vhd"]
     *
     * @var array
     */
    public $image;

    /**
     * Specifies the caching requirements.
     *
     * @var string
     */
    public $caching;

    /**
     * Specifies how the virtual machine was created.
     *
     * @var string
     */
    public $createOption;

    /**
     * Constructor
     *
     * @param   string     $name            Specifies the disk name.
     * @param   array      $vhd             Specifies the vhd uri.
     * @param   string     $createOption    Specifies how the virtual machine was created.
     * @param   string     $osType          optional Specifies the Operating System type. Required for all vm except Linux
     */
    public function __construct($name, $vhd, $createOption, $osType = null)
    {
        $this->name         = $name;
        $this->vhd          = $vhd;
        $this->createOption = $createOption;
        $this->osType       = $osType;
    }

    /**
     * Sets image uri
     *
     * @param string $uri Specifies the image uri.
     * @return OsDisk
     */
    public function setImageUri($uri)
    {
        $this->image = ['uri' => $uri];

        return $this;
    }

}