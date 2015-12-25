<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * InstanceTypeData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class InstanceTypeData extends AbstractDataType
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var int
     */
    public $numberOfCores;

    /**
     * @var int
     */
    public $osDiskSizeInMB;

    /**
     * @var int
     */
    public $resourceDiskSizeInMB;

    /**
     * @var int
     */
    public $memoryInMB;

    /**
     * @var int
     */
    public $maxDataDiskCount;

}