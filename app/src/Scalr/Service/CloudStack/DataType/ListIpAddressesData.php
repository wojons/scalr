<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ListIpAddressesData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListIpAddressesData extends AbstractDataType
{

    /**
     * List resources by account.
     * Must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * Limits search results to allocated public IP addresses
     *
     * @var string
     */
    public $allocatedonly;

    /**
     * Lists all public IP addresses associated to the network specified
     *
     * @var string
     */
    public $associatednetworkid;

    /**
     * List only resources belonging to the domain specified
     *
     * @var string
     */
    public $domainid;

    /**
     * List only ips used for load balancing
     *
     * @var string
     */
    public $forloadbalancing;

    /**
     * The virtual network for the IP address
     *
     * @var string
     */
    public $forvirtualnetwork;

    /**
     * Lists ip address by id
     *
     * @var string
     */
    public $id;

    /**
     * Lists the specified IP address
     *
     * @var string
     */
    public $ipaddress;

    /**
     * Defaults to false,
     * but if true, lists all resources from the parent specified by the domainId till leaves.
     *
     * @var string
     */
    public $isrecursive;

    /**
     * List only source nat ip addresses
     *
     * @var string
     */
    public $issourcenat;

    /**
     * List only static nat ip addresses
     *
     * @var string
     */
    public $isstaticnat;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * If set to false, list only resources belonging to the command's caller;
     * if set to true - list resources that the caller is authorized to see.
     * Default value is false
     *
     * @var string
     */
    public $listall;

    /**
     * Lists all public IP addresses by physical network id
     *
     * @var string
     */
    public $physicalnetworkid;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * Lists all public IP addresses by VLAN ID
     *
     * @var string
     */
    public $vlanid;

    /**
     * List ips belonging to the VPC
     *
     * @var string
     */
    public $vpcid;

    /**
     * Lists all public IP addresses by Zone ID
     *
     * @var string
     */
    public $zoneid;

}