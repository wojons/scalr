<?php
namespace Scalr\Service\CloudStack\Services\Zone\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;
use Scalr\Service\CloudStack\DataType\ResponseTagsList;

/**
 * ZoneData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with network
 *
 * @property  \Scalr\Service\CloudStack\Services\Zone\DataType\CapacityList      $capacity
 * The capacity of the Zone
 *
 * @property  object      $resourcedetails
 * Meta data associated with the zone (key/value pairs)
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ZoneData extends AbstractDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('capacity', 'tags', 'resourcedetails');

    /**
     * Zone id
     *
     * @var string
     */
    public $id;

    /**
     * The allocation state of the cluster
     *
     * @var string
     */
    public $allocationstate;

    /**
     * Zone description
     *
     * @var string
     */
    public $description;

    /**
     * The dhcp Provider for the Zone
     *
     * @var string
     */
    public $dhcpprovider;

    /**
     * The display text of the zone
     *
     * @var string
     */
    public $displaytext;

    /**
     * The first DNS for the Zone
     *
     * @var string
     */
    public $dns1;

    /**
     * The second DNS for the Zone
     *
     * @var string
     */
    public $dns2;

    /**
     * Network domain name for the networks in the zone
     *
     * @var string
     */
    public $domain;

    /**
     * The UUID of the containing domain, null for public zones
     *
     * @var string
     */
    public $domainid;

    /**
     * The name of the containing domain, null for public zones
     *
     * @var string
     */
    public $domainname;

    /**
     * The guest CIDR address for the Zone
     *
     * @var string
     */
    public $guestcidraddress;

    /**
     * The first internal DNS for the Zone
     *
     * @var string
     */
    public $internaldns1;

    /**
     * The second internal DNS for the Zone
     *
     * @var string
     */
    public $internaldns2;

    /**
     * The first IPv6 DNS for the Zone
     *
     * @var string
     */
    public $ip6dns1;

    /**
     * The second IPv6 DNS for the Zone
     *
     * @var string
     */
    public $ip6dns2;

    /**
     * True if local storage offering enabled, false otherwise
     *
     * @var string
     */
    public $localstorageenabled;

    /**
     * Zone name
     *
     * @var string
     */
    public $name;

    /**
     * The network type of the zone; can be Basic or Advanced
     *
     * @var string
     */
    public $networktype;

    /**
     * True if security groups support is enabled, false otherwise
     *
     * @var string
     */
    public $securitygroupsenabled;

    /**
     * The vlan range of the zone
     *
     * @var string
     */
    public $vlan;

    /**
     * Zone Token
     *
     * @var string
     */
    public $zonetoken;

    /**
     * Sets capacity list
     *
     * @param   CapacityList $capacity
     * @return  ZoneData
     */
    public function setCapacity(CapacityList $capacity = null)
    {
        return $this->__call(__FUNCTION__, array($capacity));
    }

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  ZoneData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }

    /**
     * Sets resource details
     *
     * @param   object    $details
     * @return  ZoneData
     */
    public function setResourcedetails($details = null)
    {
        return $this->__call(__FUNCTION__, array($details));
    }

}