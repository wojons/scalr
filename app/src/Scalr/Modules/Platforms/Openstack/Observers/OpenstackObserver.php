<?php

namespace Scalr\Modules\Platforms\Openstack\Observers;

use Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper;

class OpenstackObserver extends \EventObserver
{
    public $ObserverName = 'Openstack';

    public function OnBeforeHostTerminate(\BeforeHostTerminateEvent $event)
    {
        if (!$event->DBServer->isOpenstack())
            return;

        //DO NOT REMOVE FLOATING IP AT THIS POINT. MESSAGES WON'T BE DELIVERED
    }

    public function OnHostDown(\HostDownEvent $event)
    {
        if (!$event->DBServer->isOpenstack())
            return;

        OpenstackHelper::removeIpFromServer($event->DBServer);
    }
}
