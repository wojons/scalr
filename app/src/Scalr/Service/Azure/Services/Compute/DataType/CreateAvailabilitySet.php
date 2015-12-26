<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreateAvailabilitySet
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Compute\DataType\AvailabilitySetProperties  $properties
 *
 */
class CreateAvailabilitySet extends AbstractDataType
{
    /**
     * Default value of 'type' property
     */
    const PROPERTY_TYPE_VALUE = 'Microsoft.Compute/availabilitySets';

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the identifying URL of the availability set.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the name of the availability set.
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
     * Tags array. ['key' => 'value']
     *
     * @var array
     */
    public $tags;

    /**
     * Constructor
     *
     * @param   string  $name           Specifies the name of the availability set.
     * @param   string  $location       Specifies the supported Azure location where the resource exists
     */
    public function __construct($name, $location)
    {
        $this->name = $name;
        $this->location = $location;
        $this->type = self::PROPERTY_TYPE_VALUE;
    }

    /**
     * Sets properties
     *
     * @param   array|AvailabilitySetProperties $properties
     * @return  CreateAvailabilitySet
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof AvailabilitySetProperties)) {
            $properties = AvailabilitySetProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}