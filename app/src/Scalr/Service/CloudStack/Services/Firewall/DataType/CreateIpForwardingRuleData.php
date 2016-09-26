<?php
namespace Scalr\Service\CloudStack\Services\Firewall\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateIpForwardingRuleData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateIpForwardingRuleData extends AbstractDataType
{

    /**
     * Required
     * The public IP address id of the forwarding rule, already associated via associateIp
     *
     * @var string
     */
    public $ipaddressid;

    /**
     * Required
     * The protocol for the rule. Valid values are TCP or UDP.
     *
     * @var string
     */
    public $protocol;

    /**
     * Required
     * The start port for the rule
     *
     * @var string
     */
    public $startport;

    /**
     * The cidr list to forward traffic from
     *
     * @var string
     */
    public $cidrlist;

    /**
     * The end port for the rule
     *
     * @var string
     */
    public $endport;

    /**
     * if true, firewall rule for source/end pubic port is automatically created;
     * if false - firewall rule has to be created explicitely.
     * Has value true by default
     *
     * @var string
     */
    public $openfirewall;

    /**
     * Constructor
     *
     * @param   string  $ipaddressid         The public IP address id of the forwarding rule, already associated via associateIp
     * @param   string  $protocol            The protocol for the rule. Valid values are TCP or UDP.
     * @param   string  $startport           The start port for the rule
     */
    public function __construct($ipaddressid, $protocol, $startport)
    {
        $this->ipaddressid = $ipaddressid;
        $this->protocol = $protocol;
        $this->startport = $startport;
    }

}
