<?php

namespace Scalr\Service\Azure\Services\ResourceManager\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * ResourceGroupData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\ResourceManager\DataType\ResourceGroupProperties  $properties
 *
 */
class ResourceGroupData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the identifying URL of the resource group.
     *
     * @var string
     */
    public $id;

    /**
     * Specifies the name of the resource group.
     *
     * @var string
     */
    public $name;

    /**
     * The location of the resource. This will be one of the supported and registered Azure Geo Regions (e.g. West US, East US, Southeast Asia, etc.).
     * The geo region of a resource cannot be changed once it is created; therefore, this location parameter also cannot be changed.
     *
     * @var string
     */
    public $location;

    /**
     * Specifies the tags and their values that are used by the resource group.
     * This element is not returned if tags were not included in the request.
     * Tags array. ['key' => 'value']
     *
     * @var array
     */
    public $tags;

    /**
     * Sets properties
     *
     * @param   array|ResourceGroupProperties $properties
     * @return  ResourceGroupData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof ResourceGroupProperties)) {
            $properties = ResourceGroupProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}