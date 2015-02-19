<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * OptionSettingData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     19.01.2015
 */
class OptionSettingData extends AbstractRdsDataType
{

    /**
     * The allowed values of the option setting.
     *
     * @var string
     */
    public $allowedValues;

    /**
     * The DB engine specific parameter type.
     *
     * @var string
     */
    public $applyType;

    /**
     * The data type of the option setting.
     *
     * @var string
     */
    public $dataType;

    /**
     * The default value of the option setting.
     *
     * @var string
     */
    public $defaultValue;

    /**
     * The description of the option setting.
     *
     * @var string
     */
    public $description;

    /**
     * Indicates if the option setting is part of a collection.
     *
     * @var bool
     */
    public $isCollection;

    /**
     * A Boolean value that, when true, indicates the option setting can be modified from the default.
     *
     * @var bool
     */
    public $isModifiable;

    /**
     * The name of the option that has settings that you can set.
     *
     * @var string
     */
    public $name;

    /**
     * The current value of the option setting.
     *
     * @var string
     */
    public $value;

}