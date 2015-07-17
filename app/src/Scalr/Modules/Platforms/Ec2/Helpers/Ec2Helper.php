<?php

namespace Scalr\Modules\Platforms\Ec2\Helpers;

use Scalr\Service\Aws\Ec2\DataType\VolumeFilterNameType;

class Ec2Helper
{

    public static function farmSave(\DBFarm $DBFarm, array $roles)
    {
        
    }

    public static function farmValidateRoleSettings($settings, $rolename)
    {
    }

    public static function farmUpdateRoleSettings(\DBFarmRole $DBFarmRole, $oldSettings, $newSettings)
    {
    }

    /**
     * Creates a list of Amazon's security groups
     */
    public static function loadSecurityGroups()
    {
        
    }
    
    /**
     * Create tags on instance and it's root EBS volume
     * @param \DBServer $dbServer
     */
    public static function createServerTags(\DBServer $dbServer)
    {
        try {
            $aws = $dbServer->GetEnvironmentObject()->aws($dbServer);
        
            if ($dbServer->farmId != 0) {
                $ind = $aws->ec2->instance->describe($dbServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID))
                ->get(0)->instancesSet->get(0);
        
                $metaTagFound = false;
                foreach ($ind->tagSet as $tag) {
                    /* @var $tag \Scalr\Service\Aws\Ec2\DataType\ResourceTagSetData */
                    if ($tag->key == \Scalr_Governance::SCALR_META_TAG_NAME) {
                        $metaTagFound = true;
                        break;
                    }
                }
                
                if ($metaTagFound)
                    return;
                
                $instanceTags = [];
                $volumeTags = [];
                foreach ($dbServer->getAwsTags(true) as $k => $v) {
                    $instanceTags[] = ['key' => $k, 'value' => $v];
                    if ($k != 'Name')
                        $volumeTags[] = ['key' => $k, 'value' => $v];
                }
        
                $res = $ind->createTags($instanceTags);
        
                // We also need to tag root device
                if ($ind->rootDeviceType == 'ebs') {
                    $filter = array(array(
                        'name'  => VolumeFilterNameType::attachmentInstanceId(),
                        'value' => $dbServer->GetCloudServerID()
                    ), array(
                        'name'  => VolumeFilterNameType::attachmentDevice(),
                        'value' => '/dev/sda1'
                    ));
        
                    $ebs = $aws->ec2->volume->describe(null, $filter);
                    foreach ($ebs as $volume) {
                        /* @var $volume \Scalr\Service\Aws\Ec2\DataType\VolumeData */
                        $volume->createTags($volumeTags);
                    }
                }
            }
        } catch (\Exception $e) {
            \Logger::getLogger(\LOG_CATEGORY::FARM)->error(
                new \FarmLogMessage($dbServer->farmId, sprintf(
                    _("Scalr was unable to add tags to the server/volume '{$dbServer->serverId}': %s"),
                    $e->getMessage()
                ), $dbServer->serverId)
            );
        }
    }
}
