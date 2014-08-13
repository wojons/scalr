<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * NetworkResponseCapabilityData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class NetworkResponseCapabilityData extends AbstractDataType
{

    /**
     * Can this service capability value can be choosable while creatine network offerings
     *
     * @var string
     */
    public $canchooseservicecapability;

    /**
     * The capability value
     *
     * @var string
     */
    public $value;

    /**
     * The capability name
     *
     * @var string
     */
    public $name;


}