<?php

namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\ElbException;
use Scalr\Service\Aws\Elb\AbstractElbDataType;

/**
 * CrossZoneLoadBalancing
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class CrossZoneLoadBalancingData extends AbstractElbDataType
{
    /**
     * Specifies whether cross-zone load balancing is enabled for the load balancer.
     *
     * @var bool
     */
    public $enabled;

    /**
     * Constructor
     *
     * @param   bool  $enabled    Specifies whether cross-zone load balancing is enabled for the load balancer.
     */
    public function __construct($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Elb.AbstractElbDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        if ($this->enabled === null) {
            throw new ElbException(get_class($this) . ' has not been initialized with properties values yet.');
        }
    }

}