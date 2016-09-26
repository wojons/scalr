<?php

namespace Scalr\Farm\Role;

use Scalr\Service\Aws\Ec2\DataType\CreateVolumeRequestData;

class FarmRoleStorageConfig
{
    public  $id,
            $type,
            $index,
            $fs,
            $reUse,
            $rebuild,
            $mount,
            $mountPoint,
            $label,
            $mountOptions,
            $status,
            $settings;

    protected   $db,
                $farmRole;

    const TYPE_RAID = 'raid';

    const TYPE_RAID_EBS = 'raid.ebs';
    const TYPE_RAID_CSVOL = 'raid.csvol';
    const TYPE_RAID_CINDER = 'raid.cinder';
    const TYPE_RAID_GCE_PD = 'raid.gce_persistent';

    const TYPE_EBS = 'ebs';
    const TYPE_CSVOL = 'csvol';
    const TYPE_CINDER = 'cinder';
    const TYPE_GCE_PD = 'gce_persistent';
    const TYPE_EC2_EPHEMERAL = 'ec2_ephemeral';
    const TYPE_GCE_EPHEMERAL = 'gce_ephemeral';

    const SETTING_RAID_LEVEL = 'raid.level';
    const SETTING_RAID_VOLUMES_COUNT = 'raid.volumes_count';

    const SETTING_CSVOL_SIZE = 'csvol.size';
    const SETTING_CSVOL_SNAPSHOT = 'csvol.snapshot_id';
    const SETTING_CSVOL_DISK_OFFERING = 'csvol.disk_offering_id';
    const SETTING_CSVOL_DISK_OFFERING_TYPE = 'csvol.disk_offering_type';

    const SETTING_GCE_PD_SIZE = 'gce_persistent.size';
    const SETTING_GCE_PD_TYPE = 'gce_persistent.type';
    const SETTING_GCE_PD_SNAPSHOT = 'gce_persistent.snapshot';

    const SETTING_CINDER_SIZE = 'cinder.size';
    const SETTING_CINDER_SNAPSHOT = 'cinder.snapshot';
    const SETTING_CINDER_VOLUME_TYPE = 'cinder.volume_type';

    const SETTING_EBS_SIZE = 'ebs.size';
    const SETTING_EBS_TYPE = 'ebs.type';
    const SETTING_EBS_IOPS = 'ebs.iops';
    const SETTING_EBS_DEVICE_NAME = 'ebs.device_name';
    const SETTING_EBS_SNAPSHOT = 'ebs.snapshot';
    const SETTING_EBS_ENCRYPTED = 'ebs.encrypted';
    const SETTING_EBS_KMS_KEY_ID = 'ebs.kms_key_id';

    const SETTING_EC2_EPHEMERAL_NAME = 'ec2_ephemeral.name';
    const SETTING_EC2_EPHEMERAL_SIZE = 'ec2_ephemeral.size';

    const SETTING_GCE_EPHEMERAL_NAME = 'gce_ephemeral.name';
    const SETTING_GCE_EPHEMERAL_SIZE = 'gce_ephemeral.size';

    const STATE_PENDING_DELETE = 'Pending delete';
    const STATE_PENDING_CREATE = 'Pending create';
    const STATE_CONFIGURED = 'Configured';

    public function __construct(\DBFarmRole $farmRole)
    {
        $this->farmRole = $farmRole;
        $this->db = \Scalr::getDb();
    }

    /**
     * @param \DBFarmRole $farmRole
     * @return FarmRoleStorageConfig[]
     */
    public static function getByFarmRole(\DBFarmRole $farmRole)
    {
        $db = \Scalr::getDb();
        $configs = array();
        $ids = $db->GetCol('SELECT id FROM farm_role_storage_config WHERE farm_role_id = ?', array($farmRole->ID));
        foreach ($ids as $id) {
            $config = new FarmRoleStorageConfig($farmRole);
            if ($config->loadById($id))
                $configs[] = $config;
        }

        return $configs;
    }

