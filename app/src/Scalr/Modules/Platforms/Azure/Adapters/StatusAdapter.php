<?php

namespace Scalr\Modules\Platforms\Azure\Adapters;

class StatusAdapter implements \Scalr\Modules\Platforms\StatusAdapterInterface
{
    /**
     * Server status in cloud
     * ['ProvisioningState' => '..', 'PowerState' => '..']
     * @var array $platformStatus;
     */
    private $platformStatus = [];

    public static function load(array $status)
    {
        $class = get_called_class();
        return new $class($status);
    }

    public function __construct($status)
    {
        $this->platformStatus = $status;
    }

    public function getName()
    {
        return $this->platformStatus['ProvisioningState'] . "/" . $this->platformStatus['PowerState'];
    }

    public function isRunning()
    {
        return $this->platformStatus['PowerState'] == 'running' ? true : false;
    }

    public function isPending()
    {
        return $this->platformStatus['ProvisioningState'] == 'creating' || $this->platformStatus['PowerState'] == 'starting'  ? true : false;
    }

    public function isTerminated()
    {
        return $this->platformStatus['ProvisioningState'] == 'deleting' || $this->platformStatus['ProvisioningState'] == 'not-found' ? true : false;
    }

    public function isSuspended()
    {
        return in_array($this->platformStatus['PowerState'], array('stopping', 'stopped', 'deallocating', 'deallocated')) ? true : false;
    }

    public function isPendingSuspend()
    {
        //
    }

    public function isPendingRestore()
    {
        //
    }
}