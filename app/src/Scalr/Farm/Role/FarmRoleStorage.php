<?php
namespace Scalr\Farm\Role;

class FarmRoleStorage
{
    protected $farmRole;

    /**
     * @var \ADODB_mysqli
     */
    protected $db;

    public function __construct(\DBFarmRole $dbFarmRole)
    {
        $this->db = \Scalr::getDb();
        $this->farmRole = $dbFarmRole;
    }

    /**
     * @return FarmRoleStorageConfig[]
     */
    public function getConfigs()
    {
        return FarmRoleStorageConfig::getByFarmRole($this->farmRole);
    }

    public function setConfigs($configs)
    {
        if (!empty($configs) && is_array($configs)) {
            foreach($configs as $value) {

                if (!is_array($value) || !is_array($value['settings']))
                    continue;

                $config = new FarmRoleStorageConfig($this->farmRole);
                $config->create($value);
            }
        }
    }

    public function getVolumes($serverIndex = null)
    {
        $volumes = array();
        foreach ($this->getConfigs() as $config) {
            if (!$serverIndex)
                $volumes[$config->id] = FarmRoleStorageDevice::getByConfigId($config->id);
            else
                $volumes[$config->id][$serverIndex] = FarmRoleStorageDevice::getByConfigIdAndIndex($config->id, $serverIndex);
        }

        return $volumes;
    }

    /*
     * @param \DBServer $server
     * @param array volumes
     */
    public function setVolumes(\DBServer $server, $volumes)
    {
        $vlms = array();
        foreach ($volumes as $volume)
            $vlms[$volume->scalrStorageId] = $volume;

        foreach ($this->getConfigs() as $config) {
            if ($vlms[$config->id]) {
                $volume = new FarmRoleStorageDevice();
                if (!$volume->loadById($volume->id)) {
                    $volume->farmRoleId = $this->farmRole->ID;
                    $volume->storageConfigId = $config->id;
                    $volume->serverIndex = $server->index;
                    $volume->storageId = $vlms[$config->id]->id;
                    $volume->cloudLocation = $server->GetCloudLocation();
                    $volume->envId = $server->envId;
                }

                switch ($config->type) {
                    case FarmRoleStorageConfig::TYPE_RAID_EBS:
                        $volume->placement = $vlms[$config->id]->disks[0]->availZone;
                        break;

                    case FarmRoleStorageConfig::TYPE_EBS:
                        $volume->placement = $vlms[$config->id]->availZone;
                        break;
                }

                $volume->config = $vlms[$config->id];
                $volume->status = FarmRoleStorageDevice::STATUS_ACTIVE;

                $volume->save();

                unset($vlms[$config->id]);
            }
        }

        //TODO: Handle zombies
    }

