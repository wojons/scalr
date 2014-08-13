<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * AssociateIpAddressData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class AssociateIpAddressData extends AbstractDataType
{

    /**
     * The account to associate with this IP address
     *
     * @var string
     */
    public $account;

    /**
     * The ID of the domain to associate with this IP address
     *
     * @var string
     */
    public $domainid;

    /**
     * Should be set to true if public IP is required to be transferable across zones, if not specified defaults to false
     *
     * @var string
     */
    public $isportable;

    /**
     * The network this ip address should be associated to.
     *
     * @var string
     */
    public $networkid;

    /**
     * Deploy vm for the project
     *
     * @var string
     */
    public $projectid;

    /**
     * Region ID from where portable ip is to be associated.
     *
     * @var string
     */
    public $regionid;

    /**
     * The VPC you want the ip address to be associated with
     *
     * @var string
     */
    public $vpcid;

    /**
     * The ID of the availability zone you want to acquire an public IP address from
     *
     * @var string
     */
    public $zoneid;

}