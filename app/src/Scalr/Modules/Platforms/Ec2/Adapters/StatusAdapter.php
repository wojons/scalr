<?php

namespace Scalr\Modules\Platforms\Ec2\Adapters;

class StatusAdapter implements \Scalr\Modules\Platforms\StatusAdapterInterface
{
    private $platformStatus;

    public static function load($status)
    {
        $clss = get_called_class();
        return new $clss($status);
    }

    public function __construct($status)
    {
        $this->platformStatus = $status;
    }

    public function getName()
    {
        return $this->platformStatus;
    }

    public function isRunning()
    {
        return $this->platformStatus == 'running' ? true : false;
    }

    public function isPending()
    {
        return $this->platformStatus == 'pending' ? true : false;
    }

    public function isTerminated()
    {
        return $this->platformStatus == 'terminated' || $this->platformStatus == 'not-found' || $this->platformStatus == 'shutting-down'  ? true : false;
    }

    public function isSuspended()
    {
        return $this->platformStatus == 'stopped' || $this->platformStatus == 'stopping' ? true : false;
    }

    public function isPendingSuspend()
    {
        //return $this->platformStatus == 'stopping' ? true : false;
    }

    public function isPendingRestore()
    {
        //
    }
}