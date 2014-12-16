<?php

namespace Scalr\Modules\Platforms\Ec2\Observers;

use Scalr\Service\Aws\Ec2\DataType\VolumeFilterNameType;

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

        try {
            $dbServer = $event->DBServer;
            $aws = $dbServer->GetEnvironmentObject()->aws($dbServer);
            
            if ($dbServer->farmId != 0) {
                $ind = $aws->ec2->instance->describe($dbServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID))
                    ->get(0)->instancesSet->get(0);

                $nameFormat = $dbServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_AWS_INSTANCE_NAME_FORMAT);
                if (!$nameFormat)
                    $nameFormat = "{SCALR_FARM_NAME} -> {SCALR_ROLE_NAME} #{SCALR_INSTANCE_INDEX}";
                $instanceName = $event->DBServer->applyGlobalVarsToValue($nameFormat);

                $tags = array(
                    array('key' => "scalr-env-id", 'value' => $dbServer->envId),
                    array('key' => "scalr-owner", 'value' => $dbServer->GetFarmObject()->createdByUserEmail),
                    array('key' => "scalr-farm-id", 'value' => $dbServer->farmId),
                    array('key' => "scalr-farm-role-id", 'value' => $dbServer->farmRoleId),
                    array('key' => "scalr-server-id", 'value' => $dbServer->serverId),
                    array('key' => "Name", 'value' => $instanceName)
                );

                //Tags governance
                $governance = new \Scalr_Governance($dbServer->envId);
                $gTags = (array)$governance->getValue('ec2', 'aws.tags');
                if (count($gTags) > 0) {
                    foreach ($gTags as $tKey => $tValue) {
                        $tags[] = array('key' => $tKey, 'value' => $dbServer->applyGlobalVarsToValue($tValue));
                    }
                } else {
                    //Custom tags
                    $cTags = $dbServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_AWS_TAGS_LIST);
                    $tagsList = @explode("\n", $cTags);
                    foreach ((array)$tagsList as $tag) {
                        $tag = trim($tag);
                        if ($tag && count($tags) < 10) {
                            $tagChunks = explode("=", $tag);
                            $tags[] = array('key' => trim($tagChunks[0]), 'value' => $dbServer->applyGlobalVarsToValue(trim($tagChunks[1])));
                        }
                    }
                }
                
                $res = $ind->createTags($tags);
                
                // We also need to tag root device
                if ($ind->rootDeviceType == 'ebs') {
                    $filter = array(array(
                        'name'  => VolumeFilterNameType::attachmentInstanceId(),
                        'value' => $dbServer->GetCloudServerID(),
                    ), array(
                        'name'  => VolumeFilterNameType::attachmentDevice(),
                        'value' => '/dev/sda1',
                    ));
                    
                    $ebs = $aws->ec2->volume->describe(null, $filter);
                    foreach ($ebs as $volume) {
                        /* @var $volume \Scalr\Service\Aws\Ec2\DataType\VolumeData */
                        $volume->createTags($tags);  
                    }
                }
            }
        } catch (\Exception $e) {
            \Logger::getLogger(\LOG_CATEGORY::FARM)->error(
                new \FarmLogMessage($event->DBServer->farmId, sprintf(
                    _("Scalr was unable to add tags to the server/volume '{$event->DBServer->serverId}': %s"),
                    $e->getMessage()
                ))
            );
        }
    }
}
