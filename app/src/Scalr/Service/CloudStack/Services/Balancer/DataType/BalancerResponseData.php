<?php
namespace Scalr\Service\CloudStack\Services\Balancer\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;
use Scalr\Service\CloudStack\DataType\ResponseTagsList;

/**
 * BalancerResponseData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with the rule
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class BalancerResponseData extends AbstractDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('tags');

    /**
     * The load balancer rule ID
     *
     * @var string
     */
    public $id;

    /**
     * The account associated with the load balancer.
     *
     * @var string
     */
    public $account;

    /**
     * Load balancer algorithm (source, roundrobin, leastconn)
     *
     * @var string
     */
    public $algorithm;

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
     * The domain of the load balancer rule
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID associated with the load balancer
     *
     * @var string
     */
    public $domainid;

    /**
     * Name of the load balancer rule
     *
     * @var string
     */
    public $name;

    /**
     * The id of the guest network the lb rule belongs to
     *
     * @var string
     */
    public $networkid;

    /**
     * The private port
     *
     * @var string
     */
    public $privateport;

    /**
     * The project name of the load balancer
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the load balancer
     *
     * @var string
     */
    public $projectid;

    /**
     * The protocol for the LB
     *
     * @var string
     */
    public $protocol;

    /**
     * The public ip address
     *
     * @var string
     */
    public $publicip;

    /**
     * The public ip address id
     *
     * @var string
     */
    public $publicipid;

    /**
     * The public port
     *
     * @var string
     */
    public $publicport;

    /**
     * The state of the rule
     *
     * @var string
     */
    public $state;

    /**
     * The id of the zone the rule belongs to
     *
     * @var string
     */
    public $zoneid;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  BalancerResponseData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }
}