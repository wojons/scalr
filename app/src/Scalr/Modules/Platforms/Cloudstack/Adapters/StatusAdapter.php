<?php

namespace Scalr\Modules\Platforms\Cloudstack\Adapters;

class StatusAdapter implements \Scalr\Modules\Platforms\StatusAdapterInterface
{
    private $platformStatus;

    public static function load($status)
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
        return $this->platformStatus;
    }

    public function isRunning()
    {
        return $this->platformStatus == 'Running' ? true : false;
    }

    public function isPending()
    {
        return $this->platformStatus == 'Starting' ? true : false;
    }

    public function isTerminated()
    {
        return $this->platformStatus == 'Error' || $this->platformStatus == 'Destroyed' || $this->platformStatus == 'not-found' ? true : false;
    }

    public function isSuspended()
    {
        return $this->platformStatus == 'Stopping' || $this->platformStatus == 'Stopped' ? true : false;
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