<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ListResourceLimitsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListResourceLimitsData extends AbstractDataType
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
     * Lists resource limits by ID.
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
     * Type of resource to update. Values are 0, 1, 2, 3, and 4.0 - Instance.
     * Number of instances a user can create.
     * 1 - IP. Number of public IP addresses an account can own.
     * 2 - Volume. Number of disk volumes an account can own.
     * 3 - Snapshot. Number of snapshots an account can own.
     * 4 - Template. Number of templates an account can register/create.
     * 5 - Project. Number of projects an account can own.
     * 6 - Network. Number of networks an account can own.
     * 7 - VPC. Number of VPC an account can own.
     * 8 - CPU. Number of CPU an account can allocate for his resources.
     * 9 - Memory. Amount of RAM an account can allocate for his resources.
     * 10 - Primary Storage. Amount of Primary storage an account can allocate for his resoruces.
     * 11 - Secondary Storage.
     * Amount of Secondary storage an account can allocate for his resources.
     *
     * @var string
     */
    public $resourcetype;


}