    public function loadById($id)
    {
        $data = $this->db->GetRow('SELECT * FROM farm_role_storage_config WHERE id = ? AND farm_role_id = ? LIMIT 1', array($id, $this->farmRole->ID));
        if (empty($data))
            return false;

        $this->id = $data['id'];
        $this->type = $data['type'];
        $this->index = $data['index'];
        $this->fs = $data['fs'];
        $this->reUse = $data['re_use'];
        $this->mount = $data['mount'];
        $this->mountPoint = $data['mountpoint'];
        $this->label = $data['label'];
        $this->mountOptions = $data['mount_options'];
        $this->rebuild = $data['rebuild'];
        $this->status = $data['status'];
        $this->settings = array();
        foreach($this->db->GetAll('SELECT name, value FROM farm_role_storage_settings WHERE storage_config_id = ?', array($this->id)) as $value) {
            $this->settings[$value['name']] = $value['value'];
        }

        return $this;
    }

    /**
     * Apply properties from config to object
     *
     * @param   array   $config     Storage config
     * @return  FarmRoleStorageConfig
     */
    public function apply(array $config)
    {
        $settings = [];
        $type = $config['type'];

        if ($type == self::TYPE_CSVOL || $type == self::TYPE_RAID_CSVOL) {
            $settings[self::SETTING_CSVOL_SNAPSHOT] = $config['settings'][self::SETTING_CSVOL_SNAPSHOT];
            $settings[self::SETTING_CSVOL_SIZE] = intval($config['settings'][self::SETTING_CSVOL_SIZE]);
            $settings[self::SETTING_CSVOL_DISK_OFFERING] = $config['settings'][self::SETTING_CSVOL_DISK_OFFERING];
            $settings[self::SETTING_CSVOL_DISK_OFFERING_TYPE] = $config['settings'][self::SETTING_CSVOL_DISK_OFFERING_TYPE];

        } elseif ($type == self::TYPE_CINDER || $type == self::TYPE_RAID_CINDER) {
            $settings[self::SETTING_CINDER_SNAPSHOT] = $config['settings'][self::SETTING_CINDER_SNAPSHOT];
            $settings[self::SETTING_CINDER_VOLUME_TYPE] = $config['settings'][self::SETTING_CINDER_VOLUME_TYPE];
            $settings[self::SETTING_CINDER_SIZE] = intval($config['settings'][self::SETTING_CINDER_SIZE]);

        } elseif ($type == self::TYPE_GCE_PD || $type == self::TYPE_RAID_GCE_PD) {
            $settings[self::SETTING_GCE_PD_SNAPSHOT] = $config['settings'][self::SETTING_GCE_PD_SNAPSHOT];
            $settings[self::SETTING_GCE_PD_SIZE] = intval($config['settings'][self::SETTING_GCE_PD_SIZE]);

            if (isset($config['settings'][self::SETTING_GCE_PD_TYPE])) {
                $settings[self::SETTING_GCE_PD_TYPE] = $config['settings'][self::SETTING_GCE_PD_TYPE];
            }

        } elseif ($type == self::TYPE_EBS || $type == self::TYPE_RAID_EBS) {
            $settings[self::SETTING_EBS_SIZE] = intval($config['settings'][self::SETTING_EBS_SIZE]);
            $settings[self::SETTING_EBS_TYPE] = $config['settings'][self::SETTING_EBS_TYPE];
            $settings[self::SETTING_EBS_SNAPSHOT] = $config['settings'][self::SETTING_EBS_SNAPSHOT];

            if ($settings[self::SETTING_EBS_TYPE] == 'io1') {
                $settings[self::SETTING_EBS_IOPS] = intval($config['settings'][self::SETTING_EBS_IOPS]);
            }

            if ($type == self::TYPE_EBS) {
                $settings[self::SETTING_EBS_ENCRYPTED] = !empty($config['settings'][self::SETTING_EBS_ENCRYPTED]) ? 1 : 0;
                if ($settings[self::SETTING_EBS_ENCRYPTED] && !empty($config['settings'][self::SETTING_EBS_KMS_KEY_ID])) {
                    $settings[self::SETTING_EBS_KMS_KEY_ID] = $config['settings'][self::SETTING_EBS_KMS_KEY_ID];
                }
            }
        } elseif ($type == self::TYPE_EC2_EPHEMERAL) {
            $settings[self::SETTING_EC2_EPHEMERAL_NAME] = $config['settings'][self::SETTING_EC2_EPHEMERAL_NAME];
            $settings[self::SETTING_EC2_EPHEMERAL_SIZE] = intval($config['settings'][self::SETTING_EC2_EPHEMERAL_SIZE]);
        } elseif ($type == self::TYPE_GCE_EPHEMERAL) {
            $settings[self::SETTING_GCE_EPHEMERAL_NAME] = $config['settings'][self::SETTING_GCE_EPHEMERAL_NAME];
            $settings[self::SETTING_GCE_EPHEMERAL_SIZE] = intval($config['settings'][self::SETTING_GCE_EPHEMERAL_SIZE]);
        }

        $settings[self::SETTING_RAID_LEVEL] = $config['settings'][self::SETTING_RAID_LEVEL];
        $settings[self::SETTING_RAID_VOLUMES_COUNT] = $config['settings'][self::SETTING_RAID_VOLUMES_COUNT];

        $this->id = $config['id'];
        $this->type = $type;
        $this->fs = $config['fs'];
        $this->status = $config['status'];
        $this->reUse = !empty($config['reUse']) ? 1 : NULL;
        $this->rebuild = !empty($config['rebuild']) ? 1 : NULL;
        $this->mount = !empty($config['mount']) ? 1 : NULL;
        $this->mountPoint = $config['mountPoint'];
        $this->mountOptions = $config['mountOptions'];
        $this->label = $config['label'];
        $this->settings = $settings;
        return $this;
    }

