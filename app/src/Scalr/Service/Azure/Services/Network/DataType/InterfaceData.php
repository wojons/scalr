<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

/**
 * InterfaceData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class InterfaceData extends CreateInterface
{
    /**
     * The identifying URL of the Network Interface Card.
     *
     * @var string
     */
    public $id;

    /**
     * The name of the Network Interface Card.
     *
     * @var string
     */
    public $name;

    /**
     * System generated meta-data enabling concurrency control
     *
     * @var string
     */
    public $etag;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Sets properties
     *
     * @param   array|InterfaceProperties $properties
     * @return  InterfaceData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof InterfaceProperties)) {
            $properties = InterfaceProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}