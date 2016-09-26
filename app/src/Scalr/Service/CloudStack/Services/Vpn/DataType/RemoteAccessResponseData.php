<?php
namespace Scalr\Service\CloudStack\Services\Vpn\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * RemoteAccessResponseData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class RemoteAccessResponseData extends AbstractDataType
{

    /**
     * The id of the remote access vpn
     *
     * @var string
     */
    public $id;

    /**
     * The account of the remote access vpn
     *
     * @var string
     */
    public $account;

    /**
     * The domain name of the account of the remote access vpn
     *
     * @var string
     */
    public $domain;

    /**
     * The domain id of the account of the remote access vpn
     *
     * @var string
     */
    public $domainid;

    /**
     * The range of ips to allocate to the clients
     *
     * @var string
     */
    public $iprange;

    /**
     * The ipsec preshared key
     *
     * @var string
     */
    public $presharedkey;

    /**
     * The project name of the vpn
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the vpn
     *
     * @var string
     */
    public $projectid;

    /**
     * The public ip address of the vpn server
     *
     * @var string
     */
    public $publicip;

    /**
     * The public ip address of the vpn server
     *
     * @var string
     */
    public $publicipid;

    /**
     * The state of the rule
     *
     * @var string
     */
    public $state;

}