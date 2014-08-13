<?php
namespace Scalr\Service\CloudStack\Services\SshKeyPair\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListSshKeyPairsData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListSshKeyPairsData extends AbstractDataType
{

    /**
     * A key pair name to look for
     *
     * @var string
     */
    public $name;

    /**
     * List resources by account. Must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * List only resources belonging to the domain specified
     *
     * @var string
     */
    public $domainid;

    /**
     * A public key fingerprint to look for
     *
     * @var string
     */
    public $fingerprint;

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