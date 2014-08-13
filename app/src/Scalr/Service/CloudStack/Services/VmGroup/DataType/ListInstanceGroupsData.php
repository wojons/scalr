<?php
namespace Scalr\Service\CloudStack\Services\VmGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListInstanceGroupsData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListInstanceGroupsData extends AbstractDataType
{

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
     * List instance groups by ID
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
     * List instance groups by name
     *
     * @var string
     */
    public $name;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

}