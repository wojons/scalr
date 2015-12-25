<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

/**
 * PublicIpAddressData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class PublicIpAddressData extends CreatePublicIpAddress
{
    /**
     * The identifying URL of the Public IP Address.
     *
     * @var string
     */
    public $id;

    /**
     * The name of the Public IP Address.
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
     * @param   array|PublicIpAddressProperties $properties
     * @return  PublicIpAddressData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof PublicIpAddressProperties)) {
            $properties = PublicIpAddressProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}