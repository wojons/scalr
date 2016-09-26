<?php

namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\ElbException;
use Scalr\Service\Aws\Elb\AbstractElbDataType;

/**
 * ConnectionDraining
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class ConnectionDrainingData extends AbstractElbDataType
{
    /**
     * Specifies whether connection draining is enabled for the load balancer.
     *
     * @var bool
     */
    public $enabled;

    /**
     * The maximum time, in seconds, to keep the existing connections open before deregistering the instances.
     *
     * @var int
     */
    public $timeout;

    /**
     * Constructor
     *
     * @param   bool  $enabled    Specifies whether connection draining is enabled for the load balancer.
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