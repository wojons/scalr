<?php
use Scalr\Acl\Acl;
use Scalr\Service\Aws\Ec2\DataType\VolumeFilterNameType;
use Scalr\Service\Aws\Ec2\DataType\AttachmentSetResponseData;
use Scalr\Service\Aws\Ec2\DataType\VolumeData;
use Scalr\Service\Aws\Ec2\DataType\ResourceTagSetData;
use Scalr\Service\Aws\Ec2\DataType\CreateVolumeRequestData;
use Scalr\Model\Entity\Image;
use Scalr\UI\Request\Validator;
use Scalr\Acl\Resource\CloudResourceFilteringDecision;
use Scalr\Acl\Resource\Mode\CloudResourceScopeMode;

class Scalr_UI_Controller_Tools_Aws_Ec2_Ebs_Volumes extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'volumeId';

    /**
     * Simple cache for volumes data
     *
     * @var array
     */
    protected $listCache = ["instances" => [], "farms" => [], "farmRoles" => []];

    /**
     * List of the managed farms
     *
     * @var array
     */
    protected $managedFarms;

    /**
     * @var CloudResourceFilteringDecision
     */
    protected $filteringDecision;

    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_AWS_VOLUMES);
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * Gets Cloud Resource filtering decision that is based on the User
     *
     * @return CloudResourceFilteringDecision
     */
    protected function getFilteringDecision()
    {
        if (!$this->filteringDecision) {
            $this->filteringDecision = $this->request->getCloudResourceFilteringDecision(Acl::RESOURCE_AWS_VOLUMES);
        }

        return $this->filteringDecision;
    }

    /**
     * Describes volume on the EC2 and checks ACL permissions
     *
     * @param    string    $cloudLocation  Cloud location
     * @param    string    $id             ID of the volume
     * @return   \Scalr\Service\Aws\Ec2\DataType\VolumeData Returns the volume data
     * @throws   Scalr_Exception_InsufficientPermissions
     */
    protected function describeVolume($cloudLocation, $id)
    {
        $aws = $this->getEnvironment()->aws($cloudLocation);

        $filteringDecision = $this->getFilteringDecision();

        if ($filteringDecision->emptySet) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        /* @var $vol VolumeData */
        $vol = $aws->ec2->volume->describe(
            $this->getParam('volumeId'),
            !empty($filteringDecision->filter) ? $filteringDecision->filter : null
        )->get(0);

        if (!$filteringDecision->matchAwsResourceTag($vol)) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        //Check if volume is attached to the Server this User have access to.
        if (!empty($vol->attachmentSet[0]->instanceId)) {
            $instanceId = $vol->attachmentSet[0]->instanceId;

            if (isset($this->listCache["instances"][$instanceId])) {
                $dbServer = $this->listCache["instances"][$instanceId];
            } else {
                $dbServer = DBServer::LoadByPropertyValue(EC2_SERVER_PROPERTIES::INSTANCE_ID, $instanceId);
                $this->listCache["instances"][$instanceId] = $dbServer;
            }

            if ($dbServer->envId != $this->getEnvironmentId()) {
                throw new Exception("Access forbidden: The volume is attached to the Server from another Environment.");
            }

            if (!$this->request->isAllowed(Acl::RESOURCE_FARMS)) {
                if (!in_array($dbServer->farmId, $this->getManagedFarms())) {
                    throw new Exception("Access forbidden: The volume is attached to the Server to which you don't have access.");
                }
            }
        }

        return $vol;
    }

    /**
     * Gets the list of the managed Farms
     *
     * @return array Returns the list of the managed Farms
     */
    protected function getManagedFarms()
    {
        if ($this->managedFarms === null) {
            $this->managedFarms = call_user_func_array([$this->db, 'GetCol'], $this->request->prepareFarmSqlQuery("
                SELECT id FROM farms WHERE env_id = ?
            ", [$this->getEnvironmentId()]));
        }

        return $this->managedFarms;
    }

    public function attachAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_VOLUMES, Acl::PERM_AWS_VOLUMES_MANAGE);

        $vol = $this->describeVolume($this->getParam('cloudLocation'), $this->getParam('volumeId'));

        $servers = $this->getRunningServersByAvailabilityZone($vol->availabilityZone);

        if (count($servers) == 0) {
            throw new Exception(
                "Either you have no running servers in the availablity zone of this volume "
                . "or you don't have access permissions to these servers."
            );
        }

        $this->response->page('ui/tools/aws/ec2/ebs/volumes/attach.js', array(
            'servers' => $servers
        ));
    }

    /**
     * Gets the list of the running servers by the specified availability zone
     *
     * It takes into consideration Managed Farm ACL permission
     *
     * @param    string     $availabilityZone
     * @return   array      Returns array looks like [serverId => name by convention]
     */
    protected function getRunningServersByAvailabilityZone($availabilityZone)
    {
        if (!$this->request->isAllowed(Acl::RESOURCE_FARMS)) {
            $restrictToFarms = true;
            $managedFarms = $this->getManagedFarms();
        }

        $servers = [];

        // Checks whether the user has no access to any Farm
        if (empty($restrictToFarms) || !empty($restrictToFarms) && !empty($managedFarms)) {
            $rows = $this->db->GetAll("
                SELECT s.server_id, s.farm_id, s.farm_roleid, fr.alias AS farm_roles_alias, r.name AS role_name, f.name AS farm_name
                FROM servers s
                JOIN server_properties sp ON sp.server_id = s.server_id AND sp.name = ?
                LEFT JOIN farm_roles fr ON s.farm_roleid = fr.id
                LEFT JOIN roles r ON r.id = fr.role_id
                LEFT JOIN farms f ON f.id = s.farm_id
                WHERE s.platform = ? AND s.status = ? AND s.env_id = ?
                AND sp.value = ?
                " . (!empty($restrictToFarms) ? "AND s.farm_id IN ('" . join("', '", $managedFarms) . "') " : "") . "
            ", [
                EC2_SERVER_PROPERTIES::AVAIL_ZONE,
                SERVER_PLATFORMS::EC2,
                SERVER_STATUS::RUNNING,
                $this->getEnvironmentId(),
                $availabilityZone
            ]);

            foreach ($rows as $row) {
                $dbServer = DBServer::LoadByID($row['server_id']);
                $servers[] = [
                    'id' => $dbServer->serverId,
                    'name' => $dbServer->getNameByConvention(),
                    'farmName' => $row['farm_name'],
                    'farmRoleName' => !empty($row['farm_role_alias']) ? $row['farm_role_alias'] : $row['role_name']
                ];
            }
        }

        return $servers;
    }

    /**
     * Gets the list of the servers which should be used to attach a new volume to it
     *
     * @param   string   $availabilityZone  Availability zone
     */
    public function xGetServersListAction($availabilityZone)
    {
        $servers = $this->getRunningServersByAvailabilityZone($availabilityZone);

        $this->response->data(['servers' => $servers]);
    }

    public function createAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_VOLUMES, Acl::PERM_AWS_VOLUMES_MANAGE);

        $this->response->page('ui/tools/aws/ec2/ebs/volumes/create.js', array(
            'locations'	=> self::loadController('Platforms')->getCloudLocations(SERVER_PLATFORMS::EC2, false)
        ));
    }

    public function viewAction()
    {
        $this->response->page('ui/tools/aws/ec2/ebs/volumes/view.js', []);
    }

    /**
     * Attaches volume to server
     *
     * It uses request params and can't be used without UI request
     *
     * @param    VolumeData $info AWS EBS Volume info
     * @throws   Exception
     */
    protected function attachVolumeToServer(VolumeData $info)
    {
        $dBServer = DBServer::LoadByID($this->getParam('serverId'));

        //Check access permission to specified server
        if (!$this->request->isFarmAllowed($dBServer->GetFarmObject())) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

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

        $image = Image::findOne([
            ['platform'      => $dBServer->platform],
            ['id'            => $dBServer->imageId],
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

        //Updates/Creates AWS Tags of Volume
        $tags = [];

        foreach ($dBServer->getAwsTags() as $k => $v) {
            $tags[] = [
                'key'   => $k,
                'value' => $v
            ];
        }

        if (!empty($tags)) {
            $info->createTags($tags);
        }
    }

    public function xAttachAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_VOLUMES, Acl::PERM_AWS_VOLUMES_MANAGE);

        $this->request->defineParams(array(
            'cloudLocation', 'serverId', 'volumeId', 'mount', 'mountPoint'
        ));

        /* @var $info VolumeData */
        $info = $this->describeVolume($this->getParam('cloudLocation'), $this->getParam('volumeId'));

        $this->attachVolumeToServer($info);

        $this->response->success('EBS volume has been successfully attached');
    }

    public function xDetachAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_VOLUMES, Acl::PERM_AWS_VOLUMES_MANAGE);

        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $this->request->defineParams(array(
            'cloudLocation', 'volumeId', 'forceDetach'
        ));

        $isForce = ($this->getParam('forceDetach') == 1);

        //Describes if the volume exists and checks user access permissions
        $decision = $this->getFilteringDecision();

        //We should check permissions to this volume
        $this->describeVolume($this->getParam('cloudLocation'), $this->getParam('volumeId'));

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

    /**
     * @param string $cloudLocation
     * @param string $availabilityZone
     * @param int    $size
     * @param string $snapshotId
     * @param bool   $encrypted
     * @param string $kmsKeyId
     * @param string $type
     * @param int    $iops
     * @param string $serverId
     * @param bool   $mount
     * @param string $mountPoint
     */
    public function xCreateAction($cloudLocation, $availabilityZone, $size, $snapshotId = null, $encrypted = false,
                                  $kmsKeyId = null, $type = 'standard', $iops = null,
                                  $serverId = null, $mount = false, $mountPoint = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_VOLUMES, Acl::PERM_AWS_VOLUMES_MANAGE);

        $aws = $this->getEnvironment()->aws($cloudLocation);

        $decision = $this->getFilteringDecision();

        $validator = new Validator();

        if (empty($serverId) && $decision->mode == CloudResourceScopeMode::MODE_MANAGED_FARMS) {
            $validator->addError('serverId', 'Your permissions do not allow you to create EBS volume without attaching it to the Server.');
        }

        if ($encrypted && $kmsKeyId) {
            $kmsKey = $aws->kms->key->describe($kmsKeyId);
            if (!$kmsKey->enabled) {
                $validator->addError('kmsKeyId', 'This KMS Key is disabled, please choose another one.');
            }
        }

        if (!$validator->isValid($this->response)) {
            return;
        }

        $req = new CreateVolumeRequestData($availabilityZone, $size);
        if ($snapshotId) {
            $req->snapshotId = $snapshotId;
        }

        if ($encrypted) {
            $req->encrypted = true;
            if ($kmsKeyId) {
                $req->kmsKeyId = $kmsKeyId;
            }
        }

        if (in_array($type, [CreateVolumeRequestData::VOLUME_TYPE_STANDARD, CreateVolumeRequestData::VOLUME_TYPE_GP2, CreateVolumeRequestData::VOLUME_TYPE_IO1])) {
            $req->volumeType = $type;
            if ($type == CreateVolumeRequestData::VOLUME_TYPE_IO1) {
                $req->iops = $iops ? $iops : 100;
            }
        }

        $info = $aws->ec2->volume->create($req);

        if ($serverId) {
            //TODO You can attach only volume in available state
            $attempts = 6;

            while ($info->status != 'available' && $attempts--) {
                sleep(2);
                $info = $aws->ec2->volume->describe($info->volumeId)->get(0);
            }

            $this->attachVolumeToServer($info);
        } else {
            //Creates tags for the AWS resource
            $aws->ec2->tag->create($info->volumeId, $this->getEnvironment()->getAwsTags());
        }

        $this->response->success('EBS volume has been successfully created');
        $this->response->data(array('data' => array('volumeId' => $info->volumeId)));
    }

    public function xRemoveAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_AWS_VOLUMES, Acl::PERM_AWS_VOLUMES_MANAGE);

        $aws = $this->getEnvironment()->aws($this->getParam('cloudLocation'));

        $filteringDecision = $this->getFilteringDecision();

        $this->request->defineParams(array(
            'volumeId' => array('type' => 'json'),
            'cloudLocation'
        ));

        $cnt = 0;

        foreach ($this->getParam('volumeId') as $volumeId) {
            //Describes if the volume exists and checks user access permissions
            $decision = $this->getFilteringDecision();

            //We should check access permission to the Volume as well as to the Server to which this volume may be attached
            $this->describeVolume($this->getParam('cloudLocation'), $volumeId);

            if ($aws->ec2->volume->delete($volumeId)) {
                $cnt++;
                $this->db->Execute("DELETE FROM ec2_ebs WHERE volume_id=?", array($volumeId));
            }
        }

        $this->response->success(sprintf('Volume%s been successfully removed.', ($cnt > 1 ? 's have' : ' has')));
    }

    /**
     * List volumes
     *
     * @param string $cloudLocation          The location of a cloud
     * @param string $volumeId      optional Volume ID
     * @param int    $farmId        optional Farm ID
     * @param int    $farmRoleId    optional Role ID tied to the farm
     */
    public function xListVolumesAction($cloudLocation, $volumeId = null, $farmId = null, $farmRoleId = null)
    {
        $filter = [];

        $filterFields = ["instanceId", "volumeId", "snapshotId", "availZone", "type"];

        $env = $this->getEnvironment();

        $aws = $env->aws($cloudLocation);

        if (!empty($volumeId)) {
            $filter = [[
                "name"  => VolumeFilterNameType::volumeId(),
                "value" => $volumeId,
            ]];
        }

        $filteringDecision = $this->request->getCloudResourceFilteringDecision(
            Acl::RESOURCE_AWS_VOLUMES, SERVER_PLATFORMS::EC2, !empty($farmId) ? $farmId : null
        );

        if ($filteringDecision->emptySet) {
            //This user hasn't any managed Farm. We should return empty result set.
            $response = $this->buildResponseFromData([], $filterFields);

            return $this->response->data($response);
        } elseif (!empty($filteringDecision->filter)) {
            $filter = array_merge($filter, $filteringDecision->filter);
        }

        // Rows
        $startTime = microtime(true);
        $volumeList = $aws->ec2->volume->describe(null, (empty($filter) ? null : $filter));
        $describeTime = round(microtime(true) - $startTime) * 1000;

        $startTime = microtime(true);
        $vols = [];

        $needFilter = !empty($farmId) || !empty($farmRoleId);

        foreach ($volumeList as $pv) {
            /* @var $pv VolumeData */
            /* @var $att AttachmentSetResponseData */
            $att = count($pv->attachmentSet) ? $pv->attachmentSet[0] : null;

            $tags = [];
            $scalrMetaTag = null;
            foreach ($pv->tagSet as $tag) {
                /* @var $tag ResourceTagSetData */
                $tg = "{$tag->key}";

                if ($tag->value) {
                    $tg .= "={$tag->value}";
                }

                if ($tag->key == Scalr_Governance::SCALR_META_TAG_NAME) {
                    $scalrMetaTag = $tag->value;
                }

                $tags[] = $tg;
            }

            if (!$filteringDecision->matchScalrMetaTag($scalrMetaTag)) {
                continue;
            }

            $item = [
                "volumeId"         => $pv->volumeId,
                "size"             => (int) $pv->size,
                "snapshotId"       => $pv->snapshotId,
                "availZone"        => $pv->availabilityZone,
                "type"             => $pv->volumeType,
                "status"           => $pv->status,
                "attachmentStatus" => $att !== null ? $att->status : null,
                "device"           => $att !== null ? $att->device : null,
                "instanceId"       => $att !== null ? $att->instanceId : null,
                "tags"             => implode(",", $tags),
                "encrypted"        => $pv->encrypted,
                "kmsKeyId"         => $pv->kmsKeyId
            ];

            if (!empty($item["instanceId"])) {
                try {
                    if (isset($this->listCache["instances"][$item["instanceId"]])) {
                        $dbServer = $this->listCache["instances"][$item["instanceId"]];
                    } else {
                        $dbServer = DBServer::LoadByPropertyValue(EC2_SERVER_PROPERTIES::INSTANCE_ID, $item["instanceId"]);
                        $this->listCache["instances"][$item["instanceId"]] = $dbServer;
                    }

                    if ($dbServer) {
                        $item["farmId"]      = $dbServer->farmId;
                        $item["farmRoleId"]  = $dbServer->farmRoleId;
                        $item["serverIndex"] = $dbServer->index;
                        $item["serverId"]    = $dbServer->serverId;
                        $item["mountStatus"] = false;

                        if (isset($this->listCache["farms"][$item["farmId"]])) {
                            $item["farmName"] = $this->listCache["farms"][$item["farmId"]];
                        } else {
                            $item["farmName"] = $dbServer->GetFarmObject()->Name;
                            $this->listCache["farms"][$item["farmId"]] = $item["farmName"];
                        }
                        if (isset($this->listCache["farmRoles"][$item["farmRoleId"]])) {
                            $item["roleName"] = $this->listCache["farmRoles"][$item["farmRoleId"]];
                        } else {
                            $item["roleName"] = $dbServer->GetFarmRoleObject()->GetRoleObject()->name;
                            $this->listCache["farmRoles"][$item["farmRoleId"]] = $item["roleName"];
                        }

                        /* Waiting for bugfix on scalarizr side
                        if ($dbServer->IsSupported("2.5.4")) {
                            $item["mounts"] = $dbServer->scalarizr->system->mounts();
                        }
                        */
                    }
                } catch (\Exception $e) {
                }
            }

            if ($needFilter === true) {
                foreach (["farmId", "farmRoleId"] as $var) {
                    if (! empty($$var) && (! isset($item[$var]) || $item[$var] != $$var)) {
                        continue 2;
                    }
                }
            }

            $vols[] = $item;
        }

        $volumesTime = round(microtime(true) - $startTime) * 1000;

        $startTime = microtime(true);
        $response = $this->buildResponseFromData($vols, $filterFields);
        foreach($response["data"] as &$item) {
            $item["autoSnaps"] = (bool) $this->db->GetOne(
                "SELECT id FROM autosnap_settings WHERE objectid=? AND object_type=? LIMIT 1",
                [$item["volumeId"], \AUTOSNAPSHOT_TYPE::EBSSnap]
            );
        }

        $responseTime = round(microtime(true) - $startTime) * 1000;

        $response["performanceMeasurements"] = [
            "describe" => $describeTime,
            "volumes"  => $volumesTime,
            "response" => $responseTime,
        ];

        $this->response->data($response);
    }
}
