<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * OptionData
 *
 * @property \Scalr\Service\Aws\Rds\DataType\DBSecurityGroupMembershipList $dBSecurityGroupMemberships
 *           If the option requires access to a port, then this DB security group allows access to the port.
 *
 * @property \Scalr\Service\Aws\Rds\DataType\VpcSecurityGroupMembershipList $vpcSecurityGroupMemberships
 *           If the option requires access to a port, then this VPC security group allows access to the port.
 *
 * @property \Scalr\Service\Aws\Rds\DataType\OptionSettingList $optionSettings
 *           The option settings for this option.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class OptionData extends AbstractRdsDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['dBSecurityGroupMemberships', 'vpcSecurityGroupMemberships', 'optionSettings'];

    /**
     * The description of the option.
     *
     * @var string
     */
    public $optionDescription;

    /**
     * The name of the option.
     *
     * @var string
     */
    public $optionName;

    /**
     * Indicate if this option is permanent.
     *
     * @var bool
     */
    public $permanent;

    /**
     * Indicate if this option is persistent.
     *
     * @var bool
     */
    public $persistent;

    /**
     * If required, the port configured for this option to use.
     *
     * @var int
     */
    public $port;

}