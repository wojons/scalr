<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;
use DateTime;

/**
 * NetworkOfferingsResponseData
 *
 * property  \Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseServiceList   $service
 * The list of services
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class NetworkOfferingsResponseData extends AbstractDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('service');

    /**
     * The id of the network offering
     *
     * @var string
     */
    public $id;

    /**
     * The availability of network offering.
     *
     * @var string
     */
    public $availability;

    /**
     * True if network offering is ip conserve mode enabled
     *
     * @var string
     */
    public $conservemode;

    /**
     * The date this network offering was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * Additional key/value details tied with network offering
     *
     * @var string
     */
    public $details;

    /**
     * An alternate display text of the network offering.
     *
     * @var string
     */
    public $displaytext;

    /**
     * True if network offering supports persistent networks, false otherwise
     *
     * @var string
     */
    public $egressdefaultpolicy;

    /**
     * True if network offering can be used by VPC networks only
     *
     * @var string
     */
    public $forvpc;

    /**
     * Guest type of the network offering, can be Shared or Isolated
     *
     * @var string
     */
    public $guestiptype;

    /**
     * True if network offering is default, false otherwise
     *
     * @var string
     */
    public $isdefault;

    /**
     * True if network offering supports persistent networks, false otherwise
     *
     * @var string
     */
    public $ispersistent;

    /**
     * Maximum number of concurrents connections to be handled by lb
     *
     * @var string
     */
    public $maxconnections;

    /**
     * The name of the network offering
     *
     * @var string
     */
    public $name;

    /**
     * Data transfer rate in megabits per second allowed.
     *
     * @var string
     */
    public $networkrate;

    /**
     * The ID of the service offering used by virtual router provider
     *
     * @var string
     */
    public $serviceofferingid;

    /**
     * True if network offering supports specifying ip ranges, false otherwise
     *
     * @var string
     */
    public $specifyipranges;

    /**
     * True if network offering supports vlans, false otherwise
     *
     * @var string
     */
    public $specifyvlan;

    /**
     * State of the network offering. Can be Disabled/Enabled/Inactive
     *
     * @var string
     */
    public $state;

    /**
     * The tags for the network offering
     *
     * @var string
     */
    public $tags;

    /**
     * The traffic type for the network offering,
     * supported types are Public, Management, Control, Guest, Vlan or Storage.
     *
     * @var string
     */
    public $traffictype;

    /**
     * Sets service list
     *
     * @param   NetworkResponseService $service
     * @return  NetworkOfferingsResponseData
     */
    public function setService(NetworkResponseServiceList $service = null)
    {
        return $this->__call(__FUNCTION__, array($service));
    }

}