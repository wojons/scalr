<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreateVirtualNetwork
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\VirtualNetworkProperties  $properties
 *
 */
class CreateVirtualNetwork extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies virtual network's name.
     *
     * @var string
     */
    public $name;

    /**
     * Specifies virtual network's id.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the supported Azure location of the virtual network.
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
     *
     * @var string
     */
    public $etag;

    /**
     * Constructor
     *
     * @param   string                         $name             Specifies the name of the virtual network.
     * @param   string                         $location         Specifies the supported Azure location of the virtual network.
     * @param   array|VirtualNetworkProperties $properties       Specifies properties
     */
    public function __construct($name, $location, $properties)
    {
        $this->name = $name;
        $this->location = $location;
        $this->setProperties($properties);
    }

    /**
     * Sets properties
     *
     * @param   array|VirtualNetworkProperties $properties
     * @return  CreateVirtualNetwork
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof VirtualNetworkProperties)) {
            $properties = VirtualNetworkProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}