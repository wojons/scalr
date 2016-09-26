<?php
namespace Scalr\Service\CloudStack\Services\SecurityGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListSecurityGroupsData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListSecurityGroupsData extends AbstractDataType
{

    /**
     * List resources by account.
     * Must be used with the domainId parameter.
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
     * List the security group by the id provided
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

    /**
     * Lists security groups by name
     *
     * @var string
     */
    public $securitygroupname;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * Lists security groups by virtual machine id
     *
     * @var string
     */
    public $virtualmachineid;

}