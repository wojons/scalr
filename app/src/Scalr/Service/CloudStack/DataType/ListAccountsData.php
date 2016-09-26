<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ListAccountsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ListAccountsData extends AbstractDataType
{

    /**
     * List accounts by account type.
     * Valid account types are 1 (admin), 2 (domain-admin), and 0 (user).
     *
     * @var string
     */
    public $accounttype;

    /**
     * List only resources belonging to the domain specified
     *
     * @var string
     */
    public $domainid;

    /**
     * List accounts by cleanuprequred attribute (values are true or false)
     *
     * @var string
     */
    public $iscleanuprequired;

    /**
     * List account by account ID
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
     * List account by account name
     *
     * @var string
     */
    public $name;

    /**
     * List accounts by state.
     * Valid states are enabled, disabled, and locked.
     *
     * @var string
     */
    public $state;

}