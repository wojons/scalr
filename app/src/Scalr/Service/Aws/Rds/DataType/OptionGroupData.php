<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * OptionGroupData
 *
 * @property \Scalr\Service\Aws\Rds\DataType\OptionList $options
 *           Indicates what options are available in the option group.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class OptionGroupData extends AbstractRdsDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['options'];

    /**
     * Indicates whether this option group can be applied to both VPC and non-VPC instances.
     * The value true indicates the option group can be applied to both VPC and non-VPC instances.
     *
     * @var bool
     */
    public $allowsVpcAndNonVpcInstanceMemberships;

    /**
     * Engine name that this option group can be applied to.
     *
     * @var string
     */
    public $engineName;

    /**
     * Indicates the major engine version associated with this option group.
     *
     * @var string
     */
    public $majorEngineVersion;

    /**
     * Provides a description of the option group.
     *
     * @var string
     */
    public $optionGroupDescription;

    /**
     * Specifies the name of the option group.
     *
     * @var string
     */
    public $optionGroupName;

    /**
     * If AllowsVpcAndNonVpcInstanceMemberships is false, this field is blank.
     * If AllowsVpcAndNonVpcInstanceMemberships is true and this field is blank, then this option group can be applied to both VPC and non-VPC instances.
     * If this field contains a value, then this option group can only be applied to instances that are in the VPC indicated by this field.
     *
     * @var string
     */
    public $vpcId;

}