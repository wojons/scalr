<?php
namespace Scalr\Service\CloudStack\Services\Zone\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListZonesData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListZonesData extends AbstractDataType
{

    /**
     * True if you want to retrieve all available Zones.
     * False if you only want to return the Zones from which you have at least one VM.
     * Default is false.
     *
     * @var string
     */
    public $available;

    /**
     * The ID of the domain associated with the zone
     *
     * @var string
     */
    public $domainid;

    /**
     * The ID of the zone
     *
     * @var string
     */
    public $id;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * The name of the zone
     *
     * @var string
     */
    public $name;

    /**
     * The network type of the zone that the virtual machine belongs to
     *
     * @var string
     */
    public $networktype;

    /**
     * Flag to display the capacity of the zones
     *
     * @var string
     */
    public $showcapacities;

    /**
     * List zones by resource tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

}