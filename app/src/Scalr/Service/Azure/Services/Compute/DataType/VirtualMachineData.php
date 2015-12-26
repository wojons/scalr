<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

/**
 * VirtualMachineData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class VirtualMachineData extends CreateVirtualMachine
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
     * @param   array|VirtualMachineProperties $properties
     * @return  VirtualMachineData
     */
    public function setProperties($properties = null)
    {
        if (!($properties instanceof VirtualMachineProperties)) {
            $properties = VirtualMachineProperties::initArray($properties);
        }

        return $this->__call(__FUNCTION__, [$properties]);
    }

    /**
     * Sets plan
     *
     * @param   array|PlanProperties $plan Required for marketplace images
     * @return  VirtualMachineData
     */
    public function setPlan($plan = null)
    {
        if (!($plan instanceof PlanProperties)) {
            $plan = PlanProperties::initArray($plan);
        }

        return $this->__call(__FUNCTION__, [$plan]);
    }

}