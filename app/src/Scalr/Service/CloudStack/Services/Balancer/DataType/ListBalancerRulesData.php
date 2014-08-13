<?php
namespace Scalr\Service\CloudStack\Services\Balancer\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListBalancerRulesData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListBalancerRulesData extends AbstractDataType
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
     * The ID of the load balancer rule
     *
     * @var string
     */
    public $id;

    /**
     * Defaults to false, but if true,
     * lists all resources from the parent specified by the domainId till leaves.
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
     * The name of the load balancer rule
     *
     * @var string
     */
    public $name;

    /**
     * List by network id the rule belongs to
     *
     * @var string
     */
    public $networkid;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

    /**
     * The public IP address id of the load balancer rule
     *
     * @var string
     */
    public $publicipid;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * The ID of the virtual machine of the load balancer rule
     *
     * @var string
     */
    public $virtualmachineid;

    /**
     * The availability zone ID
     *
     * @var string
     */
    public $zoneid;
}