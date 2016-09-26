<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreateSecurityGroup
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.9
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\SecurityGroupProperties  $properties
 *
 */
class CreateSecurityGroup extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the supported Azure location of the Network Security Group.
     *
     * @var string
     */
    public $location;

    /**
     * Tags array. ['key' => 'value']
     *
     * @var array
     */
    public $tags;

    /**
     * Constructor
     *
     * @param   string   $location         Specifies the supported Azure location of the Network Security Group.
     */
    public function __construct($location)
    {
        $this->location = $location;
    }

    /**
     * Sets properties
     *
     * @param   array|SecurityGroupProperties $properties
     * @return  CreateSecurityGroup
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof SecurityGroupProperties) && !empty($properties)) {
            $properties = SecurityGroupProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}