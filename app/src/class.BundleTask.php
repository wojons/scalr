<?php

use Scalr\Model\Entity\Image;

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

    public $createdById;
    public $createdByEmail;

    public $osFamily;
    public $osName;
    public $osVersion;

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
        'os_family'		=> 'osFamily',
        'os_name'		=> 'osName',
        'os_version'	=> 'osVersion',
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
        $os = $this->getOsDetails();
        $snapshot = $this->getSnapshotDetails();

        $image = new Image();
        $image->id = $this->snapshotId;
        $image->envId = $this->envId;
        $image->bundleTaskId = $this->id;
        $image->platform = $this->platform;
        $image->cloudLocation = $this->cloudLocation;
        $image->os = $os->name;
        $image->osFamily = $os->family;
        $image->osGeneration = $os->generation;
        $image->osVersion = $os->version;
        $image->createdById = $this->createdById;
        $image->createdByEmail = $this->createdByEmail;
        $image->architecture = is_null($snapshot['os']->arch) ? 'x86_64' : $snapshot['os']->arch;
        $image->source = Image::SOURCE_BUNDLE_TASK;
        $image->status = Image::STATUS_ACTIVE;
        $image->agentVersion = $snapshot['szr_version'];

        $image->checkImage();
        if (!$image->name)
            $image->name = $this->roleName . '-' . date('YmdHi');

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
                $retval->name = $this->osFamily;
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
                $retval->version = "{$retval->generation}.X";
                $retval->name = "CentOS {$retval->version} Final";
                break;
            case "amazon":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = $this->osVersion;
                $retval->name = "Amazon Linux {$this->osName}";
                break;
            case "gcel":
                $retval->family = $this->osFamily;
                $retval->generation = $this->osVersion;
                $retval->version = $this->osVersion;
                $retval->name = "GCEL 12.04";
                break;
            case "oel":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = "{$retval->generation}.X";
                $retval->name = "Oracle Enterprise Linux Server {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Tikanga";
                elseif ($retval->generation == 6)
                    $retval->name .= " Santiago";
                break;
            case "redhat":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = "{$retval->generation}.X";
                $retval->name = "Redhat {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Tikanga";
                elseif ($retval->generation == 6)
                    $retval->name .= " Santiago";
                break;
            case "scientific":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = "{$retval->generation}.X";
                $retval->name = "Scientific {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Boron";
                elseif ($retval->generation == 6)
                $retval->name .= " Carbon";
                break;
            case "debian":
                $retval->family = $this->osFamily;
                $retval->generation = (int)substr($this->osVersion, 0, 1);
                $retval->version = "{$retval->generation}.X";
                $retval->name = "Debian {$this->osVersion}";
                if ($retval->generation == 5)
                    $retval->name .= " Lenny";
                elseif ($retval->generation == 6)
                    $retval->name .= " Squeeze";
                elseif ($retval->generation == 7)
                    $retval->name .= " Wheezy";
                break;
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

        $this->createImageEntity();

        $this->Log(sprintf(_("Snapshot creation complete. ImageID: '%s'. Bundle task status changed to: %s"),
            $snapshotId, $this->status
        ));

        $this->Save();
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
}
