<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ListServiceOfferingsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListServiceOfferingsData extends AbstractDataType
{

    /**
     * The ID of the domain associated with the service offering
     *
     * @var string
     */
    public $domainid;

    /**
     * ID of the service offering
     *
     * @var string
     */
    public $id;

    /**
     * Is this a system vm offering
     *
     * @var string
     */
    public $issystem;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * Name of the service offering
     *
     * @var string
     */
    public $name;

    /**
     * The system VM type.
     * Possible types are "consoleproxy", "secondarystoragevm" or "domainrouter".
     *
     * @var string
     */
    public $systemvmtype;

    /**
     * The ID of the virtual machine.
     * Pass this in if you want to see the available service offering that a virtual machine can be changed to.
     *
     * @var string
     */
    public $virtualmachineid;

}