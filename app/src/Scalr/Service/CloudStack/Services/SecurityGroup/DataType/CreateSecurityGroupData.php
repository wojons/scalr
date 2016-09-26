<?php
namespace Scalr\Service\CloudStack\Services\SecurityGroup\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * CreateSecurityGroupData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class CreateSecurityGroupData extends AbstractDataType
{

    /**
     * Required
     * Name of the security group
     *
     * @var string
     */
    public $name;

    /**
     * An optional account for the security group.
     * Must be used with domainId.
     *
     * @var string
     */
    public $account;

    /**
     * An optional domainId for the security group.
     * If the account parameter is used, domainId must also be used.
     *
     * @var string
     */
    public $domainid;

    /**
     * The description of the security group
     *
     * @var string
     */
    public $description;

    /**
     * Create security group for project
     *
     * @var string
     */
    public $projectid;

    /**
     * Constructor
     *
     * @param   string  $name    Name of the security group
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

}
