<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * DataDiskData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class DataDiskData extends AbstractDataType
{
    /**
     * Specifies the name of data disk
     *
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $diskSizeGB;

    /**
     * @var int
     */
    public $lun;

    /**
     * Example Format: ["uri" => "https://myStorage.blob.core.windows.net/vhds/{vMname}/dataDisk1.vhd"]
     * @var array
     */
    public $vhd;

    /**
     * @var string
     */
    public $createOption;

}