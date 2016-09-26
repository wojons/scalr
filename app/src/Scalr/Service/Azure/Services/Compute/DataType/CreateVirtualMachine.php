<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreateVirtualMachine
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\VirtualMachineProperties  $properties
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\PlanProperties  $plan
 *            Required for marketplace images
 *
 */
class CreateVirtualMachine extends AbstractDataType
{
    /**
     * Default value of 'type' property
     */
    const PROPERTY_TYPE_VALUE = 'Microsoft.Compute/virtualMachines';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties', 'plan'];

    /**
     * Specifies the identifying URL of the virtual machine.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the name of the virtual machine.
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
     * Specifies the supported Azure location where the resource exists
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
     * @param   string  $name       Specifies the name of the virtual machine.
     * @param   string  $location   Specifies the supported Azure location where the resource exists.
     * @param   array|VirtualMachineProperties  $properties     Specifies properties
     */
    public function __construct($name, $location, $properties)
    {
        $this->name = $name;
        $this->type = self::PROPERTY_TYPE_VALUE;
        $this->location = $location;
        $this->setProperties($properties);
    }

    /**
     * Sets properties
     *
     * @param   array|VirtualMachineProperties $properties
     * @return  CreateVirtualMachine
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof VirtualMachineProperties)) {
            $properties = VirtualMachineProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

    /**
     * Sets plan
     *
     * @param   array|PlanProperties $plan Required for marketplace images
     * @return  CreateVirtualMachine
     */
    public function setPlan($plan = null)
    {
        if (!($plan instanceof PlanProperties)) {
            $plan = PlanProperties::initArray($plan);
        }

        return $this->__call(__FUNCTION__, [$plan]);
    }

}