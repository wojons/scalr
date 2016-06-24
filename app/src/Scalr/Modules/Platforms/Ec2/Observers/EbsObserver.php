<?php

namespace Scalr\Modules\Platforms\Ec2\Observers;

use Scalr\Service\Aws\Ec2\DataType\CreateVolumeRequestData;
use Scalr\Model\Entity;
use DBFarmRole;
use Scalr\Observer\AbstractEventObserver;
use FarmLogMessage;

class EbsObserver extends AbstractEventObserver
{

    public $ObserverName = 'Elastic Block Storage';

    function __construct()
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnBeforeInstanceLaunch()
     */
    public function OnBeforeInstanceLaunch(\BeforeInstanceLaunchEvent $event)
    {
        if ($event->DBServer->platform != \SERVER_PLATFORMS::EC2) {
            return;
        }
        $DBFarm = $event->DBServer->GetFarmObject();
        $DBFarmRole = $event->DBServer->GetFarmRoleObject();

        // Create EBS volume for MySQLEBS
        if (!$event->DBServer->IsSupported("0.6")) {
            // Only for old AMIs
            if ($DBFarmRole->GetRoleObject()->hasBehavior(\ROLE_BEHAVIORS::MYSQL) &&
                $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_DATA_STORAGE_ENGINE) == \MYSQL_STORAGE_ENGINE::EBS) {

                $server = $event->DBServer;
                $masterServer = $DBFarm->GetMySQLInstances(true);
                $isMaster = !$masterServer || $masterServer[0]->serverId == $server->serverId;
                $farmMasterVolId = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_MASTER_EBS_VOLUME_ID);
                $createEbs = ($isMaster && !$farmMasterVolId);

                if ($createEbs) {
                    \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                        $event->DBServer,
                        sprintf("Need EBS volume for MySQL %s instance...", ($isMaster ? "Master" : "Slave"))
                    ));

                    $req = new CreateVolumeRequestData(
                        $event->DBServer->GetProperty(\EC2_SERVER_PROPERTIES::AVAIL_ZONE),
                        $DBFarmRole->GetSetting(Entity\FarmRoleSetting::MYSQL_EBS_VOLUME_SIZE)
                    );
                    $aws = $event->DBServer->GetEnvironmentObject()->aws($DBFarmRole->CloudLocation);
                    $res = $aws->ec2->volume->create($req);

