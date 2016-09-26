<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ResponseTagsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ResponseTagsData extends AbstractDataType
{

    /**
     * The account associated with the tag
     *
     * @var string
     */
    public $account;

    /**
     * Customer associated with the tag
     *
     * @var string
     */
    public $customer;

    /**
     * The domain associated with the tag
     *
     * @var string
     */
    public $domain;

    /**
     * The ID of the domain associated with the tag
     *
     * @var string
     */
    public $domainid;

    /**
     * Tag key name
     *
     * @var string
     */
    public $key;

    /**
     * The project name where tag belongs to
     *
     * @var string
     */
    public $project;

    /**
     * The project id the tag belongs to
     *
     * @var string
     */
    public $projectid;

    /**
     * Id of the resource
     *
     * @var string
     */
    public $resourceid;

    /**
     * Resource type
     *
     * @var string
     */
    public $resourcetype;

    /**
     * Tag value
     *
     * @var string
     */
    public $value;

}