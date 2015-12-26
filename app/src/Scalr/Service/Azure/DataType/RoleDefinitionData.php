<?php

namespace Scalr\Service\Azure\DataType;

/**
 * RoleDefinitionData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\DataType\RoleDefinitionProperties  $properties
 *
 */
class RoleDefinitionData extends AbstractDataType
{
    /**
     * Default value of 'type' property
     */
    const PROPERTY_TYPE_VALUE = 'Microsoft.Authorization/roleDefinitions';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the identifying URL of the role definition.
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
     * @param   array|RoleDefinitionProperties $properties
     * @return  RoleDefinitionData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof RoleDefinitionProperties)) {
            $properties = RoleDefinitionProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}