<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * AvailabilitySetProperties
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class AvailabilitySetProperties extends AbstractDataType
{
    /**
     * Platform Update Domain Count
     *
     * @var int
     */
    public $platformUpdateDomainCount;

    /**
     * Platform Fault Domain Count
     *
     * @var int
     */
    public $platformFaultDomainCount;

}