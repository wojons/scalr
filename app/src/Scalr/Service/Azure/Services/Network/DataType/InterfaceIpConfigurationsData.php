<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * InterfaceIpConfigurations
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 * @property  \Scalr\Service\Azure\Services\Network\DataType\IpConfigurationProperties  $properties
 *
 */
class InterfaceIpConfigurationsData extends AbstractDataType
{
    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = ['properties'];

    /**
     * User-defined name of the IP.
     *
     * @var string
     */
    public $name;

    /**
     * The ID of the IP Configuration
     *
     * @var string
     */
    public $id;

    /**
     * System generated meta-data enabling concurrency control
     *
     * @var string
     */
    public $etag;

    /**
     * Constructor
     *
     * @param   string                          $name       User-defined name of the IP.
     * @param   array|IpConfigurationProperties $properties Specifies properties
     */
    public function __construct($name, $properties)
    {
        $this->name = $name;
        $this->setProperties($properties);
    }

    /**
     * Sets properties
     *
     * @param   array|IpConfigurationProperties $properties
     * @return  InterfaceIpConfigurationsData
     */
    public function setProperties($properties = null)
    {
        if (!empty($properties) && !($properties instanceof IpConfigurationProperties)) {
            $properties = IpConfigurationProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}