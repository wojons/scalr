<?php
namespace Scalr\Farm\Role;

use DBFarmRole;
use DBServer;
use stdClass;

class FarmRoleStorage
{
    protected $farmRole;

    /**
     * @var \ADODB_mysqli
     */
    protected $db;

    public function __construct(DBFarmRole $dbFarmRole)
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

    /**
     * Validate storage configs
     *
     * @param   array   $configs    Array of storage configs, structure is defined in FarmRoleStorageConfig::apply
     * @return  array   Array of errors [index => error message] or empty array if configs are valid
     */
    public function validateConfigs($configs)
    {
        $errors = [];
        $configs = is_array($configs) ? $configs : [];

        foreach ($configs as $key => $value) {
            if (!is_array($value) || !is_array($value['settings'])) {
                continue;
            }

            $config = new FarmRoleStorageConfig($this->farmRole);
            $config->apply($value);

            if ($config->status != FarmRoleStorageConfig::STATE_PENDING_DELETE) {
                $result = $config->validate();

                if ($result !== true) {
                    $errors[$key] = $result;
                }
            }
        }

        return $errors;
    }

    /**
     * Save storage configs
     *
     * @param   array   $configs    Array of storage config
     * @param   bool    $validate   optional    If true validate config before save
     * @throws  FarmRoleStorageException
     */
    public function setConfigs($configs, $validate = true)
    {
        $configs = is_array($configs) ? $configs : [];
        $ephemeralEc2 = [];
        $ephemeralGce = [];

        foreach ($configs as $value) {
            if (!is_array($value) || !is_array($value['settings'])) {
                continue;
            }

            $object = new FarmRoleStorageConfig($this->farmRole);
            $object->apply($value);

            if ($validate) {
                $r = $object->validate();

                if ($r !== true) {
                    throw new FarmRoleStorageException($r);
                }
            }

            $config = new FarmRoleStorageConfig($this->farmRole);
            $config->create($object);

            if ($config->type == FarmRoleStorageConfig::TYPE_EC2_EPHEMERAL) {
                $ephemeralEc2[$config->settings[FarmRoleStorageConfig::SETTING_EC2_EPHEMERAL_NAME]] = $config->id;
            } else if ($config->type == FarmRoleStorageConfig::TYPE_GCE_EPHEMERAL) {
                $ephemeralGce[$config->settings[FarmRoleStorageConfig::SETTING_GCE_EPHEMERAL_NAME]] = $config->id;
            }
        }

        // validate ephemeral configs
        foreach (self::getConfigs() as $config) {
            if ($config->type == FarmRoleStorageConfig::TYPE_EC2_EPHEMERAL) {
                $name = $config->settings[FarmRoleStorageConfig::SETTING_EC2_EPHEMERAL_NAME];

                if (!isset($ephemeralEc2[$name]) || $ephemeralEc2[$name] != $config->id) {
                    $config->delete();
                }
            } else if ($config->type == FarmRoleStorageConfig::TYPE_GCE_EPHEMERAL) {
                $name = $config->settings[FarmRoleStorageConfig::SETTING_GCE_EPHEMERAL_NAME];

                if (!isset($ephemeralGce[$name]) || $ephemeralGce[$name] != $config->id) {
                    $config->delete();
                }
            }
        }
    }

    public function getVolumes($serverIndex = null)
    {
        $volumes = [];
        foreach ($this->getConfigs() as $config) {
            if (!$serverIndex)
                $volumes[$config->id] = FarmRoleStorageDevice::getByConfigId($config->id);
            else
                $volumes[$config->id][$serverIndex] = FarmRoleStorageDevice::getByConfigIdAndIndex($config->id, $serverIndex);
        }

        return $volumes;
    }

