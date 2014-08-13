<?php
namespace Scalr\Service\CloudStack\Services\Network\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListNetworkData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListNetworkData extends AbstractDataType
{

    /**
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
     * Access control type; supported values are account and domain.
     * In 3.0 all shared networks should have aclType=Domain, and all Isolated networks - Account.
     * Account means that only the account owner can use the network,
     * domain - all accouns in the domain can use the network
     *
     * @var string
     */
    public $acltype;

    /**
     * List networks available for vm deployment
     *
     * @var string
     */
    public $canusefordeploy;

    /**
     * Domain ID of the account owning a network
     *
     * @var string
     */
    public $domainid;

    /**
     * The network belongs to vpc
     *
     * @var string
     */
    public $forvpc;

    /**
     * List networks by id
     *
     * @var string
     */
    public $id;

    /**
     * Defaults to false, but if true, lists all resources from the parent specified by the domainId till leaves.
     *
     * @var string
     */
    public $isrecursive;

    /**
     * True if network is system, false otherwise
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
     * If set to false, list only resources belonging to the command's caller;
     * if set to true - list resources that the caller is authorized to see.
     * Default value is false
     *
     * @var string
     */
    public $listall;

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
     * List networks by restartRequired
     *
     * @var string
     */
    public $restartrequired;

    /**
     * The VPC network belongs to
     *
     * @var string
     */
    public $vpcid;

    /**
     * True if need to list only networks which support specifying ip ranges
     *
     * @var string
     */
    public $specifyipranges;

    /**
     * List networks supporting certain services
     *
     * @var string
     */
    public $supportedservices;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * Type of the traffic
     *
     * @var string
     */
    public $traffictype;

    /**
     * The type of the network. Supported values are: Isolated and Shared
     *
     * @var string
     */
    public $type;

}