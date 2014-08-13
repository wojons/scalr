<?php
namespace Scalr\Service\CloudStack\Services\Instance\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * ListVirtualMachinesData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class ListVirtualMachinesData extends AbstractDataType
{

    /**
     * List resources by account. Must be used with the domainId parameter.
     *
     * @var string
     */
    public $account;

    /**
     * List vms by affinity group
     *
     * @var string
     */
    public $affinitygroupid;

    /**
     * Comma separated list of host details requested, value can be a list of [all, group, nics, stats, secgrp, tmpl, servoff, iso, volume, min, affgrp].
     * If no parameter is passed in, the details will be defaulted to all
     *
     * @var string
     */
    public $details;

    /**
     * List only resources belonging to the domain specified
     *
     * @var string
     */
    public $domainid;

    /**
     * List by network type; true if need to list vms using Virtual Network, false otherwise
     *
     * @var string
     */
    public $forvirtualnetwork;

    /**
     * The group ID
     *
     * @var string
     */
    public $groupid;

    /**
     * The host ID
     *
     * @var string
     */
    public $hostid;

    /**
     * The target hypervisor for the template
     *
     * @var string
     */
    public $hypervisor;

    /**
     * The ID of the virtual machine
     *
     * @var string
     */
    public $id;

    /**
     * List vms by iso
     *
     * @var string
     */
    public $isoid;

    /**
     * Defaults to false, but if true, lists all resources from the parent specified by the domainId till leaves.
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
     * Name of the virtual machine
     *
     * @var string
     */
    public $name;

    /**
     * List by network id
     *
     * @var string
     */
    public $networkid;

    /**
     * The pod ID
     *
     * @var string
     */
    public $podid;

    /**
     * List objects by project
     *
     * @var string
     */
    public $projectid;

    /**
     * State of the virtual machine
     *
     * @var string
     */
    public $state;

    /**
     * The storage ID where vm's volumes belong to
     *
     * @var string
     */
    public $storageid;

    /**
     * List resources by tags (key/value pairs)
     *
     * @var string
     */
    public $tags;

    /**
     * List vms by template
     *
     * @var string
     */
    public $templateid;

    /**
     * List vms by vpc
     *
     * @var string
     */
    public $vpcid;

    /**
     * The availability zone ID
     *
     * @var string
     */
    public $zoneid;

}