<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreateResourceExtension
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\ResourceExtensionProperties  $properties
 *
 */
class CreateResourceExtension extends AbstractDataType
{
    /**
     * Default value of 'type' property
     */
    const PROPERTY_TYPE_VALUE = 'Microsoft.Compute/virtualMachines/extensions';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the identifying URL of the virtual machine extension.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the name of the virtual machine extension.
     *
     * @var string
     */
    public $name;

    /**
     * Specifies the type of compute resource.
     *
     * @var string
     */
    public $type;

    /**
     * Specifies the supported Azure location where the resource exists.
     *
     * @var string
     */
    public $location;

    /**
     * Constructor
     *
     * @param   string  $name           Specifies the name of the virtual machine extension.
     * @param   string  $location       Specifies the supported Azure location where the resource exists
     * @param   array|ResourceExtensionProperties $properties Specifies properties
     */
    public function __construct($name, $location, $properties)
    {
        $this->name = $name;
        $this->location = $location;
        $this->type = self::PROPERTY_TYPE_VALUE;
        $this->setProperties($properties);
    }

    /**
     * Sets properties
     *
     * @param   array|ResourceExtensionProperties $properties
     * @return  CreateResourceExtension
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof ResourceExtensionProperties)) {
            $properties = ResourceExtensionProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }
}