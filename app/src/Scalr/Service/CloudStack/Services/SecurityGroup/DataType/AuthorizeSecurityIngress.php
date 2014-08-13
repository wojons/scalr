<?php
namespace Scalr\Service\CloudStack\Services\SecurityGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * AuthorizeSecurityIngress
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class AuthorizeSecurityIngress extends AbstractDataType
{

    /**
     * An optional account for the security group.
     * Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * The cidr list associated
     *
     * @var string
     */
    public $cidrlist;

    /**
     * An optional domainId for the security group.
     * If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * End port for this ingress rule
     *
     * @var string
     */
    public $endport;

    /**
     * Error code for this icmp message
     *
     * @var string
     */
    public $icmpcode;

    /**
     * Type of the icmp message being sent
     *
     * @var string
     */
    public $icmptype;

    /**
     * An optional project of the security group
     *
     * @var string
     */
    public $projectid;

    /**
     * TCP is default. UDP is the other supported protocol
     *
     * @var string
     */
    public $protocol;

    /**
     * The ID of the security group.
     * Mutually exclusive with securityGroupName parameter
     *
     * @var string
     */
    public $securitygroupid;

    /**
     * The name of the security group.
     * Mutually exclusive with securityGroupName parameter
     *
     * @var string
     */
    public $securitygroupname;

    /**
     * Start port for this ingress rule
     *
     * @var string
     */
    public $startport;

    /**
     * User to security group mapping
     *
     * @var string
     */
    public $usersecuritygrouplist;

}