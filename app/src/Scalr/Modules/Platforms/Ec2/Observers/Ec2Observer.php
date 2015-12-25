<?php

namespace Scalr\Modules\Platforms\Ec2\Observers;
use Scalr\Modules\Platforms\Ec2\Helpers\Ec2Helper;
use Scalr\Observer\AbstractEventObserver;

class Ec2Observer extends AbstractEventObserver
{

    public $ObserverName = 'EC2';

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Creates tags on the instance and it's root device
     *
     * @param \HostInitEvent $event
     */
    public function OnHostInit(\HostInitEvent $event)
    {
        if ($event->DBServer->platform != \SERVER_PLATFORMS::EC2) {
            return;
        }

        Ec2Helper::createObjectTags($event->DBServer);
    }
}
