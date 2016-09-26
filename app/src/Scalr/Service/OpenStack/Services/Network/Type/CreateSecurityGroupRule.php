<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\AbstractInitType;

/**
 * CreateSecurityGroupRule
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (11.08.2014)
 */
class CreateSecurityGroupRule extends AbstractInitType
{

    /**
     * The security group ID to associate with this security group rule.
     *
     * @var string
     */
    public $security_group_id;

    /**
     * Direction
     *
     * Ingress or egress: The direction in which the security group rule is applied.
     * For a compute instance, an ingress security group rule is applied to incoming (ingress)
     * traffic for that instance. An egress rule is applied to traffic leaving the instance.
     *
     * @var string
     */
    public $direction;

    /**
     * The minimum port number in the range that is matched by the security group rule.
     * If the protocol is TCP or UDP, this value must be less than or equal to the value
     * of the port_range_max attribute. If the protocol is ICMP, this value must be an ICMP type.
     *
     * optional
     *
     * @var int
     */
    public $port_range_min;

    /**
     * The maximum port number in the range that is matched by the security group rule.
     * The port_range_min attribute constrains the port_range_max attribute.
     * If the protocol is ICMP, this value must be an ICMP type.
     *
     * optional
     *
     * @var int
     */
    public $port_range_max;

    /**
     * The protocol that is matched by the security group rule.
     * Valid values are null, tcp, udp, and icmp.
     *
     * optional
     *
     * @var string
     */
    public $protocol;

    /**
     * The remote group ID to be associated with this security group rule.
     * You can specify either remote_group_id or remote_ip_prefix in the request body.
     *
     * optional
     *
     * @var string
     */
    public $remote_group_id;

    /**
     * The remote IP prefix to be associated with this security group rule.
     * You can specify either remote_group_id or remote_ip_prefix in the request body.
     * This attribute matches the specified IP prefix as the source IP address of the IP packet.
     *
     * optional
     *
     * @var string
     */
    public $remote_ip_prefix;

    /**
     * Constructor
     *
     * @param   string     $securityGroupId The ID of the security group to associate with the rule
     * @param   string     $direction       Direction
     */
    public function __construct($securityGroupId = null, $direction = null)
    {
        $this->security_group_id = $securityGroupId;
        $this->direction = $direction;
    }

    /**
     * Initializes a new object
     *
     * @param   string     $securityGroupId The ID of the security group to associate with the rule
     * @param   string     $direction       Direction
     * @return  CreateSecurityGroupRule
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }
}