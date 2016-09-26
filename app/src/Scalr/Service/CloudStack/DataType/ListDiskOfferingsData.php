<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ListDiskOfferingsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListDiskOfferingsData extends AbstractDataType
{

    /**
     * The ID of the domain associated with the disk offering
     *
     * @var string
     */
    public $domainid;

    /**
     * ID of the disk offering
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
     * Name of the disk offering
     *
     * @var string
     */
    public $name;

}