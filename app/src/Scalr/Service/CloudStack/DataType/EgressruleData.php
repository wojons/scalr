<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * EgressruleData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class EgressruleData extends AbstractDataType
{

    /**
     * Account owning the security group rule
     *
     * @var string
     */
    public $account;

    /**
     * The CIDR notation for the base IP address of the security group rule
     *
     * @var string
     */
    public $cidr;

    /**
     * The ending IP of the security group rule
     *
     * @var string
     */
    public $endport;

    /**
     * The code for the ICMP message response
     *
     * @var string
     */
    public $icmpcode;

    /**
     * The type of the ICMP message response
     *
     * @var string
     */
    public $icmptype;

    /**
     * The protocol of the security group rule
     *
     * @var string
     */
    public $protocol;

    /**
     * The id of the security group rule
     *
     * @var string
     */
    public $ruleid;

    /**
     * Security group name
     *
     * @var string
     */
    public $securitygroupname;

    /**
     * The starting IP of the security group rule
     *
     * @var string
     */
    public $startport;

}