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
     * Create tags for different EC2 objects (instance, volume, ami)
     * @param object $object (DBServer and BundleTask)
     */
    public static function createObjectTags($object)
    {
        if ($object instanceof \BundleTask) {
            // Create tags for AMI
            try {
                $env = \Scalr_Environment::init()->loadById($object->envId);
                $aws = $env->aws($object->cloudLocation);

                if ($object->farmId != 0) {
                    $dbServer = \DBServer::LoadByID($object->serverId);

                    $objectTags = [];
                    foreach ($dbServer->getAwsTags(true) as $k => $v) {
                        if ($k != 'Name') {
                            $objectTags[] = ['key' => $k, 'value' => $v];
                        }
                    }

                    $aws->ec2->image->describe($object->snapshotId)->get(0)->createTags($objectTags);

                    $object->Log(sprintf("Added %s tags for AMI (%s)", count($objectTags), $object->snapshotId));
                } else {
                    return;
                }
            } catch (\Exception $e) {
                $object->Log(sprintf("Scalr was unable to add tags for AMI (%s): %s", $object->snapshotId, $e->getMessage()));
            }
        } elseif ($object instanceof \DBServer) {
            // Create tags for Instance and root-device volume
            try {
                $dbServer = $object;
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

                    if ($metaTagFound) {
                        return;
                    }

                    $instanceTags = [];
                    $volumeTags = [];
                    foreach ($dbServer->getAwsTags(true) as $k => $v) {
                        $instanceTags[] = ['key' => $k, 'value' => $v];

                        if ($k != 'Name') {
                            $volumeTags[] = ['key' => $k, 'value' => $v];
                        }
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
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->error(
                    new \FarmLogMessage($dbServer->farmId, sprintf(
                        _("Scalr was unable to add tags to the server/volume '{$dbServer->serverId}': %s"),
                        $e->getMessage()
                    ), $dbServer->serverId)
                );
            }
        } else {
            return;
        }
    }
}
