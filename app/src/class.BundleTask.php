<?php

use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\Os;
use Scalr\Modules\Platforms\Ec2\Helpers\Ec2Helper;
use Scalr\Model\Entity;
use Scalr\DataType\ScopeInterface;

class ServerSnapshotDetails
{
    public function getOsName()
    {
    }

    public function getSoftwareList()
    {
    }
}

class BundleTask
{
    public $id;
    public $clientId;
    public $serverId;
    public $envId;
    public $replaceType;
    public $prototypeRoleId;
    public $status;
    public $platform;
    public $roleName;
    public $failureReason;
    public $bundleType;
    public $removePrototypeRole; // deprecated, todo: remove
    public $dateAdded;
    public $dateStarted;
    public $dateFinished;
    public $snapshotId;
    public $description;
    public $roleId;
    public $farmId;
    public $cloudLocation;
    public $object;
    public $objectScope;

    public $createdById;
    public $createdByEmail;

    public $osFamily;
    public $osName;
    public $osVersion;
    public $osId;

    /**
     * @var \ADODB_mysqli
     */
    private $Db;

    private $tz;
    private $metaData;

    const BUNDLETASK_OBJECT_ROLE = 'role';
    const BUNDLETASK_OBJECT_IMAGE = 'image';

    /**
     * @var \DBFarm
     */
    private $dbFarm;

    private static $FieldPropertyMap = array(
        'id'			=> 'id',
        'client_id'		=> 'clientId',
        'env_id'		=> 'envId',
        'prototype_role_id'	=> 'prototypeRoleId',
        'server_id' 	=> 'serverId',
        'replace_type' 	=> 'replaceType',
        'status'		=> 'status',
        'platform'		=> 'platform',
        'rolename'		=> 'roleName',
        'failure_reason'=> 'failureReason',
        'remove_proto_role'	=> 'removePrototypeRole',
        'bundle_type'	=> 'bundleType',
        'dtadded'		=> 'dateAdded',
        'dtstarted'		=> 'dateStarted',
        'dtfinished'	=> 'dateFinished',
        'snapshot_id'	=> 'snapshotId',
        'description'	=> 'description',
        'role_id'		=> 'roleId',
        'farm_id'		=> 'farmId',
        'cloud_location'=> 'cloudLocation',
        'meta_data'		=> 'metaData',
        'object'        => 'object',
        'object_scope'  => 'objectScope',
        'os_family'		=> 'osFamily',
        'os_name'		=> 'osName',
        'os_version'	=> 'osVersion',
        'os_id'         => 'osId',
        'created_by_id' => 'createdById',
        'created_by_email' => 'createdByEmail'
    );

    public function __construct($id)
    {
        $this->id = $id;
        $this->Db = \Scalr::getDb();
    }

    /**
     * Gets DBFarm object
     *
     * @return \DBFarm
     */
    public function getFarmObject()
    {
        if (!$this->dbFarm && !empty($this->farmId)) {
            $this->dbFarm = \DBFarm::LoadByID($this->farmId);
        }

        return $this->dbFarm;
    }

