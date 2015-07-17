<?php
use Scalr\Acl\Acl;
use Scalr\Service\Aws\Ec2\DataType\VolumeFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\AttachmentSetResponseData;
use Scalr\Service\Aws\Ec2\DataType\VolumeData;
use Scalr\Service\Aws\Ec2\DataType\ResourceTagSetData;
use Scalr\Service\Aws\Ec2\DataType\CreateVolumeRequestData;
use Scalr\Model\Entity\Image;

class Scalr_UI_Controller_Tools_Aws_Ec2_Ebs_Volumes extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'volumeId';

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_VOLUMES);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    public function attachAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $dbServers = $this->db->GetAll("SELECT server_id FROM servers WHERE platform=? AND status=? AND env_id=?", array(
            SERVER_PLATFORMS::EC2,
            SERVER_STATUS::RUNNING,
            $this->getEnvironmentId()
        ));

        if (count($dbServers) == 0) {
            throw new Exception("You have no running servers on EC2 platform");
        }

        /* @var $vol VolumeData */
        $vol = $aws->ec2->volume->describe($this->getParam('volumeId'))->get(0);

        $servers = array();
        foreach ($dbServers as $dbServer) {
            $dbServer = DBServer::LoadByID($dbServer['server_id']);
            if ($dbServer->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE) == $vol->availabilityZone) {
                $servers[$dbServer->serverId] = $dbServer->getNameByConvention();
            }
        }

        if (count($servers) == 0) {
            throw new Exception("You have no running servers on the availablity zone of this volume");
        }

        $this->response->page('ui/tools/aws/ec2/ebs/volumes/attach.js', array(
            'servers' => $servers
        ));
    }

    public function createAction()
    {
        $this->response->page('ui/tools/aws/ec2/ebs/volumes/create.js', array(
            'locations'	=> self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false)
        ));
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/ec2/ebs/volumes/view.js', []);
    }

    public function xAttachAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $this->request->defineParams(array(
            'cloudLocation', 'serverId', 'volumeId', 'mount', 'mountPoint'
        ));

        $errmsg = null;
        try {
            $dbEbsVolume = DBEBSVolume::loadByVolumeId($this->getParam('volumeId'));
            if ($dbEbsVolume->isManual == 0) {
                $errmsg = sprintf(_("This volume was automatically created for role '%s' on farm '%s' and cannot be re-attahced manually."),
                    $this->db->GetOne("
                        SELECT name FROM roles
                        JOIN farm_roles ON farm_roles.role_id = roles.id
                        WHERE farm_roles.id=?
                        LIMIT 1
                    ", array($dbEbsVolume->farmRoleId)),
                    $this->db->GetOne("SELECT name FROM farms WHERE id=? LIMIT 1", array($dbEbsVolume->farmId))
                );
            }
        } catch (Exception $e) {
        }

        if (!empty($errmsg)) {
            throw new Exception($errmsg);
        }

        /* @var $info VolumeData */
        $info = $aws->ec2->volume->describe($this->getParam('volumeId'))->get(0);

        $dBServer = DBServer::LoadByID($this->getParam('serverId'));

        $image = Image::findOne([
            ['platform' => $dBServer->platform],
            ['id' => $dBServer->imageId],
            ['cloudLocation' => $dBServer->GetCloudLocation()]
        ]);
        
        $device = $dBServer->GetFreeDeviceName($image->isEc2HvmImage());
        
        $res = $info->attach($dBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID), $device);

        if ($this->getParam('attachOnBoot') == 'on') {
            $dbEbsVolume = new DBEBSVolume();
            $dbEbsVolume->attachmentStatus = EC2_EBS_ATTACH_STATUS::ATTACHING;
            $dbEbsVolume->isManual = true;
            $dbEbsVolume->volumeId = $info->volumeId;
            $dbEbsVolume->ec2AvailZone = $info->availabilityZone;
            $dbEbsVolume->ec2Region = $this->getParam('cloudLocation');
            $dbEbsVolume->deviceName = $device;
            $dbEbsVolume->farmId = $dBServer->farmId;
            $dbEbsVolume->farmRoleId = $dBServer->farmRoleId;
            $dbEbsVolume->serverId = $dBServer->serverId;
            $dbEbsVolume->serverIndex = $dBServer->index;
            $dbEbsVolume->size = $info->size;
            $dbEbsVolume->snapId = $info->snapshotId;
            $dbEbsVolume->mount = ($this->getParam('mount') == 1);
            $dbEbsVolume->mountPoint = $this->getParam('mountPoint');
            $dbEbsVolume->mountStatus = ($this->getParam('mount') == 1) ? EC2_EBS_MOUNT_STATUS::AWAITING_ATTACHMENT : EC2_EBS_MOUNT_STATUS::NOT_MOUNTED;
            $dbEbsVolume->clientId = $this->user->getAccountId();
            $dbEbsVolume->envId = $this->getEnvironmentId();

            $dbEbsVolume->Save();
        }

        $this->response->success('EBS volume has been successfully attached');
    }

    public function xDetachAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));
        $this->request->defineParams(array(
            'cloudLocation', 'volumeId', 'forceDetach'
        ));

        $isForce = ($this->getParam('forceDetach') == 1);
        /* @var $att AttachmentSetResponseData */
        $att = $aws->ec2->volume->detach($this->getParam('volumeId'), null, null, $isForce);
        if ($att->volumeId && ($att->status == AttachmentSetResponseData::STATUS_DETACHING ||
            $att->status == AttachmentSetResponseData::STATUS_DETACHED)) {
            $dbEbsVolume = null;
            try {
                $dbEbsVolume = DBEBSVolume::loadByVolumeId($this->getParam('volumeId'));
            } catch (\Exception $e){
            }
            if (!empty($dbEbsVolume)) {
                if ($dbEbsVolume->isManual) {
                    $dbEbsVolume->delete();
                } else if (!$dbEbsVolume->isManual) {
                    $dbEbsVolume->attachmentStatus = EC2_EBS_ATTACH_STATUS::AVAILABLE;
                    $dbEbsVolume->mountStatus = EC2_EBS_MOUNT_STATUS::NOT_MOUNTED;
                    $dbEbsVolume->serverId = '';
                    $dbEbsVolume->deviceName = '';
                    $dbEbsVolume->save();
                }
            }
        }

        $this->response->success('Volume has been successfully detached');
    }

    public function xCreateAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $this->request->defineParams(array(
            'cloudLocation', 'availabilityZone', 'size', 'snapshotId'
        ));

        $req = new CreateVolumeRequestData($this->getParam('availabilityZone'), $this->getParam('size'));
        if ($this->getParam('snapshotId')) {
            $req->snapshotId = $this->getParam('snapshotId');
        }
        $res = $aws->ec2->volume->create($req);

        $this->response->success('EBS volume has been successfully created');
        $this->response->data(array('data' => array('volumeId' => $res->volumeId)));
    }

    public function xRemoveAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $this->request->defineParams(array(
            'volumeId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $cnt = 0;
        foreach ($this->getParam('volumeId') as $volumeId) {
            if ($aws->ec2->volume->delete($volumeId)) {
                $cnt++;
                $this->db->Execute("DELETE FROM ec2_ebs WHERE volume_id=?", array($volumeId));
            }
        }

        $this->response->success(sprintf('Volume%s been successfully removed.', ($cnt > 1 ? 's have' : ' has')));
    }


    public function xListVolumesAction()
    {
        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));
        $this->request->defineParams(array(
            'sort' => array('type' => 'json', 'default' => array('property' => 'volumeId', 'direction' => 'DESC')),
            'volumeId'
        ));

        if ($this->getParam('volumeId')) {
            $filter = array(array(
                'name'  => VolumeFilterNameType::volumeId(),
                'value' => $this->getParam('volumeId'),
            ));
        } else {
            $filter = null;
        }
        // Rows
        $volumeList = $aws->ec2->volume->describe(null, $filter);

        $vols = array();
        /* @var $pv VolumeData */
        foreach ($volumeList as $pv) {
            /* @var $att AttachmentSetResponseData */
            if (count($pv->attachmentSet)) {
                $att = $pv->attachmentSet[0];
            } else {
                $att = null;
            }

            $tags = array();
            foreach ($pv->tagSet as $tag) {
                /* @var $tag ResourceTagSetData */
                $tg = "{$tag->key}";
                if ($tag->value)
                    $tg .= "={$tag->value}";

                $tags[] = $tg;
            }

            $item = array(
                'volumeId'         => $pv->volumeId,
                'size'             => (int)$pv->size,
                'snapshotId'       => $pv->snapshotId,
                'availZone'        => $pv->availabilityZone,
                'type'             => $pv->volumeType,
                'status'           => $pv->status,
                'attachmentStatus' => ($att !== null ? $att->status : null),
                'device'           => ($att !== null ? $att->device : null),
                'instanceId'       => ($att !== null ? $att->instanceId : null),
                'tags'             => implode(',', $tags)
            );

            $item['autoSnaps'] = ($this->db->GetOne("SELECT id FROM autosnap_settings WHERE objectid=? AND object_type=? LIMIT 1",
                array($pv->volumeId, AUTOSNAPSHOT_TYPE::EBSSnap))) ? true : false;


            if (!empty($item['instanceId'])) {
                try {
                    $dbServer = DBServer::LoadByPropertyValue(EC2_SERVER_PROPERTIES::INSTANCE_ID, $item['instanceId']);
                    if ($dbServer) {
                        $item['farmId'] = $dbServer->farmId;
                        $item['farmRoleId'] = $dbServer->farmRoleId;
                        $item['serverIndex'] = $dbServer->index;
                        $item['serverId'] = $dbServer->serverId;
                        $item['farmName'] = $dbServer->GetFarmObject()->Name;
                        $item['mountStatus'] = false;
                        $item['roleName'] = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;

                        /* Waiting for bugfix on scalarizr side
                        if ($dbServer->IsSupported('2.5.4')) {
                            $item['mounts'] = $dbServer->scalarizr->system->mounts();
                        }
                        */
                    }

                } catch (\Exception $e) {}
            }

            $vols[] = $item;
        }

        $response = $this->buildResponseFromData($vols, array(
            'instanceId', 'volumeId', 'snapshotId', 'farmId', 'farmRoleId', 'availZone', 'type'
        ));

        $this->response->data($response);
    }
}