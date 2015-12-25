<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreateInterface
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\InterfaceProperties  $properties
 *
 */
class CreateInterface extends AbstractDataType
{
    /**
     * Default value of 'type' property
     */
    const PROPERTY_TYPE_VALUE = "Microsoft.Network/networkInterfaces";

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the supported Azure location of the NIC.
     *
     * @var string
     */
    public $location;

    /**
     * Type
     *
     * @var string
     */
    public $type;

    /**
     * Tags array. ['key' => 'value']
     *
     * @var array
     */
    public $tags;

    /**
     * Constructor
     *
     * @param   string                    $location         Specifies the supported Azure location of the NIC.
     * @param   array|InterfaceProperties $properties       Specifies properties
     */
    public function __construct($location, $properties)
    {
        $this->location = $location;
        $this->setProperties($properties);
        $this->type = self::PROPERTY_TYPE_VALUE;
    }

    /**
     * Sets properties
     *
     * @param   array|InterfaceProperties $properties
     * @return  CreateInterface
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof InterfaceProperties)) {
            $properties = InterfaceProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}