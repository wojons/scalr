<?php
namespace Scalr\Service\CloudStack\Services\Firewall\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;
use Scalr\Service\CloudStack\DataType\ResponseTagsList;

/**
 * ForwardingRuleResponseData
 *
 * @property  \Scalr\Service\CloudStack\DataType\ResponseTagsList      $tags
 * The list of resource tags associated with the rule
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ForwardingRuleResponseData extends AbstractDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('tags');

    /**
     * The ID of the port forwarding rule
     *
     * @var string
     */
    public $id;

    /**
     * The cidr list to forward traffic from
     *
     * @var string
     */
    public $cidrlist;

    /**
     * The public ip address for the port forwarding rule
     *
     * @var string
     */
    public $ipaddress;

    /**
     * The public ip address id for the port forwarding rule
     *
     * @var string
     */
    public $ipaddressid;

    /**
     * The id of the guest network the port forwarding rule belongs to
     *
     * @var string
     */
    public $networkid;

    /**
     * The ending port of port forwarding rule's private port range
     *
     * @var string
     */
    public $privateendport;

    /**
     * The starting port of port forwarding rule's private port range
     *
     * @var string
     */
    public $privateport;

    /**
     * The protocol of the port forwarding rule
     *
     * @var string
     */
    public $protocol;

    /**
     * The ending port of port forwarding rule's private port range
     *
     * @var string
     */
    public $publicendport;

    /**
     * The starting port of port forwarding rule's public port range
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
     * The VM display name for the port forwarding rule
     *
     * @var string
     */
    public $virtualmachinedisplayname;

    /**
     * The VM ID for the port forwarding rule
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * The VM name for the port forwarding rule
     *
     * @var string
     */
    public $virtualmachinename;

    /**
     * The vm ip address for the port forwarding rule
     *
     * @var string
     */
    public $vmguestip;

    /**
     * Sets tags
     *
     * @param   ResponseTagsList $tags
     * @return  ForwardingRuleResponseData
     */
    public function setTags(ResponseTagsList $tags = null)
    {
        return $this->__call(__FUNCTION__, array($tags));
    }
}