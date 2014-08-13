<?php

namespace Scalr\Modules\Platforms\Nimbula\Adapters;

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
        return $this->platformStatus == 'running' ? true : false;
    }

    public function isPending()
    {
        return $this->platformStatus == 'queued' || $this->platformStatus == 'starting' || $this->platformStatus == 'initializing' ? true : false;
    }

    public function isTerminated()
    {
        return $this->platformStatus == 'terminating' || $this->platformStatus == 'not-found'  ? true : false;
    }

    public function isSuspended()
    {
        //
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