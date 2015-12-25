<?php

namespace Scalr\Modules\Platforms\Ec2\Observers;

use Scalr\Model\Entity;
use DBServer;
use DBFarmRole;
use Scalr\Observer\AbstractEventObserver;

class ElbObserver extends AbstractEventObserver
{
    public $ObserverName = 'Elastic Load Balancing';

    function __construct()
    {
        parent::__construct();
    }

    private function DeregisterInstanceFromLB(DBServer $DBServer)
    {
        try {
            $DBFarmRole = $DBServer->GetFarmRoleObject();

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ENABLED)) {
                $useElb = true;
                $elbId = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ID);
            }

            if ($useElb) {
                $Client = $DBServer->GetClient();
                $elb = $DBServer->GetEnvironmentObject()->aws($DBServer)->elb;
                $elb->loadBalancer->deregisterInstances(
                    $elbId,
                    $DBServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID)
                );
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new \FarmLogMessage($this->FarmID,
                    sprintf(_("Instance '%s' deregistered from '%s' load balancer"),
                        $DBServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID),
                        $elbId
                    ),
                    $DBServer->serverId
                ));
            }
        } catch(\Exception $e) {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new \FarmLogMessage($this->FarmID,
                sprintf(_("Cannot deregister instance from the load balancer: %s"), $e->getMessage()),
                $DBServer->serverId
            ));
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostDown()
     */
    public function OnHostDown(\HostDownEvent $event)
    {
        if ($event->DBServer->IsRebooting())
            return;

        $this->DeregisterInstanceFromLB($event->DBServer);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnBeforeHostTerminate()
     */
    public function OnBeforeHostTerminate(\BeforeHostTerminateEvent $event)
    {
        $this->DeregisterInstanceFromLB($event->DBServer);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostUp()
     */
    public function OnHostUp(\HostUpEvent $event)
    {
        try {
            $DBFarmRole = $event->DBServer->GetFarmRoleObject();

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::BALANCING_USE_ELB)) {
                $useElb = true;
                $elbId = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::BALANCING_NAME);
            }

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ENABLED)) {
                $useElb = true;
                $elbId = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ID);
            }

            if ($useElb) {
                $Client = $event->DBServer->GetClient();
                $elb = $event->DBServer->GetEnvironmentObject()->aws($event->DBServer)->elb;
                $elb->loadBalancer->registerInstances(
                    $elbId,
                    $event->DBServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID)
                );
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new \FarmLogMessage($this->FarmID,
                    sprintf(_("Instance '%s' registered on '%s' load balancer"),
                        $event->DBServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID),
                        $elbId
                    ),
                    $event->DBServer->serverId
                ));
            }
        } catch(\Exception $e) {
            //TODO:
            $this->Logger->fatal(sprintf(_("Cannot register instance with the load balancer: %s"), $e->getMessage()));
        }
    }

    public function OnHostInit(\HostInitEvent $event)
    {
        //
    }
}
