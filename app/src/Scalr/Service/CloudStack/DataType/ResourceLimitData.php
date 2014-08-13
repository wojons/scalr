<?php
namespace Scalr\Service\CloudStack\DataType;

/**
 * ResourceLimitData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 *
 */
class ResourceLimitData extends AbstractDataType
{

    /**
     * The account of the resource limit
     *
     * @var string
     */
    public $account;

    /**
     * The domain name of the resource limit
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID of the resource limit
     *
     * @var string
     */
    public $domainid;

    /**
     * The maximum number of the resource.
     * A -1 means the resource currently has no limit.
     *
     * @var string
     */
    public $max;

    /**
     * The project name of the resource limit
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the resource limit
     *
     * @var string
     */
    public $projectid;

    /**
     * Resource type.
     * Values include 0, 1, 2, 3, 4, 6, 7, 8, 9, 10, 11.
     * See the resourceType parameter for more information on these values.
     *
     * @var string
     */
    public $resourcetype;

}