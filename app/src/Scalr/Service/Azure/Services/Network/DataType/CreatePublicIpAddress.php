<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * CreatePublicIpAddress
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\PublicIpAddressProperties  $properties
 *
 */
class CreatePublicIpAddress extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * Specifies the supported Azure location of the Public IP Address.
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
     * @param   string                          $location         Specifies the supported Azure location of the Public IP Address.
     * @param   array|PublicIpAddressProperties $properties       Specifies properties
     */
    public function __construct($location, $properties)
    {
        $this->location = $location;
        $this->setProperties($properties);
    }

    /**
     * Sets properties
     *
     * @param   array|PublicIpAddressProperties $properties
     * @return  CreatePublicIpAddress
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof PublicIpAddressProperties)) {
            $properties = PublicIpAddressProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}