<?php

namespace Scalr\Service\Aws\Elb\DataType;

use Scalr\Service\Aws\ElbException;
use Scalr\Service\Aws\Elb\AbstractElbDataType;

/**
 * ConnectionSettings
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.9
 */
class ConnectionSettingsData extends AbstractElbDataType
{
    /**
     * The time, in seconds, that the connection is allowed to be idle (no data has been sent over the connection) before it is closed by the load balancer.
     *
     * @var int
     */
    public $idleTimeout;

    /**
     * Constructor
     *
     * @param   int  $idleTimeout    The time, in seconds, that the connection is allowed to be idle (no data has been sent over the connection) before it is closed by the load balancer.
     */
    public function __construct($idleTimeout)
    {
        $this->idleTimeout = $idleTimeout;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws\Elb.AbstractElbDataType::throwExceptionIfNotInitialized()
     */
    protected function throwExceptionIfNotInitialized()
    {
        if ($this->idleTimeout === null) {
            throw new ElbException(get_class($this) . ' has not been initialized with properties values yet.');
        }
    }

}