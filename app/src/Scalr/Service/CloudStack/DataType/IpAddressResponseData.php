<?php
namespace Scalr\Service\CloudStack\DataType;

use \DateTime;

/**
 * IpAddressResponseData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with ip address
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class IpAddressResponseData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('tags');

    /**
     * Public IP address id
     *
     * @var string
     */
    public $id;

    /**
     * The account the public IP address is associated with
     *
     * @var string
     */
    public $account;

    /**
     * Date the public IP address was acquired
     *
     * @var DateTime
     */
    public $allocated;

    /**
     * The ID of the Network associated with the IP address
     *
     * @var string
     */
    public $associatednetworkid;

    /**
     * The name of the Network associated with the IP address
     *
     * @var string
     */
    public $associatednetworkname;

    /**
     * The domain the public IP address is associated with
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID the public IP address is associated with
     *
     * @var string
     */
    public $domainid;

    /**
     * The virtual network for the IP address
     *
     * @var string
     */
    public $forvirtualnetwork;

    /**
     * Public IP address
     *
     * @var string
     */
    public $ipaddress;

    /**
     * Is public IP portable across the zones
     *
     * @var string
     */
    public $isportable;

    /**
     * True if the IP address is a source nat address, false otherwise
     *
     * @var string
     */
    public $issourcenat;

    /**
     * True if this ip is for static nat, false otherwise
     *
     * @var string
     */
    public $isstaticnat;

    /**
     * True if this ip is system ip (was allocated as a part of deployVm or createLbRule)
     *
     * @var string
     */
    public $issystem;

    /**
     * The ID of the Network where ip belongs to
     *
     * @var string
     */
    public $networkid;

    /**
     * The physical network this belongs to
     *
     * @var string
     */
    public $physicalnetworkid;

    /**
     * The project name of the address
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the ipaddress
     *
     * @var string
     */
    public $projectid;

    /**
     * Purpose of the IP address.
     * In Acton this value is not null for Ips with isSystem=true, and can have either StaticNat or LB value
     *
     * @var string
     */
    public $purpose;

    /**
     * State of the ip address. Can be: Allocatin, Allocated and Releasing
     *
     * @var string
     */
    public $state;

    /**
     * Virutal machine display name the ip address is assigned to (not null only for static nat Ip)
     *
     * @var string
     */
    public $virtualmachinedisplayname;

    /**
     * Virutal machine id the ip address is assigned to (not null only for static nat Ip)
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * Virutal machine name the ip address is assigned to (not null only for static nat Ip)
     *
     * @var string
     */
    public $virtualmachinename;

    /**
     * The ID of the VLAN associated with the IP address.
     * This parameter is visible to ROOT admins only
     *
     * @var string
     */
    public $vlanid;

    /**
     * The VLAN associated with the IP address
     *
     * @var string
     */
    public $vlanname;

    /**
     * Virutal machine (dnat) ip address (not null only for static nat Ip)
     *
     * @var string
     */
    public $vmipaddress;

    /**
     * VPC the ip belongs to
     *
     * @var string
     */
    public $vpcid;

    /**
     * The ID of the zone the public IP address belongs to
     *
     * @var string
     */
    public $zoneid;

    /**
     * The name of the zone the public IP address belongs to
     *
     * @var string
     */
    public $zonename;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  IpAddressResponseData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }
}