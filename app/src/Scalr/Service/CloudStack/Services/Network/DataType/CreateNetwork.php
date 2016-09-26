<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateNetwork
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateNetwork extends AbstractDataType
{

    /**
     * Required
     * The display text of the network
     *
     * @var string
     */
    public $displaytext;

    /**
     * Required
     * The name of the network
     *
     * @var string
     */
    public $name;

    /**
     * Required
     * The network offering id
     *
     * @var string
     */
    public $networkofferingid;

    /**
     * Required
     * The Zone ID for the network
     *
     * @var string
     */
    public $zoneid;

    /**
     * Account who will own the network
     *
     * @var string
     */
    public $account;

    /**
     * Network ACL Id associated for the network
     *
     * @var string
     */
    public $aclid;

    /**
     * Access control type; supported values are account and domain.
     * In 3.0 all shared networks should have aclType=Domain, and all Isolated networks - Account.
     * Account means that only the account owner can use the network,
     * domain - all accouns in the domain can use the network
     *
     * @var string
     */
    public $acltype;

    /**
     * An optional field, whether to the display the network to the end user or not.
     *
     * @var string
     */
    public $displaynetwork;

    /**
     * Domain ID of the account owning a network
     *
     * @var string
     */
    public $domainid;

    /**
     * The ending IP address in the network IP range.
     * If not specified, will be defaulted to startIP
     *
     * @var string
     */
    public $endip;

    /**
     * The ending IPv6 address in the IPv6 network range
     *
     * @var string
     */
    public $endipv6;

    /**
     * The gateway of the network.
     * Required for Shared networks and Isolated networks when it belongs to VPC
     *
     * @var string
     */
    public $gateway;

    /**
     * The CIDR of IPv6 network, must be at least /64
     *
     * @var string
     */
    public $ip6cidr;

    /**
     * The gateway of the IPv6 network.
     * Required for Shared networks and Isolated networks when it belongs to VPC
     *
     * @var string
     */
    public $ip6gateway;

    /**
     * The isolated private vlan for this network
     *
     * @var string
     */
    public $isolatedpvlan;

    /**
     * The netmask of the network. Required for Shared networks and Isolated networks when it belongs to VPC
     *
     * @var string
     */
    public $netmask;

    /**
     * Network domain
     *
     * @var string
     */
    public $networkdomain;

    /**
     * The Physical Network ID the network belongs to
     *
     * @var string
     */
    public $physicalnetworkId;

    /**
     * An optional project for the ssh key
     *
     * @var string
     */
    public $projectid;

    /**
     * The beginning IP address in the network IP range
     *
     * @var string
     */
    public $startip;

    /**
     * The beginning IPv6 address in the IPv6 network range
     *
     * @var string
     */
    public $startipv6;

    /**
     * Defines whether to allow subdomains to use networks dedicated to their parent domain(s).
     * Should be used with aclType=Domain, defaulted to allow.subdomain.network.access global config if not specified
     *
     * @var string
     */
    public $subdomainaccess;

    /**
     * The ID or VID of the network
     *
     * @var string
     */
    public $vlan;

    /**
     * The VPC network belongs to
     *
     * @var string
     */
    public $vpcid;

    /**
     * Constructor
     *
     * @param   string  $displayText        The display text of the network
     * @param   string  $name               The name of the network
     * @param   string  $networkOfferingId  The network offering id
     * @param   string  $zoneId             The Zone ID for the network
     */
    public function __construct($displayText, $name, $networkOfferingId, $zoneId)
    {
        $this->displaytext = $displayText;
        $this->name = $name;
        $this->networkofferingId = $networkOfferingId;
        $this->zoneId = $zoneId;
    }

}
