<?php
namespace Scalr\Service\CloudStack\Services\SecurityGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * DeleteSecurityGroupData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class DeleteSecurityGroupData extends AbstractDataType
{

    /**
     * The name of the security group.
     * Mutually exclusive with id parameter
     *
     * @var string
     */
    public $name;

    /**
     * The account of the security group.
     * Must be specified with domain ID
     *
     * @var string
     */
    public $account;

    /**
     * The domain ID of account owning the security group
     *
     * @var string
     */
    public $domainid;

    /**
     * The ID of the security group.
     * Mutually exclusive with name parameter
     *
     * @var string
     */
    public $id;

    /**
     * Create security group for project
     *
     * @var string
     */
    public $projectid;

}