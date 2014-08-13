<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\JobStatusData;
use Scalr\Service\CloudStack\DataType\ResponseTagsList;

/**
 * NetworkResponseData
 *
 * @property  \Scalr\Service\CloudStack\Services\Network\DataType\NetworkResponseServiceList   $service
 * The list of services
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with network
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class NetworkResponseData extends JobStatusData
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('service', 'tags');

    /**
     * The id of the network
     *
     * @var string
     */
    public $id;

    /**
     * The owner of the network
     *
     * @var string
     */
    public $account;

    /**
     * ACL Id associated with the VPC network
     *
     * @var string
     */
    public $aclid;

    /**
     * Acl type - access type to the network
     *
     * @var string
     */
    public $acltype;

    /**
     * Broadcast domain type of the network
     *
     * @var string
     */
    public $broadcastdomaintype;

    /**
     * Broadcast uri of the network. This parameter is visible to ROOT admins only
     *
     * @var string
     */
    public $broadcasturi;

    /**
     * List networks available for vm deployment
     *
     * @var string
     */
    public $canusefordeploy;

    /**
     * Cloudstack managed address space, all CloudStack managed VMs get IP address from CIDR
     *
     * @var string
     */
    public $cidr;

    /**
     * An optional field, whether to the display the network to the end user or not.
     *
     * @var string
     */
    public $displaynetwork;

    /**
     * The displaytext of the network
     *
     * @var string
     */
    public $displaytext;

    /**
     * The first DNS for the network
     *
     * @var string
     */
    public $dns1;

    /**
     * The second DNS for the network
     *
     * @var string
     */
    public $dns2;

    /**
     * The domain name of the network owner
     *
     * @var string
     */
    public $domain;

    /**
     * The domain id of the network owner
     *
     * @var string
     */
    public $domainid;

    /**
     * The network's gateway
     *
     * @var string
     */
    public $gateway;

    /**
     * The cidr of IPv6 network
     *
     * @var string
     */
    public $ip6cidr;

    /**
     * The gateway of IPv6 network
     *
     * @var string
     */
    public $ip6gateway;

    /**
     * True if network is default, false otherwise
     *
     * @var string
     */
    public $isdefault;

    /**
     * List networks that are persistent
     *
     * @var string
     */
    public $ispersistent;

    /**
     * True if network is system, false otherwise
     *
     * @var string
     */
    public $issystem;

    /**
     * The name of the network
     *
     * @var string
     */
    public $name;

    /**
     * The network's netmask
     *
     * @var string
     */
    public $netmask;

    /**
     * The network CIDR of the guest network configured with IP reservation.
     * It is the summation of CIDR and RESERVED_IP_RANGE
     *
     * @var string
     */
    public $networkcidr;

    /**
     * The network domain
     *
     * @var string
     */
    public $networkdomain;

    /**
     * Availability of the network offering the network is created from
     *
     * @var string
     */
    public $networkofferingavailability;

    /**
     * True if network offering is ip conserve mode enabled
     *
     * @var string
     */
    public $networkofferingconservemode;

    /**
     * Display text of the network offering the network is created from
     *
     * @var string
     */
    public $networkofferingdisplaytext;

    /**
     * Network offering id the network is created from
     *
     * @var string
     */
    public $networkofferingid;

    /**
     * Name of the network offering the network is created from
     *
     * @var string
     */
    public $networkofferingname;

    /**
     * The physical network id
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
     * Related to what other network configuration
     *
     * @var string
     */
    public $related;

    /**
     * The network's IP range not to be used by CloudStack guest VMs and can be used for non CloudStack purposes
     *
     * @var string
     */
    public $reservediprange;

    /**
     * True network requires restart
     *
     * @var string
     */
    public $restartrequired;

    /**
     * True if network supports specifying ip ranges, false otherwise
     *
     * @var string
     */
    public $specifyipranges;

    /**
     * State of the network
     *
     * @var string
     */
    public $state;

    /**
     * True if users from subdomains can access the domain level network
     *
     * @var string
     */
    public $subdomainaccess;

    /**
     * The traffic type of the network
     *
     * @var string
     */
    public $traffictype;

    /**
     * The type of the network
     *
     * @var string
     */
    public $type;

    /**
     * The vlan of the network. This parameter is visible to ROOT admins only
     *
     * @var string
     */
    public $vlan;

    /**
     * VPC the network belongs to
     *
     * @var string
     */
    public $vpcid;

    /**
     * Zone id of the network
     *
     * @var string
     */
    public $zoneid;

    /**
     * The name of the zone the network belongs to
     *
     * @var string
     */
    public $zonename;

    /**
     * Sets service list
     *
     * @param   NetworkResponseService $service
     * @return  NetworkResponseData
     */
    public function setService(NetworkResponseServiceList $service = null)
    {
        return $this->__call(__FUNCTION__, array($service));
    }

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  NetworkResponseData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }

}