    /**
     * Validate current object
     *
     * @return  string|true  Return true if object valid or string on error
     */
    public function validate()
    {
        if (! in_array($this->type, [self::TYPE_RAID_EBS, self::TYPE_RAID_CSVOL, self::TYPE_RAID_CINDER, self::TYPE_EBS, self::TYPE_CSVOL, self::TYPE_CINDER, self::TYPE_GCE_PD, self::TYPE_RAID_GCE_PD, self::TYPE_EC2_EPHEMERAL, self::TYPE_GCE_EPHEMERAL])) {
            return '[Storage] Invalid type';
        }

        if ($this->type == self::TYPE_CINDER || $this->type == self::TYPE_RAID_CINDER) {
            if ($this->settings[self::SETTING_CINDER_SIZE] < 1 || $this->settings[self::SETTING_CINDER_SIZE] > 1024)
                return 'Volume size should be from 1 to 1024 GB';

        } elseif (($this->type == self::TYPE_GCE_PD || $this->type == self::TYPE_RAID_GCE_PD) && isset($this->settings[self::SETTING_GCE_PD_TYPE])) {
            if (!in_array($this->settings[self::SETTING_GCE_PD_TYPE], ['pd-standard', 'pd-ssd']))
                return 'Invalid GCE disk type';

        } elseif ($this->type == self::TYPE_EBS || $this->type == self::TYPE_RAID_EBS) {
            if (!in_array($this->settings[self::SETTING_EBS_TYPE], [
                CreateVolumeRequestData::VOLUME_TYPE_STANDARD,
                CreateVolumeRequestData::VOLUME_TYPE_GP2,
                CreateVolumeRequestData::VOLUME_TYPE_IO1,
                CreateVolumeRequestData::VOLUME_TYPE_SC1,
                CreateVolumeRequestData::VOLUME_TYPE_ST1
            ]))
                return 'EBS type should be standard, gp2 or io1';

            if ($this->settings[self::SETTING_EBS_TYPE] == CreateVolumeRequestData::VOLUME_TYPE_STANDARD && ($this->settings[self::SETTING_EBS_SIZE] < 1 || $this->settings[self::SETTING_EBS_SIZE] > 1024)) {
                return 'EBS size should be from 1 to 1024 GB';
            }

            if (in_array($this->settings[self::SETTING_EBS_TYPE], [
                CreateVolumeRequestData::VOLUME_TYPE_GP2, 
                CreateVolumeRequestData::VOLUME_TYPE_SC1,
                CreateVolumeRequestData::VOLUME_TYPE_ST1
            ]) && ($this->settings[self::SETTING_EBS_SIZE] < 1 || $this->settings[self::SETTING_EBS_SIZE] > 16384)) {
                return 'EBS size should be from 1 to 16384 GB';
            }

            if ($this->settings[self::SETTING_EBS_TYPE] == CreateVolumeRequestData::VOLUME_TYPE_IO1) {
                if ($this->settings[self::SETTING_EBS_IOPS] < 100 || $this->settings[self::SETTING_EBS_IOPS] > 20000)
                    return 'EBS iops should be from 100 to 20000';

                if (($this->settings[self::SETTING_EBS_IOPS] / $this->settings[self::SETTING_EBS_SIZE]) > 30)
                    return sprintf(
                        'Invalid ratio. You should increase volume size to %d GB or decrease volume iops to %d',
                        (int) $this->settings[self::SETTING_EBS_IOPS]/30,
                        $this->settings[self::SETTING_EBS_SIZE] * 30
                    );
            }

            // TODO: validate KMS_KEY_ID
        }

        // TODO: validate raid, cvsol

        return true;
    }

