<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * NicData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class NicData extends AbstractDataType
{

    /**
     * The ID of the nic
     *
     * @var string
     */
    public $id;

    /**
     * The broadcast uri of the nic
     *
     * @var string
     */
    public $broadcasturi;

    /**
     * The gateway of the nic
     *
     * @var string
     */
    public $gateway;

    /**
     * The IPv6 address of network
     *
     * @var string
     */
    public $ip6address;

    /**
     * The cidr of IPv6 network
     *
     * @var string
     */
    public $ip6cidr;

    /**
     * The gateway of IPv6 network
     *
     * @var string
     */
    public $ip6gateway;

    /**
     * The ip address of the nic
     *
     * @var string
     */
    public $ipaddress;

    /**
     * true if nic is default, false otherwise
     *
     * @var string
     */
    public $isdefault;

    /**
     * The isolation uri of the nic
     *
     * @var string
     */
    public $isolationuri;

    /**
     * true if nic is default, false otherwise
     *
     * @var string
     */
    public $macaddress;

    /**
     * The netmask of the nic
     *
     * @var string
     */
    public $netmask;

    /**
     * The ID of the corresponding network
     *
     * @var string
     */
    public $networkid;

    /**
     * The name of the corresponding network
     *
     * @var string
     */
    public $networkname;

    /**
     * The Secondary ipv4 addr of nic
     *
     * @var string
     */
    public $secondaryip;

    /**
     * The traffic type of the nic
     *
     * @var string
     */
    public $traffictype;

    /**
     * The type of the nic
     *
     * @var string
     */
    public $type;

}