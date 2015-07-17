<?php

namespace Scalr\Modules\Platforms\Ec2\Observers;
use Scalr\Modules\Platforms\Ec2\Helpers\Ec2Helper;

class Ec2Observer extends \EventObserver
{

    public $ObserverName = 'EC2';

    function __construct()
    {
        parent::__construct();
    }

    public function OnHostInit(\HostInitEvent $event) {

        if ($event->DBServer->platform != \SERVER_PLATFORMS::EC2) {
            return;
        }

        Ec2Helper::createServerTags($event->DBServer);
    }
}