    /*
     * @param DBServer $server
     * @param array volumes
     */
    public function setVolumes(DBServer $server, $volumes)
    {
        $vlms = [];

        foreach ($volumes as $volume) {
            $vlms[$volume->scalrStorageId] = $volume;
        }

        foreach ($this->getConfigs() as $config) {
            if ($vlms[$config->id]) {
                $volume = new FarmRoleStorageDevice();
                if (!$volume->loadById($vlms[$config->id]->id)) {
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

    public function getVolumesConfigs(DBServer $dbServer, $isHostInit = true)
    {
        $volumes = [];

        $configs = $this->getConfigs();

        foreach ($configs as $config) {
            //Check for existing volume
            $createFreshConfig = true;
            $volume = null;
            $dbVolume = FarmRoleStorageDevice::getByConfigIdAndIndex($config->id, $dbServer->index);

            if ($dbVolume) {
                 $volume = $dbVolume->config;
                 $createFreshConfig = false;
                 $volume->mpoint = ($config->mount == 1) ? $config->mountPoint : null;
            }

            if ($createFreshConfig || $config->rebuild) {
                $volumeConfigTemplate = new stdClass();
                $volumeConfigTemplate->scalrStorageId = $config->id;
                $volumeConfigTemplate->type = stristr($config->type, "raid.") ? FarmRoleStorageConfig::TYPE_RAID : $config->type;
                $volumeConfigTemplate->fstype = $config->fs;
                $volumeConfigTemplate->mpoint = ($config->mount == 1) ? $config->mountPoint : null;

                if ($config->mount == 1) {
                    if ($config->fs == 'ntfs' && !empty($config->label)) {
                        $volumeConfigTemplate->label = $config->label;
                    }
                    if ($config->fs != 'ntfs' && !empty($config->mountOptions)) {
                        $volumeConfigTemplate->mount_options = array_map('trim', explode(',', $config->mountOptions));
                    }
                }
                switch ($config->type) {
                    case FarmRoleStorageConfig::TYPE_EC2_EPHEMERAL:
                        $volumeConfigTemplate->name = $config->settings[FarmRoleStorageConfig::SETTING_EC2_EPHEMERAL_NAME];
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_EC2_EPHEMERAL_SIZE];
                        break;

                    case FarmRoleStorageConfig::TYPE_GCE_EPHEMERAL:
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_GCE_EPHEMERAL_SIZE];
                        $volumeConfigTemplate->name = $config->settings[FarmRoleStorageConfig::SETTING_GCE_EPHEMERAL_NAME];
                        break;

                    case FarmRoleStorageConfig::TYPE_CINDER:
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_SIZE];

                        if (!empty($config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE])) {
                            $volumeConfigTemplate->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE];
                        }

                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_CINDER_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new stdClass();
                            $volumeConfigTemplate->snap->type = FarmRoleStorageConfig::TYPE_CINDER;
                            $volumeConfigTemplate->snap->id = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_SNAPSHOT];
                        }

                        break;

                    case FarmRoleStorageConfig::TYPE_CSVOL:
                        $volumeConfigTemplate->diskOfferingId = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING];
                        $volumeConfigTemplate->diskOfferingType = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING_TYPE];

                        if ($volumeConfigTemplate->diskOfferingType == 'custom' || !$volumeConfigTemplate->diskOfferingId) {
                            $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SIZE];
                        }


                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new stdClass();
                            $volumeConfigTemplate->snap->type = FarmRoleStorageConfig::TYPE_CSVOL;
                            $volumeConfigTemplate->snap->id = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SNAPSHOT];
                        }

                        break;

                    case FarmRoleStorageConfig::TYPE_GCE_PD:
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SIZE];

                        if (!empty($config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_TYPE])) {
                            $volumeConfigTemplate->diskType = $config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_TYPE];
                        }

                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new stdClass();
                            $volumeConfigTemplate->snap->type = FarmRoleStorageConfig::TYPE_GCE_PD;
                            $volumeConfigTemplate->snap->id = $config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SNAPSHOT];
                        }

                        break;

                    case FarmRoleStorageConfig::TYPE_EBS:
                        $volumeConfigTemplate->size = $config->settings[FarmRoleStorageConfig::SETTING_EBS_SIZE];
                        $volumeConfigTemplate->encrypted = (!empty($config->settings[FarmRoleStorageConfig::SETTING_EBS_ENCRYPTED])) ? 1 : 0;
                        $volumeConfigTemplate->tags = $dbServer->getAwsTags();

                        // IOPS
                        $volumeConfigTemplate->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_EBS_TYPE];

                        if ($volumeConfigTemplate->volumeType == 'io1') {
                            $volumeConfigTemplate->iops = $config->settings[FarmRoleStorageConfig::SETTING_EBS_IOPS];
                        }

                        if (!empty($config->settings[FarmRoleStorageConfig::SETTING_EBS_KMS_KEY_ID])) {
                            $volumeConfigTemplate->kmsKeyId = $config->settings[FarmRoleStorageConfig::SETTING_EBS_KMS_KEY_ID];
                        }

                        // SNAPSHOT
                        if ($config->settings[FarmRoleStorageConfig::SETTING_EBS_SNAPSHOT] != '') {
                            $volumeConfigTemplate->snap = new stdClass();
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
                        $volumeConfigTemplate->disks = [];

                        for ($i = 1; $i <= $config->settings[FarmRoleStorageConfig::SETTING_RAID_VOLUMES_COUNT]; $i++) {
                            $disk = new stdClass();

                            if ($config->type == FarmRoleStorageConfig::TYPE_RAID_EBS) {
                                $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_EBS_SIZE];
                                $disk->encrypted = (!empty($config->settings[FarmRoleStorageConfig::SETTING_EBS_ENCRYPTED])) ? 1 : 0;
                                $disk->type = FarmRoleStorageConfig::TYPE_EBS;
                                $disk->tags = $dbServer->getAwsTags();

                                // IOPS
                                $disk->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_EBS_TYPE];

                                if ($disk->volumeType == 'io1') {
                                    $disk->iops = $config->settings[FarmRoleStorageConfig::SETTING_EBS_IOPS];
                                }

                                if (!empty($config->settings[FarmRoleStorageConfig::SETTING_EBS_KMS_KEY_ID])) {
                                    $disk->kmsKeyId = $config->settings[FarmRoleStorageConfig::SETTING_EBS_KMS_KEY_ID];
                                }

                            } elseif ($config->type == FarmRoleStorageConfig::TYPE_RAID_CSVOL) {
                                $disk->diskOfferingId = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING];
                                $disk->diskOfferingType = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_DISK_OFFERING_TYPE];

                                if ($disk->diskOfferingType == 'custom' || !$disk->diskOfferingId) {
                                    $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_CSVOL_SIZE];
                                }

                                $disk->type = FarmRoleStorageConfig::TYPE_CSVOL;
                            } elseif ($config->type == FarmRoleStorageConfig::TYPE_RAID_GCE_PD) {
                                $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_GCE_PD_SIZE];
                                $disk->type = FarmRoleStorageConfig::TYPE_GCE_PD;
                            } elseif ($config->type == FarmRoleStorageConfig::TYPE_RAID_CINDER) {
                                $disk->size = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_SIZE];
                                $disk->type = FarmRoleStorageConfig::TYPE_CINDER;

                                if (!empty($config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE])) {
                                    $disk->volumeType = $config->settings[FarmRoleStorageConfig::SETTING_CINDER_VOLUME_TYPE];
                                }
                            }

                            $volumeConfigTemplate->disks[] = $disk;
                        }

                        break;
                }
            }

            if (!$volume) {
                $volume = $volumeConfigTemplate;
            } elseif ($config->rebuild && $volume->id) {
                $volume->template = $volumeConfigTemplate;
                $volume->fromTemplateIfMissing = 1;
            }

            $volumes[] = $volume;
        }

        return $volumes;
    }
}
