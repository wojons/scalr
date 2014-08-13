<?php
namespace Scalr\Service\CloudStack\Services\Vpn\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListVpnUsersData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListVpnUsersData extends AbstractDataType
{

    /**
     * Username for the vpn user
     *
     * @var string
     */
    public $username;

    /**
     * An optional account for the vpn user.
     * Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * An optional domainId for the vpn user.
     * If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * The uuid of the Vpn user
     *
     * @var string
     */
    public $id;

    /**
     * Defaults to false,
     * but if true, lists all resources from the parent specified by the domainId till leaves.
     *
     * @var string
     */
    public $isrecursive;

    /**
     * List by keyword
     *
     * @var string
     */
    public $keyword;

    /**
     * If set to false, list only resources belonging to the command's caller;
     * if set to true - list resources that the caller is authorized to see.
     * Default value is false
     *
     * @var string
     */
    public $listall;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

}