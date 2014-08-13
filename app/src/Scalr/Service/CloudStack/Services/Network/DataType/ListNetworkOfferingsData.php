<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListNetworkOfferingsData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListNetworkOfferingsData extends AbstractDataType
{

    /**
     * The availability of network offering. Default value is Required
     *
     * @var string
     */
    public $availability;

    /**
     * List network offerings by display text
     *
     * @var string
     */
    public $displaytext;

    /**
     * The network offering can be used only for network creation inside the VPC
     *
     * @var string
     */
    public $forvpc;

    /**
     * List network offerings by guest type: Shared or Isolated
     *
     * @var string
     */
    public $guestiptype;

    /**
     * List network offerings by id
     *
     * @var string
     */
    public $id;

    /**
     * True if need to list only default network offerings. Default value is false
     *
     * @var string
     */
    public $isdefault;

    /**
     * True if offering has tags specified
     *
     * @var string
     */
    public $istagged;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * List network offerings by name
     *
     * @var string
     */
    public $name;

    /**
     * The ID of the network.
     * Pass this in if you want to see the available network offering that a network can be changed to.
     *
     * @var string
     */
    public $networkid;

    /**
     * True if need to list only netwok offerings where source nat is supported, false otherwise
     *
     * @var string
     */
    public $sourcenatsupported;

    /**
     * True if need to list only network offerings which support specifying ip ranges
     *
     * @var string
     */
    public $specifyipranges;

    /**
     * The tags for the network offering.
     *
     * @var string
     */
    public $specifyvlan;

    /**
     * List network offerings by state
     *
     * @var string
     */
    public $state;

    /**
     * List network offerings supporting certain services
     *
     * @var string
     */
    public $supportedservices;

    /**
     * List network offerings by tags
     *
     * @var string
     */
    public $tags;

    /**
     * List by traffic type
     *
     * @var string
     */
    public $traffictype;

    /**
     * List netowrk offerings available for network creation in specific zone
     *
     * @var string
     */
    public $zoneid;

}