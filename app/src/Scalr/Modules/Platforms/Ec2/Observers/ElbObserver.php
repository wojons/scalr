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

    private function registerInstanceOnLB(DBServer $dbServer)
    {
        try {
            $DBFarmRole = $dbServer->GetFarmRoleObject();
        
            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ENABLED)) {
                $elbId = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ID);
        
                $elb = $dbServer->GetEnvironmentObject()->aws($dbServer)->elb;
                
                $elb->loadBalancer->registerInstances(
                    $elbId,
                    $dbServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID)
                );
                
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new \FarmLogMessage(
                    $dbServer->farmId,
                    sprintf(_("Instance '%s' registered on '%s' load balancer"),
                        $dbServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID),
                        $elbId
                    ),
                    $dbServer->serverId,
                    $dbServer->envId,
                    $dbServer->farmRoleId
                ));
            }
        } catch(\Exception $e) {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new \FarmLogMessage(
                $dbServer->farmId,
                sprintf(_("Cannot register instance on the load balancer: %s"), $e->getMessage()),
                $dbServer->serverId,
                $dbServer->envId,
                $dbServer->farmRoleId
            ));
        }
    }
    
    private function deregisterInstanceFromLB(DBServer $dbServer)
    {
        try {
            $DBFarmRole = $dbServer->GetFarmRoleObject();

            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ENABLED)) {
                $elbId = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ID);
                
                $elb = $dbServer->GetEnvironmentObject()->aws($dbServer)->elb;
                
                $elb->loadBalancer->deregisterInstances(
                    $elbId,
                    $dbServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID)
                );
                
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new \FarmLogMessage(
                    $dbServer->farmId,
                    sprintf(_("Instance '%s' deregistered from '%s' load balancer"), $dbServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID), $elbId),
                    $dbServer->serverId,
                    $dbServer->envId,
                    $dbServer->farmRoleId
                ));
            }
        } catch(\Exception $e) {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new \FarmLogMessage(
                $dbServer->farmId,
                sprintf(_("Cannot deregister instance from the load balancer: %s"), $e->getMessage()),
                $dbServer->serverId,
                $dbServer->envId,
                $dbServer->farmRoleId
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

        $this->deregisterInstanceFromLB($event->DBServer);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnBeforeHostTerminate()
     */
    public function OnBeforeHostTerminate(\BeforeHostTerminateEvent $event)
    {
        $this->deregisterInstanceFromLB($event->DBServer);
    }
    
    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnResumeComplete()
     */
    public function OnResumeComplete(\ResumeCompleteEvent $event) 
    {
        $this->registerInstanceOnLB($event->DBServer);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostUp()
     */
    public function OnHostUp(\HostUpEvent $event)
    {
        $this->registerInstanceOnLB($event->DBServer);
    }

    public function OnHostInit(\HostInitEvent $event)
    {
        //
    }
}