    public function getVolumesConfigs(\DBServer $dbServer, $isHostInit = true)
    {
        $volumes = array();

        $configs = $this->getConfigs();
        foreach ($configs as $config) {
            //Check for existing volume
            $createFreshConfig = true;
            $volume = null;
            $dbVolume = FarmRoleStorageDevice::getByConfigIdAndIndex($config->id, $dbServer->index);
            if ($dbVolume) {
                 if ($config->reUse == 0 && $isHostInit) {
                     $dbVolume->status = FarmRoleStorageDevice::STATUS_ZOMBY;
                     $dbVolume->save();
                 } else {
                     $volume = $dbVolume->config;
                     $createFreshConfig = false;

                     $volume->mpoint = ($config->mount == 1) ? $config->mountPoint : null;
                 }
            }

            if ($createFreshConfig || $config->rebuild) {
                $volumeConfigTemplate = new \stdClass();
                $volumeConfigTemplate->scalrStorageId = $config->id;
                $volumeConfigTemplate->type = stristr($config->type, "raid.") ? FarmRoleStorageConfig::TYPE_RAID : $config->type;
                $volumeConfigTemplate->fstype = $config->fs;
                $volumeConfigTemplate->mpoint = ($config->mount == 1) ? $config->mountPoint : null;
                
                //Tags
                try {
                    $tags = array(
                        "scalr-env-id"  => $dbServer->envId,
                        "scalr-owner" => $dbServer->GetFarmObject()->createdByUserEmail,
                        "scalr-farm-id" => $dbServer->farmId,
                        "scalr-farm-role-id" => $dbServer->farmRoleId,
                        "scalr-server-id" => $dbServer->serverId
                    );
                    
                    $governance = new \Scalr_Governance($this->farmRole->GetFarmObject()->EnvID);
                    $gTags = (array)$governance->getValue('ec2', 'aws.tags');
                    if (count($gTags) > 0) {
                        foreach ($gTags as $tKey => $tValue) {
                            if ($tKey == 'Name')
                                continue;
                            $tags[$tKey] = $dbServer->applyGlobalVarsToValue($tValue);
                        }
                    } else {
                        //Custom tags
                        $cTags = $dbServer->GetFarmRoleObject()->GetSetting(\DBFarmRole::SETTING_AWS_TAGS_LIST);
                        $tagsList = @explode("\n", $cTags);
                        foreach ((array)$tagsList as $tag) {
                            $tag = trim($tag);
                            if ($tag && count($tags) < 10) {
                                $tagChunks = explode("=", $tag);
                                $tags[trim($tagChunks[0])] = $dbServer->applyGlobalVarsToValue(trim($tagChunks[1]));
                            }
                        }
                    }
                    
                    $volumeConfigTemplate->tags = $tags;
                } catch (\Exception $e) {}
                //

                switch ($config->type) {
                    case FarmRoleStorageConfig::TYPE_CINDER:
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_SIZE];
                        
                        if ($config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE])
                        	$volumeConfigTemplate->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE];

                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_CINDER_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new \stdClass();
                            $volumeConfigTemplate->snap->type = FarmRoleStorageConfig::TYPE_CINDER;
                            $volumeConfigTemplate->snap->id = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_SNAPSHOT];
                        }
                        break;
                    case FarmRoleStorageConfig::TYPE_CSVOL:
                        $volumeConfigTemplate->diskOfferingId = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING];
                        $volumeConfigTemplate->diskOfferingType = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING_TYPE];
                        if ($volumeConfigTemplate->diskOfferingType == 'custom' || !$volumeConfigTemplate->diskOfferingId)
                            $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SIZE];


                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new \stdClass();
                            $volumeConfigTemplate->snap->type = FarmRoleStorageConfig::TYPE_CSVOL;
                            $volumeConfigTemplate->snap->id = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SNAPSHOT];
                        }
                        break;
                    case FarmRoleStorageConfig::TYPE_GCE_PD:
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SIZE];

                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new \stdClass();
                            $volumeConfigTemplate->snap->type = FarmRoleStorageConfig::TYPE_GCE_PD;
                            $volumeConfigTemplate->snap->id = $config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SNAPSHOT];
                        }
                        break;
                    case FarmRoleStorageConfig::TYPE_EBS:
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_EBS_SIZE];
                        $volumeConfigTemplate->encrypted = (!empty($config->settings[FarmRoleStorageConfig::SETTING_EBS_ENCRYPTED])) ? 1 : 0;

                        // IOPS
                        $volumeConfigTemplate->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_EBS_TYPE];
                        if ($volumeConfigTemplate->volumeType == 'io1')
                            $volumeConfigTemplate->iops = $config->settings[FarmRoleStorageConfig::SETTING_EBS_IOPS];

                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_EBS_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new \stdClass();
                            $volumeConfigTemplate->snap->type = FarmRoleStorageConfig::TYPE_EBS;
                            $volumeConfigTemplate->snap->id = $config->settings[FarmRoleStorageConfig::SETTING_EBS_SNAPSHOT];
                        }
                        break;

                    case FarmRoleStorageConfig::TYPE_RAID_CSVOL:
                    case FarmRoleStorageConfig::TYPE_RAID_CINDER:
                    case FarmRoleStorageConfig::TYPE_RAID_EBS:
                    case FarmRoleStorageConfig::TYPE_RAID_GCE_PD:
                        $volumeConfigTemplate->level = $config->settings[FarmRoleStorageConfig::SETTING_RAID_LEVEL];
                        $volumeConfigTemplate->vg = $config->id;
                        $volumeConfigTemplate->disks = array();
                        for ($i = 1; $i <= $config->settings[FarmRoleStorageConfig::SETTING_RAID_VOLUMES_COUNT]; $i++) {
                            $disk = new \stdClass();

                            if ($config->type == FarmRoleStorageConfig::TYPE_RAID_EBS) {
                                $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_EBS_SIZE];
                                $disk->encrypted = (!empty($config->settings[FarmRoleStorageConfig::SETTING_EBS_ENCRYPTED])) ? 1 : 0;
                                $disk->type = FarmRoleStorageConfig::TYPE_EBS;

                                // IOPS
                                $disk->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_EBS_TYPE];
                                if ($disk->volumeType == 'io1')
                                    $disk->iops = $config->settings[FarmRoleStorageConfig::SETTING_EBS_IOPS];

                            } elseif ($config->type == FarmRoleStorageConfig::TYPE_RAID_CSVOL) {
                                $disk->diskOfferingId = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING];
                                $disk->diskOfferingType = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING_TYPE];
                                if ($disk->diskOfferingType == 'custom' || !$disk->diskOfferingId)
                                    $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SIZE];

                                $disk->type = FarmRoleStorageConfig::TYPE_CSVOL;
                            } elseif ($config->type == FarmRoleStorageConfig::TYPE_RAID_GCE_PD) {
                                $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SIZE];
                                $disk->type = FarmRoleStorageConfig::TYPE_GCE_PD;
                            } elseif ($config->type == FarmRoleStorageConfig::TYPE_RAID_CINDER) {
                                $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_SIZE];
                                $disk->type = FarmRoleStorageConfig::TYPE_CINDER;
                                if ($config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE])
                                	$disk->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE];
                            }

                            $volumeConfigTemplate->disks[] = $disk;
                        }

                        break;
                }
            }

            if (!$volume)
                $volume = $volumeConfigTemplate;
            elseif ($config->rebuild && $volume->id) {
                $volume->template = $volumeConfigTemplate;
                $volume->fromTemplateIfMissing = 1;
            }

            $volumes[] = $volume;
        }

        return $volumes;
    }
}