    /**
     * Create new FarmRoleStorageConfig based on input config
     *
     * @param   FarmRoleStorageConfig   $config
     */
    public function create(FarmRoleStorageConfig $config)
    {
        $deleteFlag = false;

        if ($config->id) {
            $this->loadById($config->id);

            if ($this->status == self::STATE_PENDING_CREATE) {
                if ($config->status == self::STATE_PENDING_DELETE) {
                    // mark for delete on save
                    $deleteFlag = true;
                } else {
                    $this->type = $config->type;
                    $this->fs = $config->fs;
                    $this->reUse = $config->reUse;
                    $this->rebuild = $config->rebuild;
                    $this->mount = $config->mount;
                    $this->mountPoint = $config->mountPoint;
                    $this->mountOptions = $config->mountOptions;
                    $this->label = $config->label;
                }
            } elseif ($config->status == self::STATE_PENDING_DELETE) {
                $this->status = self::STATE_PENDING_DELETE;
            }
        } else {
            $this->id = \Scalr::GenerateUID();
            $this->type = $config->type;
            $this->fs = $config->fs;
            $this->reUse = $config->reUse;
            $this->rebuild = $config->rebuild;
            $this->mount = $config->mount;
            $this->mountPoint = $config->mountPoint;
            $this->mountOptions = $config->mountOptions;
            $this->label = $config->label;
            $this->status = self::STATE_PENDING_CREATE;
        }

        if ($deleteFlag) {
            $this->delete();
            return;
        }

        $this->settings = $config->settings;
        $this->save();
    }

    public function save()
    {
        $this->db->Execute("INSERT INTO farm_role_storage_config SET
            id = ?,
            farm_role_id = ?,
            `index` = ?,
            `type` = ?,
            fs = ?,
            re_use = ?,
            rebuild = ?,
            mount = ?,
            mountpoint = ?,
            label = ?,
            mount_options = ?,
            status = ?
        ON DUPLICATE KEY UPDATE `index` = ?, `type` = ?, fs = ?, re_use = ?, `rebuild` = ?, mount = ?, mountpoint = ?, label = ?, mount_options = ?, status = ?
        ", array(
            $this->id,
            $this->farmRole->ID,
            $this->index,
            $this->type,
            $this->fs,
            $this->reUse,
            $this->rebuild,
            $this->mount,
            $this->mountPoint,
            $this->label,
            $this->mountOptions,
            $this->status,

            $this->index,
            $this->type,
            $this->fs,
            $this->reUse,
            $this->rebuild,
            $this->mount,
            $this->mountPoint,
            $this->label,
            $this->mountOptions,
            $this->status
        ));

        $this->db->Execute('DELETE FROM farm_role_storage_settings WHERE storage_config_id = ?', array($this->id));

        if (count($this->settings)) {
            $query = array();
            $args = array();
            foreach ($this->settings as $key => $value) {
                $query[] = '(?,?,?)';
                $args[] = $this->id;
                $args[] = $key;
                $args[] = $value;
            }
            $this->db->Execute('INSERT INTO farm_role_storage_settings (storage_config_id, name, value) VALUES ' . implode(',', $query), $args);
        }
    }

    public function delete($id = null)
    {
        $id = !is_null($id) ? $id : $this->id;
        $this->db->Execute('DELETE FROM farm_role_storage_settings WHERE storage_config_id = ?', array($id));

        //TODO: NEET TO SET FarmRoleStorageDevice::TYPE_ZOMBY to all devices of deleted config
        // $this->db->Execute('DELETE FROM farm_role_storage_devices WHERE storage_id = ?', array($id));
        $this->db->Execute('DELETE FROM farm_role_storage_config WHERE id = ?', array($id));
    }
}
