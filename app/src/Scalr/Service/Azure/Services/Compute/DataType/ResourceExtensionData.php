<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

/**
 * ResourceExtensionData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class ResourceExtensionData extends CreateResourceExtension
{
    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Sets properties
     *
     * @param   array|ResourceExtensionProperties $properties
     * @return  ResourceExtensionData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof ResourceExtensionProperties)) {
            $properties = ResourceExtensionProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }
}