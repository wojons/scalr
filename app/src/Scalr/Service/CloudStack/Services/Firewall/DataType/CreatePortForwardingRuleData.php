<?php
namespace Scalr\Service\CloudStack\Services\Firewall\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreatePortForwardingRuleData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreatePortForwardingRuleData extends AbstractDataType
{

    /**
     * Required
     * The IP address id of the port forwarding rule
     *
     * @var string
     */
    public $ipaddressid;

    /**
     * Required
     * The starting port of port forwarding rule's private port range
     *
     * @var string
     */
    public $privateport;

    /**
     * Required
     * The protocol for the port fowarding rule. Valid values are TCP or UDP.
     *
     * @var string
     */
    public $protocol;

    /**
     * Required
     * The starting port of port forwarding rule's public port range
     *
     * @var string
     */
    public $publicport;

    /**
     * Required
     * The ID of the virtual machine for the port forwarding rule
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * The cidr list to forward traffic from
     *
     * @var string
     */
    public $cidrlist;

    /**
     * The network of the vm the Port Forwarding rule will be created for.
     * Required when public Ip address is not associated with any Guest network yet (VPC case)
     *
     * @var string
     */
    public $networkid;

    /**
     * if true, firewall rule for source/end pubic port is automatically created;
     * if false - firewall rule has to be created explicitely.
     * If not specified
     * 1) defaulted to false when PF rule is being created for VPC guest network
     * 2) in all other cases defaulted to true
     *
     * @var string
     */
    public $openfirewall;

    /**
     * The ending port of port forwarding rule's private port range
     *
     * @var string
     */
    public $privateendport;

    /**
     * The ending port of port forwarding rule's private port range
     *
     * @var string
     */
    public $publicendport;

    /**
     * VM guest nic Secondary ip address for the port forwarding rule
     *
     * @var string
     */
    public $vmguestip;

    /**
     * Constructor
     *
     * @param   string  $ipaddressid         The IP address id of the port forwarding rule
     * @param   string  $privateport         The starting port of port forwarding rule's private port range
     * @param   string  $protocol            The protocol for the port fowarding rule. Valid values are TCP or UDP.
     * @param   string  $publicport          The starting port of port forwarding rule's public port range
     * @param   string  $virtualmachineid    The ID of the virtual machine for the port forwarding rule
     */
    public function __construct($ipaddressid, $privateport, $protocol, $publicport, $virtualmachineid)
    {
        $this->ipaddressid = $ipaddressid;
        $this->privateport = $privateport;
        $this->protocol = $protocol;
        $this->publicport = $publicport;
        $this->virtualmachineid = $virtualmachineid;
    }

}
