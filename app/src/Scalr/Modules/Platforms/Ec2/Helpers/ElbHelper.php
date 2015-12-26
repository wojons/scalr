<?php

namespace Scalr\Modules\Platforms\Ec2\Helpers;

use Scalr\Model\Entity\CloudResource;
use Scalr\Model\Entity;
use \DBFarmRole;

class ElbHelper
{
    public static function farmValidateRoleSettings($settings, $rolename)
    {
    }

    public static function farmUpdateRoleSettings(DBFarmRole $DBFarmRole, $oldSettings, $newSettings)
    {
        //Convert OLD ELB settings into NEW ELB SETTINGS
        if (isset($newSettings[Entity\FarmRoleSetting::BALANCING_USE_ELB]) && $newSettings[Entity\FarmRoleSetting::BALANCING_USE_ELB] == 1 && empty($newSettings[Entity\FarmRoleSetting::AWS_ELB_ENABLED])) {
            $newSettings[Entity\FarmRoleSetting::AWS_ELB_ENABLED] = 1;
            $newSettings[Entity\FarmRoleSetting::AWS_ELB_ID] = $newSettings[Entity\FarmRoleSetting::BALANCING_NAME];
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::AWS_ELB_ENABLED, 1, Entity\FarmRoleSetting::TYPE_CFG);
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::AWS_ELB_ID, $newSettings[Entity\FarmRoleSetting::BALANCING_NAME], Entity\FarmRoleSetting::TYPE_LCL);
        }

        //NEW ELB:
        try {
            $DBFarm = $DBFarmRole->GetFarmObject();
            $elb = $DBFarm->GetEnvironmentObject()->aws($DBFarmRole)->elb;

            /*
             * aws.elb.enabled
             * aws.elb.id":"scalr-97f8a108ce4100-775",
             * aws.elb.remove
             */
            if (!empty($newSettings[Entity\FarmRoleSetting::AWS_ELB_ENABLED]) && !empty($newSettings[Entity\FarmRoleSetting::AWS_ELB_ID])) {
                if (!empty($oldSettings[Entity\FarmRoleSetting::AWS_ELB_ID]) && $oldSettings[Entity\FarmRoleSetting::AWS_ELB_ID] == $newSettings[Entity\FarmRoleSetting::AWS_ELB_ID])
                    return true;

                $service = CloudResource::findPk(
                    $newSettings[Entity\FarmRoleSetting::AWS_ELB_ID],
                    CloudResource::TYPE_AWS_ELB,
                    $DBFarm->EnvID,
                    \SERVER_PLATFORMS::EC2,
                    $DBFarmRole->CloudLocation
                );
                if (!$service) {
                    // Setup new service
                    // ADD ELB to role_cloud_services
                    $service = new CloudResource();
                    $service->id = $newSettings[Entity\FarmRoleSetting::AWS_ELB_ID];
                    $service->type = CloudResource::TYPE_AWS_ELB;
                    $service->platform = \SERVER_PLATFORMS::EC2;
                    $service->cloudLocation = $DBFarmRole->CloudLocation;
                    $service->envId = $DBFarm->EnvID;
                    $service->farmId = $DBFarmRole->FarmID;
                    $service->farmRoleId = $DBFarmRole->ID;
                } else {
                    if ($service->envId == $DBFarmRole->GetFarmObject()->EnvID) {
                        $service->farmRoleId = $DBFarmRole->ID;
                        $service->farmId = $DBFarmRole->FarmID;
                    } else {
                        $DBFarmRole->SetSetting(Entity\FarmRoleSetting::AWS_ELB_ID, $oldSettings[Entity\FarmRoleSetting::AWS_ELB_ID]);
                        throw new \Exception("ELB already used on another scalr account/environment");
                    }
                }
                $service->save();

                // Add running instances to ELB
                $servers = $DBFarmRole->GetServersByFilter(array('status' => \SERVER_STATUS::RUNNING));
                $newInstances = array();
                foreach ($servers as $DBServer) {
                    $newInstances[] = $DBServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID);
                }

                try {
                    if (count($newInstances) > 0)
                        $elb->loadBalancer->registerInstances($newSettings[Entity\FarmRoleSetting::AWS_ELB_ID], $newInstances);
                } catch (\Exception $e) {}

                try {
                    //Check and deregister old instances instances
                    $list = $elb->loadBalancer->describeInstanceHealth($newSettings[Entity\FarmRoleSetting::AWS_ELB_ID], array());
                    /* @var $instance \Scalr\Service\Aws\Elb\DataType\InstanceStateData */
                    $instances = array();
                    foreach ($list as $instance) {
                        if (!in_array($instance->instanceId, $newInstances))
                            array_push($instances, $instance->instanceId);
                    }

                    if (count($instances) > 0)
                        $elb->loadBalancer->deregisterInstances($newSettings[Entity\FarmRoleSetting::AWS_ELB_ID], $instances);
                } catch (\Exception $e) {}

            } else {
                $clearSettings = true;
            }


            // Remove OLD ELB
            if (!empty($oldSettings[Entity\FarmRoleSetting::AWS_ELB_ID])) {
                $oldService = CloudResource::findPk(
                    $oldSettings[Entity\FarmRoleSetting::AWS_ELB_ID],
                    CloudResource::TYPE_AWS_ELB,
                    $DBFarm->EnvID,
                    \SERVER_PLATFORMS::EC2,
                    $DBFarmRole->CloudLocation
                );
                if ($oldService && $oldService->farmRoleId == $DBFarmRole->ID)
                    $oldService->delete();

                if ($newSettings['aws.elb.remove']) {
                    $elb->loadBalancer->delete($oldSettings[Entity\FarmRoleSetting::AWS_ELB_ID]);
                }
            }

            if ($clearSettings) {
                $DBFarmRole->ClearSettings("aws.elb.");
            }

            // Check and remove OLD ELB settings
            if (!empty($newSettings['aws.elb.enabled']) && $DBFarmRole->GetSetting(Entity\FarmRoleSetting::BALANCING_HOSTNAME)) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::BALANCING_NAME, null, Entity\FarmRoleSetting::TYPE_LCL);
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::BALANCING_HOSTNAME, null, Entity\FarmRoleSetting::TYPE_LCL);
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::BALANCING_USE_ELB, null, Entity\FarmRoleSetting::TYPE_LCL);
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::BALANCING_HC_HASH, null, Entity\FarmRoleSetting::TYPE_LCL);
                $DBFarmRole->ClearSettings("lb.avail_zone");
                $DBFarmRole->ClearSettings("lb.healthcheck");
                $DBFarmRole->ClearSettings("lb.role.listener");
            }
        } catch (\Exception $e) {
            throw new \Exception("Error with ELB on Role '{$DBFarmRole->GetRoleObject()->name}': {$e->getMessage()}");
        }
    }
}
