<?php
namespace Scalr\Service\CloudStack\Services\VmGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;
use DateTime;

/**
 * InstanceGroupData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class InstanceGroupData extends AbstractDataType
{

    /**
     * The id of the instance group
     *
     * @var string
     */
    public $id;

    /**
     * The account owning the instance group
     *
     * @var string
     */
    public $account;

    /**
     * Time and date the instance group was created
     *
     * @var DateTime
     */
    public $created;

    /**
     * The domain name of the instance group
     *
     * @var string
     */
    public $domain;

    /**
     * The domain ID of the instance group
     *
     * @var string
     */
    public $domainid;

    /**
     * The name of the instance group
     *
     * @var string
     */
    public $name;

    /**
     * The project name of the group
     *
     * @var string
     */
    public $project;

    /**
     * The project id of the group
     *
     * @var string
     */
    public $projectid;

}