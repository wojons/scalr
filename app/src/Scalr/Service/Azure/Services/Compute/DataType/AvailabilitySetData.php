<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

/**
 * AvailabilitySetData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class AvailabilitySetData extends CreateAvailabilitySet
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
     * @param   array|AvailabilitySetProperties $properties
     * @return  AvailabilitySetData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof AvailabilitySetProperties)) {
            $properties = AvailabilitySetProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

}