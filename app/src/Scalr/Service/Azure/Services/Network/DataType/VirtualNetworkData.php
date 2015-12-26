<?php

namespace Scalr\Service\Azure\Services\Network\DataType;

/**
 * VirtualNetworkData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class VirtualNetworkData extends CreateVirtualNetwork
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
     * @param   array|VirtualNetworkProperties $properties
     * @return  VirtualNetworkData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof VirtualNetworkProperties)) {
            $properties = VirtualNetworkProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }
}