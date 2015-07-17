<?php

namespace Scalr\Modules\Platforms\Ec2\Helpers;

use Scalr\Model\Entity\CloudResource;
use \DBFarmRole;

class ElbHelper
{
    public static function farmValidateRoleSettings($settings, $rolename)
    {
    }

    public static function farmUpdateRoleSettings(DBFarmRole $DBFarmRole, $oldSettings, $newSettings)
    {
        //Conver OLD ELB settings into NEW ELB SETTINGS
        if ($newSettings[DBFarmRole::SETTING_BALANCING_USE_ELB] == 1 && !$newSettings[DBFarmRole::SETTING_AWS_ELB_ENABLED]) {
            $newSettings[DBFarmRole::SETTING_AWS_ELB_ENABLED] = 1;
            $newSettings[DBFarmRole::SETTING_AWS_ELB_ID] = $newSettings[DBFarmRole::SETTING_BALANCING_NAME];
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_AWS_ELB_ENABLED, 1, DBFarmRole::TYPE_CFG);
            $DBFarmRole->SetSetting(DBFarmRole::SETTING_AWS_ELB_ID, $newSettings[DBFarmRole::SETTING_BALANCING_NAME], DBFarmRole::TYPE_LCL);
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
            if ($newSettings[DBFarmRole::SETTING_AWS_ELB_ENABLED] && $newSettings[DBFarmRole::SETTING_AWS_ELB_ID]) {
                if ($oldSettings[DBFarmRole::SETTING_AWS_ELB_ID] == $newSettings[DBFarmRole::SETTING_AWS_ELB_ID])
                    return true;

                $service = CloudResource::findPk(
                    $newSettings[DBFarmRole::SETTING_AWS_ELB_ID],
                    $DBFarm->EnvID,
                    \SERVER_PLATFORMS::EC2,
                    $DBFarmRole->CloudLocation
                );
                if (!$service) {
                    // Setup new service
                    // ADD ELB to role_cloud_services
                    $service = new CloudResource();
                    $service->id = $newSettings[DBFarmRole::SETTING_AWS_ELB_ID];
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
                        $DBFarmRole->SetSetting(DBFarmRole::SETTING_AWS_ELB_ID, $oldSettings[DBFarmRole::SETTING_AWS_ELB_ID]);
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
                        $elb->loadBalancer->registerInstances($newSettings[DBFarmRole::SETTING_AWS_ELB_ID], $newInstances);
                } catch (\Exception $e) {}

                try {
                    //Check and deregister old instances instances
                    $list = $elb->loadBalancer->describeInstanceHealth($newSettings[DBFarmRole::SETTING_AWS_ELB_ID], array());
                    /* @var $instance \Scalr\Service\Aws\Elb\DataType\InstanceStateData */
                    $instances = array();
                    foreach ($list as $instance) {
                        if (!in_array($instance->instanceId, $newInstances))
                            array_push($instances, $instance->instanceId);
                    }

                    if (count($instances) > 0)
                        $elb->loadBalancer->deregisterInstances($newSettings[DBFarmRole::SETTING_AWS_ELB_ID], $instances);
                } catch (\Exception $e) {}

            } else {
                $clearSettings = true;
            }


            // Remove OLD ELB
            if ($oldSettings[DBFarmRole::SETTING_AWS_ELB_ID]) {
                $oldService = CloudResource::findPk(
                    $oldSettings[DBFarmRole::SETTING_AWS_ELB_ID],
                    $DBFarm->EnvID,
                    \SERVER_PLATFORMS::EC2,
                    $DBFarmRole->CloudLocation
                );
                if ($oldService && $oldService->farmRoleId == $DBFarmRole->ID)
                    $oldService->delete();

                if ($newSettings['aws.elb.remove']) {
                    $elb->loadBalancer->delete($oldSettings[DBFarmRole::SETTING_AWS_ELB_ID]);
                }
            }

            if ($clearSettings) {
                $DBFarmRole->ClearSettings("aws.elb.");
            }

            // Check and remove OLD ELB settings
            if ($newSettings['aws.elb.enabled'] && $DBFarmRole->GetSetting(DBFarmRole::SETTING_BALANCING_HOSTNAME)) {
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_BALANCING_NAME, null, DBFarmRole::TYPE_LCL);
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_BALANCING_HOSTNAME, null, DBFarmRole::TYPE_LCL);
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_BALANCING_USE_ELB, null, DBFarmRole::TYPE_LCL);
                $DBFarmRole->SetSetting(DBFarmRole::SETTING_BALANCING_HC_HASH, null, DBFarmRole::TYPE_LCL);
                $DBFarmRole->ClearSettings("lb.avail_zone");
                $DBFarmRole->ClearSettings("lb.healthcheck");
                $DBFarmRole->ClearSettings("lb.role.listener");
            }
        } catch (\Exception $e) {
            throw new \Exception("Error with ELB on Role '{$DBFarmRole->GetRoleObject()->name}': {$e->getMessage()}");
        }
    }
}