    public function Log($message)
    {
        if ($this->id) {
            try {
                $this->Db->Execute("INSERT INTO bundle_task_log SET
                    bundle_task_id	= ?,
                    dtadded			= NOW(),
                    message			= ?
                ", array(
                    $this->id,
                    $message
                ));
            } catch (ADODB_Exception $e) {
            }
        }
    }

    /**
     * Sets a timestamps according to a specified event
     *
     * @param   string   $dt  The name of event (finished | added | started)
     */
    public function setDate($dt)
    {
        switch ($dt) {
            case "finished":
                $this->dateFinished = date("Y-m-d H:i:s");
                break;

            case "added":
                $this->dateAdded = date("Y-m-d H:i:s");
                break;

            case "started":
                if (!$this->dateStarted)
                   $this->dateStarted = date("Y-m-d H:i:s");
                break;
        }
    }

    public static function GenerateRoleName($DBFarmRole, $DBServer)
    {
        $db = \Scalr::getDb();

        $n = $DBFarmRole->GetRoleObject()->name;
        preg_match('/^([A-Za-z0-9-]+)-([0-9]+)-([0-9]+)$/si', $n, $m);
        if ($m[0] == $n) {
            if (date("Ymd") != $m[2]) {
                $name = "{$m[1]}-".date("Ymd")."-01";
                $i = 1;
            } else {
                $s = $m[3]++;
                $i = $s;
                $s = ($s < 10) ? "0{$s}" : $s;
                $name = "{$m[1]}-{$m[2]}-{$s}";
            }
        } else {
            $name = "{$n}-".date("Ymd")."-01";
            $i = 1;
        }

        $role = $db->GetOne("SELECT id FROM roles WHERE name=? AND env_id=? LIMIT 1", array($name, $DBServer->envId));
        if ($role) {
            while ($role) {
                $i++;
                preg_match('/^([A-Za-z0-9-]+)-([0-9]+)-([0-9]+)$/si', $name, $m);
                $s = ($i < 10) ? "0{$i}" : $i;
                $name = "{$m[1]}-{$m[2]}-{$s}";

                $role = $db->GetOne("SELECT id FROM roles WHERE name=? AND env_id=? LIMIT 1", array($name, $DBServer->envId));
            }
        }

        return $name;
    }

    /**
     * @return Image
     */
    public function createImageEntity()
    {
        $snapshot = $this->getSnapshotDetails();

        $image = new Image();
        $image->id = $this->snapshotId;
        $image->accountId = $this->clientId;
        $image->envId = $this->envId;
        $image->bundleTaskId = $this->id;
        $image->platform = $this->platform;
        $image->cloudLocation = $this->cloudLocation;
        $image->createdById = $this->createdById;
        $image->createdByEmail = $this->createdByEmail;
        $image->architecture = is_null($snapshot['os']->arch) ? 'x86_64' : $snapshot['os']->arch;
        $image->source = Image::SOURCE_BUNDLE_TASK;
        $image->status = Image::STATUS_ACTIVE;
        $image->agentVersion = $snapshot['szr_version'];
        $image->isScalarized = 1;
        $image->hasCloudInit = 0;

        $image->checkImage();
        if (!$image->name) {
            $image->name = $this->roleName . '-' . date('YmdHi');
        }

        // before checkImage we should set current envId, so that request to cloud could fill required fields, after that set correct envId
        if ($this->objectScope == ScopeInterface::SCOPE_ACCOUNT) {
            $image->envId = null;
        }

        $image->osId = $this->osId;
        $image->save();

        if ($snapshot['software']) {
            $software = [];
            foreach ((array) $snapshot['software'] as $soft)
                $software[$soft->name] = $soft->version;

            $image->setSoftware($software);
        }

        return $image;
    }

    /**
     * @return ServerSnapshotDetails
     */
    public function getSnapshotDetails()
    {
        return unserialize($this->metaData);
    }

    public function getOsDetails()
    {
        $retval = new stdClass();
        switch ($this->osFamily) {
            case "windows":
                $retval->family = "windows";

                if (strpos($this->osName, '2008Server') === 0)
                    $generation = '2008';
                elseif (strpos($this->osName, '2012Server') === 0)
                    $generation = '2012';

                $retval->generation = $generation;
                $retval->version = $this->osVersion;
                $retval->name = "Windows {$generation}";

                if (substr($this->osName, -2) == 'R2')
                    $retval->name .= " R2";

                break;
            case "ubuntu":
                $retval->family = $this->osFamily;
                $retval->generation = $this->osVersion;
                $retval->version = $this->osVersion;
                $retval->name = "Ubuntu {$retval->version} ".ucfirst($this->osName);
                break;
            case "centos":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = $this->osVersion;
                $retval->name = "CentOS {$retval->version} Final";
                break;
            case "amazon":
                $retval->family = $this->osFamily;
                $retval->generation = $this->osVersion;
                $retval->version = $this->osVersion;
                $retval->name = "Amazon Linux {$retval->version}";
                break;
            case "oel":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = $this->osVersion;
                $retval->name = "Oracle Enterprise Linux Server {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Tikanga";
                elseif ($retval->generation == 6)
                    $retval->name .= " Santiago";
                break;
            case "redhat":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = $this->osVersion;
                $retval->name = "Redhat {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Tikanga";
                elseif ($retval->generation == 6)
                    $retval->name .= " Santiago";
                break;
            case "scientific":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = $this->osVersion;
                $retval->name = "Scientific {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Boron";
                elseif ($retval->generation == 6)
                $retval->name .= " Carbon";
                break;
            case "debian":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = $this->osVersion;
                $retval->name = "Debian {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Lenny";
                elseif ($retval->generation == 6)
                    $retval->name .= " Squeeze";
                elseif ($retval->generation == 7)
                    $retval->name .= " Wheezy";
                break;
            default:
                $retval->generation = '';
                $retval->version = '';
                $retval->name = $this->osName;
        }

        $osIds = Os::findIdsBy($retval->family, $retval->generation, $retval->version);
        if (count($osIds) > 0) {
            $retval->id = $osIds[0];
        } else {
            $osIds = Os::findIdsBy($retval->family, $retval->generation, NULL);
            if (count($osIds) > 0) {
                $retval->id = $osIds[0];
            } else
                $retval->id = Os::UNKNOWN_OS;
        }

        return $retval;
    }

    public function setMetaData($data)
    {
        $currentMetaData = $this->getSnapshotDetails();
        $data = array_merge((array)$currentMetaData, $data);

        $this->metaData = serialize($data);
    }

    public function SnapshotCreationComplete($snapshotId, $metaData=array())
    {
        $this->snapshotId = $snapshotId;
        $this->status = SERVER_SNAPSHOT_CREATION_STATUS::CREATING_ROLE;
        $this->setMetaData($metaData);

        if (!$this->osId) {
            $os = $this->getOsDetails();
            $this->osId = $os->id;
        }

        $this->createImageEntity();

        $this->Log(sprintf(_("Snapshot creation complete. ImageID: '%s'. Bundle task status changed to: %s"),
            $snapshotId, $this->status
        ));

        $this->Save();

        if ($this->platform == \SERVER_PLATFORMS::EC2) {
            Ec2Helper::createObjectTags($this);
        }
    }

    public function SnapshotCreationFailed($failed_reason)
    {
        $this->status = SERVER_SNAPSHOT_CREATION_STATUS::FAILED;

        $this->failureReason = $failed_reason;

        try {
            $dbServer = DBServer::LoadByID($this->serverId);

            //Terminate server
            if ($dbServer->status == SERVER_STATUS::TEMPORARY) {
                try {
                    if (!$dbServer->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_LEAVE_ON_FAIL) && $dbServer->GetCloudServerID()) {
                        $this->Log(sprintf(_("Terminating temporary server...")));
                        $dbServer->terminate(DBServer::TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER);
                        $this->Log(_("Termination request has been sent"));
                    }
                } catch (Exception $e) {
                }
            }

            if ($dbServer->status == SERVER_STATUS::IMPORTING) {
                $this->Log(sprintf(_("Removing import server record from database...")));
                $dbServer->Remove();
            }
        } catch (Exception $e) {
            $this->Log(sprintf(_("SnapshotCreationFailed raised error: %s"), $e->getMessage()));
        }

        $this->Log(sprintf(_("Snapshot creation failed. Reason: %s. Bundle task status changed to: %s"), $failed_reason, $this->status));

        $this->Save();
    }

    private function Unbind()
    {
        $row = array();
        foreach (self::$FieldPropertyMap as $field => $property) {
            $row[$field] = $this->{$property};
        }

        return $row;
    }

    function Save()
    {
        $row = $this->Unbind();
        unset($row['id']);

        // Prepare SQL statement
        $set = array();
        $bind = array();
        foreach ($row as $field => $value) {
            $set[] = "`$field` = ?";
            $bind[] = $value;
        }

        $set = join(', ', $set);

        try	{
            // Perform Update
            $bind[] = $this->id;
            $this->Db->Execute("UPDATE bundle_tasks SET $set WHERE id = ?", $bind);
        } catch (Exception $e) {
            throw new Exception ("Cannot save bundle task. Error: " . $e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param  ServerSnapshotCreateInfo $ServerSnapshotCreateInfo
     * @return BundleTask
     */
    public static function Create(ServerSnapshotCreateInfo $ServerSnapshotCreateInfo, $isRoleBuilder = false)
    {
        $db = \Scalr::getDb();

        $db->Execute("INSERT INTO bundle_tasks SET
            client_id	= ?,
            env_id		= ?,
            server_id	= ?,
            farm_id		= ?,
            prototype_role_id	= ?,
            replace_type		= ?,
            status		= ?,
            platform	= ?,
            rolename	= ?,
            description	= ?,
            object = ?,
            object_scope = ?,
            cloud_location = ?
        ", array(
            $ServerSnapshotCreateInfo->DBServer->clientId,
            $ServerSnapshotCreateInfo->DBServer->envId,
            $ServerSnapshotCreateInfo->DBServer->serverId,
            $ServerSnapshotCreateInfo->DBServer->farmId,
            $ServerSnapshotCreateInfo->DBServer->farmRoleId ? $ServerSnapshotCreateInfo->DBServer->GetFarmRoleObject()->RoleID : 0,
            $ServerSnapshotCreateInfo->replaceType,
            (!$isRoleBuilder) ? SERVER_SNAPSHOT_CREATION_STATUS::PENDING : SERVER_SNAPSHOT_CREATION_STATUS::STARING_SERVER,
            $ServerSnapshotCreateInfo->DBServer->platform,
            $ServerSnapshotCreateInfo->roleName,
            $ServerSnapshotCreateInfo->description,
            $ServerSnapshotCreateInfo->object,
            'environment', // default value
            $ServerSnapshotCreateInfo->DBServer->GetCloudLocation()
        ));

        $bundleTaskId = $db->Insert_Id();

        $task = self::LoadById($bundleTaskId);

        $metaData = array();
        if ($ServerSnapshotCreateInfo->rootBlockDeviceProperties)
            $metaData['rootBlockDeviceProperties'] = $ServerSnapshotCreateInfo->rootBlockDeviceProperties;

        $task->setMetaData($metaData);
        $task->setDate('added');

        $task->save();

        $task->Log(sprintf(_("Bundle task created. ServerID: %s, FarmID: %s, Platform: %s."),
            $ServerSnapshotCreateInfo->DBServer->serverId,
            ($ServerSnapshotCreateInfo->DBServer->farmId) ? $ServerSnapshotCreateInfo->DBServer->farmId : '-',
            $ServerSnapshotCreateInfo->DBServer->platform
        ));

        $task->Log(sprintf(_("Bundle task status: %s"),
            $task->status
        ));

        if ($task->status == SERVER_SNAPSHOT_CREATION_STATUS::PENDING) {
            //TODO:
        }
        else {
            $task->Log(sprintf(_("Waiting for server...")));
        }

        return $task;
    }

    /**
     *
     * @param integer $id
     * @return BundleTask
     */
    public static function LoadById($id)
    {
        $db = \Scalr::getDb();

        $taskinfo = $db->GetRow("SELECT * FROM bundle_tasks WHERE id=?", array($id));
        if (!$taskinfo)
            throw new Exception(sprintf(_("Bundle task ID#%s not found in database"), $id));

        $task = new BundleTask($id);
        foreach (self::$FieldPropertyMap as $k => $v) {
            if (isset($taskinfo[$k]))
                $task->{$v} = $taskinfo[$k];
        }

        return $task;
    }

    /**
     * Cancels obsolete tasks
     *
     * @param int $limit
     *
     * @return int Returns number of cancelled tasks
     */
    public static function failObsoleteTasks($limit = null)
    {
        $db = \Scalr::getDb();

        $limit = $limit ? "LIMIT {$limit}" : '';

        $db->Execute("UPDATE `bundle_tasks` SET `status` = ? WHERE `dtadded` < NOW() - INTERVAL 3 DAY AND `status` NOT IN (?, ?, ?) {$limit};", [
                SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
                SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
                SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
                SERVER_SNAPSHOT_CREATION_STATUS::CANCELLED
        ]);

        return $db->Affected_Rows();
    }

    /**
     * Designates the type of the bundle task
     *
     * It sets the type of bundle task to ec2.ebs-hvm to bundle in AWS way
     * according to the OS version
     *
     * @param    string   $platform    The name of the cloud platform
     * @param    string   $family      OS family
     * @param    string   $generation  optional OS generation. If generation is not provided it will use version instead.
     * @param    string   $version     optional OS version. If generation is not provided it will use version instead.
     */
    public function designateType($platform, $family, $generation = null, $version = '')
    {
        if ($platform == SERVER_PLATFORMS::EC2) {
            switch (true) {
                case in_array($family, ['redhat', 'oel', 'scientific']) :
                case $family == 'centos' && ($generation == '7' || strpos($version, '7') === 0) :
                case $family == 'debian' && ($generation == '8' || strpos($version, '8') === 0) :
                case $family == 'amazon' && ($generation == '2014.09' || $version == '2014.09') :
                    $this->bundleType = SERVER_SNAPSHOT_CREATION_TYPE::EC2_EBS_HVM;
                    break;
            }
        }
    }

    /**
     * @return Image
     */
    public function getImageEntity()
    {
        return Image::findOne([
            ['id'            => $this->snapshotId],
            ['envId'         => $this->envId],
            ['platform'      => $this->platform],
            ['cloudLocation' => in_array($this->platform, [SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::AZURE]) ? '' : $this->cloudLocation]
        ]);
    }

    /**
     * Check if given name is already used in any running bundletask of this account or environment
     *
     * @param   string  $name       Name of Role
     * @param   int     $accountId  Identifier of Account
     * @param   int     $envId      Identifier of Environment
     * @return  int|bool            Returns identifier of the Active BundleTask that matches the specified criteria or false otherwise
     */
    public static function getActiveTaskIdByName($name, $accountId, $envId)
    {
        return Scalr::getDb()->GetOne("
            SELECT id
            FROM bundle_tasks
            WHERE rolename = ?
            AND object = ?
            AND (client_id = ? AND object_scope = ? OR env_id = ? AND object_scope = ?)
            AND status NOT IN (?, ?)
        ", [
            $name,
            self::BUNDLETASK_OBJECT_ROLE,
            $accountId,
            ScopeInterface::SCOPE_ACCOUNT,
            $envId,
            ScopeInterface::SCOPE_ENVIRONMENT,
            SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
            SERVER_SNAPSHOT_CREATION_STATUS::FAILED
        ]);
    }

    /**
     * Check if there any running bundletask that will affect given role
     *
     * @param   int     $roleId     Identifier of Role
     * @param   int     $envId      Identifier of Account
     * @param   string  $object     Object of BundleTask (role, image)
     * @return  int|bool            Returns identifier of the Active BundleTask that matches the specified criteria or false otherwise
     */
    public static function getActiveTaskIdByRoleId($roleId, $envId, $object)
    {
        return Scalr::getDb()->GetOne("
            SELECT id
            FROM bundle_tasks
            WHERE prototype_role_id = ?
            AND env_id = ?
            AND object = ?
            AND status NOT IN (?,?)
            AND replace_type IN(?,?)
        ", [
            $roleId,
            $envId,
            $object,
            SERVER_SNAPSHOT_CREATION_STATUS::SUCCESS,
            SERVER_SNAPSHOT_CREATION_STATUS::FAILED,
            SERVER_REPLACEMENT_TYPE::REPLACE_ALL,
            SERVER_REPLACEMENT_TYPE::REPLACE_FARM
        ]);
    }
}
