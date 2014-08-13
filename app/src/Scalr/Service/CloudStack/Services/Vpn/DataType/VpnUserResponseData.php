<?php
namespace Scalr\Service\CloudStack\Services\Vpn\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * VpnUserResponseData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class VpnUserResponseData extends AbstractDataType
{

    /**
     * The vpn userID
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
     * The state of the rule
     *
     * @var string
     */
    public $state;

    /**
     * The username of the vpn user
     *
     * @var string
     */
    public $username;

}