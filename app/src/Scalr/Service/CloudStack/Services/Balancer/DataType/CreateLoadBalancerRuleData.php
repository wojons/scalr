<?php
namespace Scalr\Service\CloudStack\Services\Balancer\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateBalancerRuleData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateBalancerRuleData extends AbstractDataType
{

    /**
     * Required
     * Load balancer algorithm (source, roundrobin, leastconn)
     *
     * @var string
     */
    public $algorithm;

    /**
     * Required
     * Name of the load balancer rule
     *
     * @var string
     */
    public $name;

    /**
     * Required
     * The private port of the private ip address/virtual machine
     * where the network traffic will be load balanced to
     *
     * @var string
     */
    public $privateport;

    /**
     * Required
     * The public port from where the network traffic will be load balanced from
     *
     * @var string
     */
    public $publicport;

    /**
     * Zone where the load balancer is going to be created.
     * This parameter is required when LB service provider is ElasticLoadBalancerVm
     *
     * @var string
     */
    public $zoneid;

    /**
     * The account associated with the load balancer.
     * Must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * The cidr list to forward traffic from
     *
     * @var string
     */
    public $cidrlist;

    /**
     * The description of the load balancer rule
     *
     * @var string
     */
    public $description;

    /**
     * The domain ID associated with the load balancer
     *
     * @var string
     */
    public $domainid;

    /**
     * The guest network this rule will be created for.
     * Required when public Ip address is not associated with any Guest network yet (VPC case)
     *
     * @var string
     */
    public $networkid;

    /**
     * if true, firewall rule for source/end pubic port is automatically created;
     * if false - firewall rule has to be created explicitely.
     * If not specified 1) defaulted to false when LB rule is being created for VPC guest network
     *                  2) in all other cases defaulted to true
     *
     * @var string
     */
    public $openfirewall;

    /**
     * The protocol for the LB
     *
     * @var string
     */
    public $protocol;

    /**
     * Public ip address id from where the network traffic will be load balanced from
     *
     * @var string
     */
    public $publicipid;

    /**
     * Constructor
     *
     * @param   string  algorithm           Load balancer algorithm (source, roundrobin, leastconn)
     * @param   string  $name               Name of the load balancer rule
     * @param   string  privateport         The private port of the private ip address/virtual machine where the network traffic will be load balanced to
     * @param   string  publicport          The public port from where the network traffic will be load balanced from
     */
    public function __construct($algorithm, $name, $privateport, $publicport)
    {
        $this->algorithm = $algorithm;
        $this->name = $name;
        $this->privateport = $privateport;
        $this->publicport = $publicport;
    }

}
