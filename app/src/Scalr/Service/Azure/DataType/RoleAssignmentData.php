<?php

namespace Scalr\Service\Azure\DataType;

/**
 * RoleAssignmentData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\DataType\RoleAssignmentProperties  $properties
 *
 */
class RoleAssignmentData extends AbstractDataType
{
    /**
     * Default value of 'type' property
     */
    const PROPERTY_TYPE_VALUE = 'Microsoft.Authorization/roleAssignments';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the identifying URL of the role assignment.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the authorization type.
     *
     * @var string
     */
    public $type;

    /**
     * Specifies the name of the role assignment.
     *
     * @var string
     */
    public $name;

    /**
     * Constructor
     */
    public function __constructor()
    {
        $this->type = self::PROPERTY_TYPE_VALUE;
    }

    /**
     * Sets properties
     *
     * @param   array|RoleAssignmentProperties $properties
     * @return  RoleAssignmentData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof RoleAssignmentProperties)) {
            $properties = RoleAssignmentProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}