                    if (!empty($res->volumeId)) {
                        $DBFarmRole->SetSetting(Entity\FarmRoleSetting::MYSQL_MASTER_EBS_VOLUME_ID, $res->volumeId, Entity\FarmRoleSetting::TYPE_LCL);
                        \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(new FarmLogMessage(
                            $event->DBServer,
                            sprintf("MySQL %S volume created. Volume ID: %s...", ($isMaster ? "Master" : "Slave"), !empty($res->volumeId) ? $res->volumeId : null)
                        ));
                    }
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnFarmTerminated()
     */
    public function OnFarmTerminated(\FarmTerminatedEvent $event)
    {
        $this->Logger->info("Keep EBS volumes: {$event->KeepEBS}");
        if ($event->KeepEBS == 1) {
            return;
        }
        $this->DB->Execute("UPDATE ec2_ebs SET attachment_status=? WHERE farm_id=? AND ismanual='0'", array(
            \EC2_EBS_ATTACH_STATUS::DELETING,
            $this->FarmID
        ));
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnEBSVolumeAttached()
     */
    public function OnEBSVolumeAttached(\EBSVolumeAttachedEvent $event)
    {
        if ($event->DeviceName) {
            try {
                $DBEBSVolume = \DBEBSVolume::loadByVolumeId($event->VolumeID);

                $DBEBSVolume->serverId = $event->DBServer->serverId;
                $DBEBSVolume->deviceName = $event->DeviceName;
                $DBEBSVolume->attachmentStatus = \EC2_EBS_ATTACH_STATUS::ATTACHED;
                //$DBEBSVolume->isFsExists = 1;

                $DBEBSVolume->save();
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnEBSVolumeMounted()
     */
    public function OnEBSVolumeMounted(\EBSVolumeMountedEvent $event)
    {
        try {
            $DBEBSVolume = \DBEBSVolume::loadByVolumeId($event->VolumeID);

            $DBEBSVolume->mountStatus = \EC2_EBS_MOUNT_STATUS::MOUNTED;
            $DBEBSVolume->deviceName = $event->DeviceName;
            $DBEBSVolume->isFsExists = 1;

            $DBEBSVolume->save();
        } catch (\Exception $e) {}
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostUp()
     */
    public function OnHostUp(\HostUpEvent $event)
    {
        if ($event->DBServer->platform != \SERVER_PLATFORMS::EC2) return;
        // Scalarizr will attach and mount volumes by itself
        if ($event->DBServer->IsSupported("0.7.36")) {
            return;
        }

        $volumes = $this->DB->GetAll("SELECT volume_id FROM ec2_ebs WHERE farm_roleid=? AND server_index=?", array(
            $event->DBServer->farmRoleId,
            $event->DBServer->index
        ));

        $this->Logger->info(new FarmLogMessage(
            !empty($this->FarmID) ? $this->FarmID : null,
            sprintf(_("Found %s volumes for server: %s"), count($volumes), $event->DBServer->serverId),
            !empty($event->DBServer->serverId) ? $event->DBServer->serverId : null,
            !empty($event->DBServer->envId) ? $event->DBServer->envId : null,
            !empty($event->DBServer->farmRoleId) ? $event->DBServer->farmRoleId : null
        ));

        foreach ($volumes as $volume) {
            if ($volume['volume_id']) {

                $this->Logger->info(new FarmLogMessage(
                    !empty($this->FarmID) ? $this->FarmID : null,
                    sprintf(_("Preparing volume #%s for attaching to server: %s."), $volume['volume_id'], $event->DBServer->serverId),
                    !empty($event->DBServer->serverId) ? $event->DBServer->serverId : null,
                    !empty($event->DBServer->envId) ? $event->DBServer->envId : null,
                    !empty($event->DBServer->farmRoleId) ? $event->DBServer->farmRoleId : null
                ));

                try {
                    $DBEBSVolume = \DBEBSVolume::loadByVolumeId($volume['volume_id']);

                    $DBEBSVolume->serverId = $event->DBServer->serverId;
                    $DBEBSVolume->attachmentStatus = \EC2_EBS_ATTACH_STATUS::ATTACHING;
                    $DBEBSVolume->mountStatus = ($DBEBSVolume->mount) ?
                        \EC2_EBS_MOUNT_STATUS::AWAITING_ATTACHMENT : \EC2_EBS_MOUNT_STATUS::NOT_MOUNTED;

                    $DBEBSVolume->save();
                } catch (\Exception $e) {
                    $this->Logger->fatal($e->getMessage());
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostInit()
     */
    public function OnHostInit(\HostInitEvent $event)
    {
        if ($event->DBServer->platform != \SERVER_PLATFORMS::EC2) {
            return;
        }

        $DBFarmRole = $event->DBServer->GetFarmRoleObject();

        if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_USE_EBS)) {
            if (!$this->DB->GetOne("
                    SELECT id FROM ec2_ebs
                    WHERE farm_roleid=? AND server_index=? AND ismanual='0'
                    LIMIT 1
                ", array(
                    $event->DBServer->farmRoleId,
                    $event->DBServer->index
                ))) {

                if (in_array($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_TYPE), [
                    CreateVolumeRequestData::VOLUME_TYPE_IO1,
                    CreateVolumeRequestData::VOLUME_TYPE_STANDARD,
                    CreateVolumeRequestData::VOLUME_TYPE_GP2,
                    CreateVolumeRequestData::VOLUME_TYPE_ST1,
                    CreateVolumeRequestData::VOLUME_TYPE_SC1
                ])) {
                    $type = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_TYPE);
                } else {
                    $type = 'standard';
                }

                $DBEBSVolume = new \DBEBSVolume();
                $DBEBSVolume->attachmentStatus = \EC2_EBS_ATTACH_STATUS::CREATING;
                $DBEBSVolume->isManual = 0;
                $DBEBSVolume->ec2AvailZone = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_AVAIL_ZONE);
                $DBEBSVolume->ec2Region = $event->DBServer->GetProperty(\EC2_SERVER_PROPERTIES::REGION);
                $DBEBSVolume->farmId = $DBFarmRole->FarmID;
                $DBEBSVolume->farmRoleId = $DBFarmRole->ID;
                $DBEBSVolume->serverId = $event->DBServer->serverId;
                $DBEBSVolume->serverIndex = $event->DBServer->index;
                $DBEBSVolume->size = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_SIZE);
                $DBEBSVolume->type = $type;
                $DBEBSVolume->iops = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_IOPS);
                $DBEBSVolume->snapId = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_SNAPID);
                $DBEBSVolume->isFsExists = ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_SNAPID)) ? 1 : 0;
                $DBEBSVolume->mount = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_MOUNT);
                $DBEBSVolume->mountPoint = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_MOUNTPOINT);
                $DBEBSVolume->mountStatus = ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_EBS_MOUNT)) ?
                    \EC2_EBS_MOUNT_STATUS::AWAITING_ATTACHMENT : \EC2_EBS_MOUNT_STATUS::NOT_MOUNTED;
                $DBEBSVolume->clientId = $event->DBServer->GetFarmObject()->ClientID;
                $DBEBSVolume->envId = $event->DBServer->envId;

                $DBEBSVolume->Save();
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostDown()
     */
    public function OnHostDown(\HostDownEvent $event)
    {
        if ($event->DBServer->platform != \SERVER_PLATFORMS::EC2) {
            return;
        }
        if ($event->DBServer->IsRebooting()) {
            return;
        }
        $this->DB->Execute("
            UPDATE ec2_ebs
            SET attachment_status=?,
                mount_status=?,
                device='',
                server_id=''
            WHERE server_id=? AND attachment_status != ?
        ", array(
            \EC2_EBS_ATTACH_STATUS::AVAILABLE,
            \EC2_EBS_MOUNT_STATUS::NOT_MOUNTED,
            $event->DBServer->serverId,
            \EC2_EBS_ATTACH_STATUS::CREATING
        ));
    }
}
