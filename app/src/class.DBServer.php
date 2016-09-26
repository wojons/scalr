<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\StatusAdapterInterface;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Model\Entity\SshKey;
use Scalr\Model\Entity;
use Scalr\Modules\Platforms\Azure\Helpers\AzureHelper;
use Scalr\DataType\ScopeInterface;
use Scalr\System\Http\Client\Request;

/**
 * Core Server object
 *
 * @author   Igor Savchenko  <igor@scalr.com>
 * @since    18.09.2012
 *
 * @deprecated
 * @see Scalr\Model\Entity\Server
 *
 * @property-read  Scalr_Net_Scalarizr_Client        $scalarizr        Scalarizr API client
 * @property-read  Scalr_Net_Scalarizr_UpdateClient  $scalarizrUpdateClient  Scalarizr Update API client
 */
class DBServer
{
    const TERMINATE_REASON_SHUTTING_DOWN_CLUSTER = 1;
    const TERMINATE_REASON_REMOVING_REPLICA_SET_FROM_CLUSTER = 2;
    //FOR FUTURE USE: const TERMINATE_REASON_ = 3;
    const TERMINATE_REASON_ROLE_REMOVED = 4;
    const TERMINATE_REASON_SERVER_DID_NOT_SEND_EVENT = 5;
    //FOR FUTURE USE: const TERMINATE_REASON_ = 6;
    const TERMINATE_REASON_TEMPORARY_SERVER_ROLE_BUILDER = 7;
    const TERMINATE_REASON_SCALING_DOWN = 8;
    const TERMINATE_REASON_SNAPSHOT_CANCELLATION = 9;
    const TERMINATE_REASON_MANUALLY = 10;
    const TERMINATE_REASON_MANUALLY_API = 11;
    //FOR FUTURE USE: const TERMINATE_REASON_ = 12;
    const TERMINATE_REASON_FARM_TERMINATED = 13;

    /**
     * @deprecated
     * No longer used by scalr
     */
    const TERMINATE_REASON_REPLACE_SERVER_FROM_SNAPSHOT = 14;
    const TERMINATE_REASON_OPERATION_CANCELLATION = 15;
    const TERMINATE_REASON_CRASHED = 16;

    /**
     * @deprecated
     * No longer used by scalr
     */
    const LAUNCH_REASON_REPLACE_SERVER_FROM_SNAPSHOT = 1;

    const LAUNCH_REASON_SCALING_UP = 2;
    const LAUNCH_REASON_FARM_LAUNCHED = 3;
    const LAUNCH_REASON_MANUALLY_API = 4;
    const LAUNCH_REASON_MANUALLY = 5;
    const LAUNCH_REASON_IMPORT = 6;


    public $serverId;

    public $farmId;

    public $farmRoleId;

    public $envId;

    public $clientId;

    public $platform;

    public $status;

    public $remoteIp;

    public $localIp;

    public $dateAdded;

    public $dateInitialized;

    public $dateShutdownScheduled;

    public $dateRebootStart;

    public $lastSync;

    public $osType = 'linux';

    public $index;

    public $cloudLocation;

    public $cloudLocationZone;

    public $imageId;

    public $instanceTypeName;

    public $operations;

    public $isScalarized = 1;

    public $farmIndex;


    private $platformProps;

    private $realStatus;

    private $cloudServerID;

    private $type;

    private $Db;

    private $dbId;

    private $globalVariablesCache = [];

    private $propsCache = [];

    private $environment;

    private $client;

    private $dbFarmRole;

    private $dbFarm;

    /**
     * Server history instance
     *
     * @var \Scalr\Model\Entity\Server\History
     */
    private $serverHistory;

    public static $platformPropsClasses = array(
        SERVER_PLATFORMS::EC2 => 'EC2_SERVER_PROPERTIES',

        SERVER_PLATFORMS::CLOUDSTACK => 'CLOUDSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::IDCF => 'CLOUDSTACK_SERVER_PROPERTIES',

        SERVER_PLATFORMS::GCE => 'GCE_SERVER_PROPERTIES',
        SERVER_PLATFORMS::AZURE => 'AZURE_SERVER_PROPERTIES',

        SERVER_PLATFORMS::OPENSTACK => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::RACKSPACENG_UK => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::RACKSPACENG_US => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::OCS => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::NEBULA => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::MIRANTIS => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::VIO => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::VERIZON => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::CISCO => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::HPCLOUD => 'OPENSTACK_SERVER_PROPERTIES'
    );

    private static $FieldPropertyMap = array(
        'id'            => 'dbId',
        'server_id'     => 'serverId',
        'env_id'        => 'envId',
        'farm_id'       => 'farmId',
        'farm_roleid'   => 'farmRoleId',
        'client_id'     => 'clientId',
        'platform'      => 'platform',
        'status'        => 'status',
        'os_type'       => 'osType',
        'remote_ip'     => 'remoteIp',
        'local_ip'      => 'localIp',
        'dtadded'       => 'dateAdded',
        'dtinitialized' => 'dateInitialized',
        'dtshutdownscheduled'   => 'dateShutdownScheduled',
        'dtrebootstart' => 'dateRebootStart',
        'index'         => 'index',
        'farm_index'    => 'farmIndex',
        'cloud_location'        => 'cloudLocation',
        'cloud_location_zone'   => 'cloudLocationZone',
        'image_id'          => 'imageId',
        'is_scalarized' => 'isScalarized',
        'type'          => 'type',
        'instance_type_name' => 'instanceTypeName',
        'dtlastsync'    => 'lastSync'
    );

    private $tmpFiles = array();

    const PORT_API = \SERVER_PROPERTIES::SZR_API_PORT;
    const PORT_CTRL = \SERVER_PROPERTIES::SZR_CTRL_PORT;
    const PORT_SNMP = \SERVER_PROPERTIES::SZR_SNMP_PORT;
    const PORT_UPDC = \SERVER_PROPERTIES::SZR_UPDC_PORT;
    const PORT_SSH = \SERVER_PROPERTIES::CUSTOM_SSH_PORT;

    const TERM_STRATEGY_TERMINATE = 'terminate';
    const TERM_STRATEGY_SUSPEND = 'suspend';

    public function __sleep()
    {
        return array_values(self::$FieldPropertyMap);
    }

    public function __construct($serverId)
    {
        $this->serverId = $serverId;
        $this->Db = \Scalr::getDb();
    }

    public function __destruct() {
        if (count($this->tmpFiles) > 0)
            foreach ($this->tmpFiles as $file)
                @unlink($file);
    }

    /**
     * Gets array of DBServer public fields and db columns
     *
     * @return array
     */
    public static function getFieldPropertyMap()
    {
        return self::$FieldPropertyMap;
    }

    public function getNameByConvention()
    {
        $displayConvention = Scalr::config('scalr.ui.server_display_convention');
        $name = "#{$this->index}: ";

        if ($displayConvention == 'hostname')
            $name .= $this->GetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME);
        elseif (($displayConvention == 'auto' && $this->remoteIp) || $displayConvention == 'public')
            $name .= $this->remoteIp;
        elseif ($this->localIp)
            $name .= $this->localIp;

        return $name;
    }

    public function getSzrHost()
    {
        $config = \Scalr::getContainer()->config;
        $instancesConnectionPolicy = $config->defined("scalr.{$this->platform}.instances_connection_policy") ? $config("scalr.{$this->platform}.instances_connection_policy") : null;
        if ($instancesConnectionPolicy === null)
            $instancesConnectionPolicy = $config('scalr.instances_connection_policy');

        if ($instancesConnectionPolicy == 'local') {
            $requestHost = $this->localIp;
        } elseif ($instancesConnectionPolicy == 'public') {
            $requestHost = $this->remoteIp;
        } elseif ($instancesConnectionPolicy == 'auto') {
            if ($this->remoteIp) {
                $requestHost = $this->remoteIp;
            } else {
                $requestHost = $this->localIp;
            }
        }

        return $requestHost;
    }

    public function getPort($portType)
    {
        $port = $this->GetProperty($portType);
        if (!$port) {
            switch ($portType) {
                case self::PORT_API:
                    $port = 8010;
                    break;
                case self::PORT_CTRL:
                    $port = 8013;
                    break;
                case self::PORT_SNMP:
                    $port = 8014;
                    break;
                case self::PORT_UPDC:
                    $port = 8008;
                    break;
                case self::PORT_SSH:
                    $port = 22;
                    break;
            }
        }

        return $port;
    }

    public function isOpenstack()
    {
        return PlatformFactory::isOpenstack($this->platform);
    }

    public function isCloudstack()
    {
        return PlatformFactory::isCloudstack($this->platform);
    }

    /**
     * Gets terminate reason
     *
     * @deprecated
     * @see \Scalr\Model\Entity\Server::getTerminateReason()
     *
     * @param int $reasonId Reason id
     * @return string
     * @throws Exception
     */
    public static function getTerminateReason($reasonId)
    {
        $reasons = [
            1 => 'Shutting-down %s cluster.',
            2 => 'Removing replica set from %s cluster.',
            3 => 'Farm role does not exist.',
            4 => 'Role removed from farm.',
            5 => 'Server did not send %s event in %s seconds after launch.',
            6 => 'Terminating temporary server.',
            7 => 'Terminating role builder temporary server.',
            8 => 'Scaling down.',
            9 => 'Snapshot cancellation.',
            10 => 'Manually terminated by %s.',
            11 => 'Terminated through the Scalr API by %s.',
            12 => 'Farm was in "%s" state. Server terminated when bundle task has been finished. Bundle task #%s.',
            13 => 'Terminating server because the farm has been terminated.',
            14 => 'Server replaced with new one after snapshotting.',
            15 => 'Server launch was canceled',
            16 => 'Server was terminated in cloud or from within an OS'
        ];

        if ($reasonId && !isset($reasons[$reasonId])) {
            throw new Exception(sprintf('Terminate reason %d doesn\'t have message', $reasonId));
        }

        return $reasonId ? $reasons[$reasonId] : '';
    }

    public static function getLaunchReason($reasonId)
    {
        $reasons = [
            1 => 'Server replacement after snapshotting',
            2 => 'Scaling up',
            3 => 'Farm launched',
            4 => 'API Request',
            5 => 'Manually launched using UI',
            6 => 'Manually imported'
        ];

        if ($reasonId && !isset($reasons[$reasonId])) {
            throw new Exception(sprintf('Launch reason %d doesn\'t have message', $reasonId));
        }

        return $reasonId ? $reasons[$reasonId] : '';
    }

    /**
     * @return Scalr_Net_Ssh2_Client
     * Enter description here ...
     */
    public function GetSsh2Client()
    {
        $ssh2Client = new Scalr_Net_Ssh2_Client();

        switch($this->platform) {

            case SERVER_PLATFORMS::RACKSPACENG_UK:
            case SERVER_PLATFORMS::RACKSPACENG_US:
                $ssh2Client->addPassword(
                    'root',
                    $this->GetProperty(OPENSTACK_SERVER_PROPERTIES::ADMIN_PASS)
                );
                break;

            case SERVER_PLATFORMS::GCE:

                $userName = 'scalr';

                 if ($this->status == SERVER_STATUS::TEMPORARY) {
                    $keyName = 'SCALR-ROLESBUILDER-'.SCALR_ID;
                }
                else {
                    $keyName = "FARM-{$this->farmId}-".SCALR_ID;
                }

                try {
                    $key = (new SshKey())->loadGlobalByName(
                        $this->envId,
                        SERVER_PLATFORMS::GCE,
                        "",
                        $keyName
                    );

                    if (!$key)
                        throw new Exception(_("There is no SSH key for server: {$this->serverId}"));
                }
                catch(Exception $e){
                    throw new Exception("Cannot init SshKey object: {$e->getMessage()}");
                }

                $priv_key_file = tempnam("/tmp", "GCEPK");
                @file_put_contents($priv_key_file, $key->privateKey);
                $this->tmpFiles[] = $priv_key_file;

                $pub_key_file = tempnam("/tmp", "GCEK");
                @file_put_contents($pub_key_file, $key->publicKey);
                $this->tmpFiles[] = $pub_key_file;

                $ssh2Client->addPubkey($userName, $pub_key_file, $priv_key_file);

                 break;

            case SERVER_PLATFORMS::IDCF:
            case SERVER_PLATFORMS::EC2:

                $userName = 'root';
                $skipKeyValidation = false;

                // Temporary server for role builder
                $sshKey = new SshKey();
                if ($this->status == SERVER_STATUS::TEMPORARY) {
                    $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID . "-{$this->envId}";
                    if (!$sshKey->loadGlobalByName(
                        $this->envId,
                        $this->platform,
                        $this->GetCloudLocation(),
                        $keyName
                    ))
                        $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID;

                    try {
                        $bundleTaskId = $this->GetProperty(\SERVER_PROPERTIES::SZR_IMPORTING_BUNDLE_TASK_ID);
                        $bundleTask = BundleTask::LoadById($bundleTaskId);
                        if ($bundleTask->osFamily == 'amazon') {
                            $userName = 'ec2-user';
                        }
                    } catch (Exception $e) {}
                } else {
                    $keyName = "FARM-{$this->farmId}-".SCALR_ID;
                    $oldKeyName = "FARM-{$this->farmId}";
                    $key = $sshKey->loadGlobalByName(
                        $this->envId,
                        $this->platform,
                        $this->GetCloudLocation(),
                        $oldKeyName
                    );

                    if ($key) {
                        $keyName = $oldKeyName;
                        $skipKeyValidation = true;
                    }
                }

                if (!$skipKeyValidation) {
                    try {
                        $key = $sshKey->loadGlobalByName(
                            $this->envId,
                            $this->platform,
                            $this->GetCloudLocation(),
                            $keyName
                        );

                        if (!$key) {
                            throw new Exception(sprintf(
                                'Could not find SSH Key for server "%s" with name:"%s", cloud-location:"%s", platform:"%s", environment:"%d".',
                                $this->serverId,
                                $keyName,
                                $this->GetCloudLocation(),
                                $this->platform,
                                $this->envId
                            ));
                        }
                    }
                    catch(Exception $e){
                        throw new Exception("Cannot init SshKey object: {$e->getMessage()}");
                    }
                }

                $priv_key_file = tempnam("/tmp", "AWSK");
                @file_put_contents($priv_key_file, $key->privateKey);
                $this->tmpFiles[] = $priv_key_file;

                $pub_key_file = tempnam("/tmp", "AWSK");
                $this->tmpFiles[] = $pub_key_file;

                $pubKey = $key->publicKey;
                if (!stristr($pubKey, $keyName))
                    $pubKey .= " {$keyName}";

                @file_put_contents($pub_key_file, $pubKey);

                $ssh2Client->addPubkey($userName, $pub_key_file, $priv_key_file);

                break;
         }

        return $ssh2Client;
    }

    public function GetCloudUserData()
    {
        $dbFarmRole = $this->GetFarmRoleObject();

        $baseurl = \Scalr::config('scalr.endpoint.scheme') . "://" .
                   \Scalr::config('scalr.endpoint.host');

        if ($this->isOpenstack() && $this->platform != SERVER_PLATFORMS::VERIZON)
            $platform = SERVER_PLATFORMS::OPENSTACK;
        else
            $platform = $this->platform;


        $retval = array(
            "farmid"            => $this->farmId,
            "role"              => implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
            //"eventhandlerurl" => \Scalr::config('scalr.endpoint.host'),
            "httpproto"         => \Scalr::config('scalr.endpoint.scheme'),
            "region"            => $this->GetCloudLocation(),

            // For Scalarizr
            "hash"                  => $this->GetFarmObject()->Hash,
            "realrolename"          => $dbFarmRole->GetRoleObject()->name,
            "szr_key"               => $this->GetKey(),
            "serverid"              => $this->serverId,
            'p2p_producer_endpoint' => $baseurl . "/messaging",
            'queryenv_url'          => $baseurl . "/query-env",
            'behaviors'             => implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
            'farm_roleid'           => $dbFarmRole->ID,
            'roleid'                => $dbFarmRole->RoleID,
            'env_id'                => $dbFarmRole->GetFarmObject()->EnvID,
            'platform'              => $platform,
            'server_index'          => $this->index,
            'cloud_server_id'       => $this->GetCloudServerID(),
            'cloud_location_zone'   => $this->cloudLocationZone,

            // General information
            "owner_email"           => $dbFarmRole->GetFarmObject()->createdByUserEmail
        );

        $retval['message_format'] = 'json';

        if (PlatformFactory::isOpenstack($this->platform) && $this->platform != SERVER_PLATFORMS::VERIZON) {
            $retval["cloud_storage_path"] = "swift://";
        } else {
            switch($this->platform) {
                case SERVER_PLATFORMS::EC2:

                    $retval["s3bucket"]	= $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_S3_BUCKET);
                    $retval["cloud_storage_path"] = "s3://".$dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_S3_BUCKET);

                    break;

                case SERVER_PLATFORMS::GCE:

                    $retval["cloud_storage_path"] = "gcs://";

                    break;
            }
        }

        // Custom settings
        foreach ($dbFarmRole->GetSettingsByFilter("user-data") as $k=>$v)
            $retval[str_replace("user-data", "custom", $k)] = $v;

        return $retval;
    }

    /**
     * @return string windows | linux
     */
    public function GetOsType()
    {
        return $this->osType;
    }

    public function IsRebooting()
    {
        return $this->GetProperty(\SERVER_PROPERTIES::REBOOTING, true);
    }

    /**
     *
     * Return cloud location (region)
     * @param bool $skipCache
     * @return string
     */
    public function GetCloudLocation()
    {
        if (!$this->cloudLocation)
            $this->cloudLocation = PlatformFactory::NewPlatform($this->platform)->GetServerCloudLocation($this);

        return $this->cloudLocation;
    }

    /**
     *
     * Return real (Cloud) server ID
     * @param bool $skipCache
     * @return string
     */
    public function GetCloudServerID($skipCache = false)
    {
        if (!$this->cloudServerID || $skipCache == true)
            $this->cloudServerID = PlatformFactory::NewPlatform($this->platform)->GetServerID($this);

        return $this->cloudServerID;
    }

    /**
     * Return server flavor (instance type)
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets instance type type
     *
     * @param string $type
     * @return DBServer
     */
    public function setType($type)
    {
        return $this->update(['type' => $type]);
    }

    /**
     * Gets real status
     *
     * @return StatusAdapterInterface
     */
    public function GetRealStatus($skipCache = false)
    {
        if (!$this->realStatus || $skipCache == true)
            $this->realStatus = PlatformFactory::NewPlatform($this->platform)->GetServerRealStatus($this);

        return $this->realStatus;
    }

    /**
     * @return Scalr_Environment
     */
    public function GetEnvironmentObject()
    {
        if (!$this->environment)
            $this->environment = Scalr_Model::init(Scalr_Model::ENVIRONMENT)->loadById($this->envId);

        return $this->environment;
    }

    /**
     *
     * Returns DBFarme object
     * @return DBFarm
     */
    public function GetFarmObject()
    {
        if (!$this->dbFarm)
            $this->dbFarm = DBFarm::LoadByID($this->farmId);

        return $this->dbFarm;
    }

    /**
     *
     * Returns DBFarmRole object
     * @return DBFarmRole
     */
    public function GetFarmRoleObject()
    {
        if (!$this->dbFarmRole)
            $this->dbFarmRole = DBFarmRole::LoadByID($this->farmRoleId);

        return $this->dbFarmRole;
    }

    /**
     *
     * @return Client
     */
    public function GetClient()
    {
        if (!$this->client)
            $this->client = Client::Load($this->clientId);

        return $this->client;
    }

    /**
     * Returns Server authentification key (For messaging and Query-Env)
     * @param bool $plain
     * @return string
     */
    public function GetKey($plain = false)
    {
        $key = $this->GetProperty(\SERVER_PROPERTIES::SZR_KEY, true);

        return ($plain) ? base64_decode($key) : $key;
    }

    public function GetAllProperties()
    {
        $props = $this->Db->GetAll("SELECT * FROM server_properties WHERE server_id=?", array($this->serverId));
        foreach ($props as $prop)
            $this->propsCache[$prop['name']] = $prop['value'];

        return $this->propsCache;
    }

    /**
     * Get Server Property
     * @param string $propertyName
     * @param boolean $ignoreCache
     * @return mixed
     */
    public function GetProperty($propertyName, $ignoreCache = false)
    {
        if (!array_key_exists($propertyName, $this->propsCache) || $ignoreCache) {
            $this->propsCache[$propertyName] = $this->Db->GetOne("
                SELECT value
                FROM server_properties
                WHERE server_id=? AND name=? LIMIT 1
            ", array(
                $this->serverId,
                $propertyName
            ));
        }

        return $this->propsCache[$propertyName];
    }

    /**
     * Set multiple server properties
     *
     * @param    array $props  Associative array of the properties array(property => value)
     */
    public function SetProperties(array $props)
    {
        if (empty($props)) return;

        $values = [];
        $stmt = '';
        foreach ($props as $k => $v) {
            $stmt .= ",(?, ?, ?)";
            $values[] = $this->serverId;
            $values[] = $k;
            $values[] = $v;
        }

        if (empty($stmt)) return;

        $this->Db->Execute("
            REPLACE `server_properties` (server_id, name, value)
            VALUES " . ltrim($stmt, ',') . "
        ", $values);

        foreach ($props as $k => $v) {
            $this->propsCache[$k] = $v;
        }
    }

    /**
     * Set server property
     *
     * @param   string $propertyName
     * @param   mixed $propertyValue
     * @return  void
     */
    public function SetProperty($propertyName, $propertyValue)
    {
        $this->Db->Execute("
            INSERT INTO server_properties
            SET server_id = ?, name = ?, value = ?
            ON DUPLICATE KEY UPDATE
                value = ?
        ", array(
            $this->serverId, $propertyName, $propertyValue,
            $propertyValue,
        ));

        $this->propsCache[$propertyName] = $propertyValue;

        return true;
    }

    /**
     * Updates Server status
     *
     * @param    string    $serverStatus  The status
     * @return   DBServer
     */
    public function updateStatus($serverStatus)
    {
        if (!$this->serverId) {
            throw new RuntimeException(sprintf(
                "Server identifier has not been set in %s object yet.",
                get_class($this)
            ));
        }

        $this->Db->Execute("
            UPDATE servers SET status = ? WHERE server_id = ?
        ", [$serverStatus, $this->serverId]);

        $this->status = $serverStatus;

        return $this;
    }

    /**
     * Updates specified properties to database
     *
     * @param   array|Iterator $props The list of the properties to update
     */
    public function update($props)
    {
        if (!$this->serverId) {
            throw new RuntimeException(sprintf(
                "Server identifier has not been set in %s object yet.",
                get_class($this)
            ));
        }

        $stmt = [];
        foreach ($props as $prop => $value) {
            if (!($column = array_search($prop, static::$FieldPropertyMap))) {
                throw new InvalidArgumentException(sprintf("Invalid property '%s' in the %s object.", $prop, get_class($this)));
            }

            $this->$prop = $value;
            $stmt[] = "`" . $column . "` = " . $this->Db->qstr($value);
        }

        if (!empty($stmt)) {
            $this->Db->Execute("
                UPDATE `servers` SET " . join(", ", $stmt) . " WHERE `server_id`= " . $this->Db->qstr($this->serverId) . "
            ");
        }

        return $this;
    }

    /**
     * Checks scalr inbound request rate (req/min)
     *
     * @param   string $resourseName optional Resourse name
     * @return  int    Returns the number of the requests per minute
     */
    public function checkRequestRate($resourseName = '')
    {
        $key = \SERVER_PROPERTIES::SCALR_INBOUND_REQ_RATE . ($resourseName ? '.' . $resourseName : '');

        $str = $this->GetProperty($key);

        $minute = gmdate('ymdHi');

        if ($str && ($a = explode(' ', $str)) && $a[0] === $minute) {
            $this->SetProperty($key, $a[0] . ' ' . $a[1]++);
            return $a[1];
        }

        $this->SetProperty($key, $minute . ' 1');

        return 1;
    }

    /**
     * Removes server from database
     * @return void
     */
    public function Remove()
    {
        try {

            // 1. Clean up cloud objects
            if ($this->platform == SERVER_PLATFORMS::AZURE) {
                AzureHelper::cleanupServerObjects($this);
            }

            // 2. Cleanup scalr db
            $this->Db->BeginTrans();

            // We need to perpetuate server_properties records for removed servers
            $this->Db->Execute("DELETE FROM servers WHERE server_id=?", array($this->serverId));
            $this->Db->Execute("DELETE FROM messages WHERE server_id=?", array($this->serverId));

            $importantProperties = \SERVER_PROPERTIES::getImportantList();
            $properties = array_diff(array_keys($this->GetAllProperties()), $importantProperties);

            if (!empty($properties)) {
                $and = " AND (";

                foreach ($properties as $name) {
                    $and .= "name=" . $this->Db->qstr($name) . " OR ";
                }

                $and = substr($and, 0, -3) . ")";

                $this->Db->Execute("DELETE FROM server_properties WHERE server_id=?" . $and, [$this->serverId]);
            }

            $this->Db->CommitTrans();
        } catch (Exception $e) {
            $this->Db->RollbackTrans();
            throw $e;
        }
    }

    public static function IsExists($serverId)
    {
        $db = \Scalr::getDb();

        return (bool)$db->GetOne("SELECT id FROM servers WHERE server_id=? LIMIT 1", array($serverId));
    }

    /**
     *
     * @param int $farm_roleid
     * @param int $index
     * @return DBServer
     */
    public static function LoadByFarmRoleIDAndIndex($farm_roleid, $index)
    {
        $db = \Scalr::getDb();

        $server_id = $db->GetOne("SELECT server_id FROM servers WHERE farm_roleid = ? AND `index` = ? AND status != ? LIMIT 1",
            array($farm_roleid, $index, SERVER_STATUS::TERMINATED)
        );

        if (!$server_id)
        {
            throw new Exception(sprintf(
                _("Server with FarmRoleID #%s and index #%s not found in database"),
                $farm_roleid,
                $index
            ));
        }

        return self::LoadByID($server_id);
    }

    public static function LoadByLocalIp($localIp, $farmId)
    {
        $db = \Scalr::getDb();

        $serverId = $db->GetOne("SELECT server_id FROM servers WHERE `local_ip`=? AND `farm_id`=? LIMIT 1", array($localIp, $farmId));

        if (!$serverId)
            throw new \Scalr\Exception\ServerNotFoundException(sprintf("Server with private IP '%s' not found in database", $localIp));

        return self::LoadByID($serverId);
    }

    /**
     * Return DBServer by property value
     * @param string $propName
     * @param string $propValue
     * @return DBServer
     */
    public static function LoadByPropertyValue($propName, $propValue)
    {
        $db = \Scalr::getDb();

        $serverId = $db->GetOne("SELECT server_id FROM server_properties WHERE `name`=? AND `value`=? LIMIT 1", array($propName, $propValue));

        if (!$serverId)
            throw new \Scalr\Exception\ServerNotFoundException(sprintf("Server with property '%s'='%s' not found in database", $propName, $propValue));

        return self::LoadByID($serverId);
    }

    /**
     * Loads server by specified identifier
     *
     * @param  string $serverId
     * @return DBServer
     */
    public static function LoadByID($serverId)
    {
        $db = \Scalr::getDb();

        $serverinfo = $db->GetRow("SELECT * FROM servers WHERE server_id=? LIMIT 1", array($serverId));

        if (!$serverinfo) {
            throw new \Scalr\Exception\ServerNotFoundException(sprintf(_("Server ID#%s not found in database"), $serverId));
        }

        $DBServer = new DBServer($serverId);

        foreach(self::$FieldPropertyMap as $k => $v) {
            if (isset($serverinfo[$k])) {
                $DBServer->{$v} = $serverinfo[$k];
            }
        }

        return $DBServer;
    }

    public static function load($serverinfo)
    {
        $DBServer = new DBServer($serverinfo['server_id']);

        foreach(self::$FieldPropertyMap as $k => $v) {
            if (isset($serverinfo[$k])) {
                $DBServer->{$v} = $serverinfo[$k];
            }
        }

        return $DBServer;
    }

    /**
     * Gets a list of servers by filter
     *
     * @param array $filter optional Filter value ['envId' => 'value']
     * @return array Returns array of DBServer objects
     */
    public static function listByFilter(array $filter = null)
    {
        $result = [];

        $db = \Scalr::getDb();

        $where = "WHERE 1=1";

        if (isset($filter)) {
            foreach ($filter as $name => $value) {
                $fieldName = \Scalr::decamelize($name);
                $where .= " AND " . $fieldName . "=" . $db->qstr($value);
            }
        }

        $servers = $db->GetAll("SELECT * FROM servers " . $where);

        foreach ($servers as $server) {
            $DBServer = new DBServer($server['server_id']);

            foreach (self::$FieldPropertyMap as $k => $v) {
                if (isset($server[$k])) {
                    $DBServer->{$v} = $server[$k];
                }
            }

            $result[] = $DBServer;
        }

        return $result;
    }

    public function GetFreeDeviceName($isHvm = false)
    {
        $list = $this->scalarizr->system->blockDevices();

        if (!$isHvm) {
            $map = array("f", "g", "h", "i", "j", "k", "l", "m", "n", "p");
            $n_map = array("1", "2", "3", "4", "5", "6");
        } else {
            $map = array("f", "g", "h", "i", "j", "k", "l", "m", "n", "p",
                "ba", "bb", "bc", "bd", "be", "bf", "bg",
                "ca", "cb", "cc", "cd", "ce", "cf", "cg",
            );
            $n_map = array("");
        }
        $mapUsed = array();

        foreach ($list as $deviceName) {
            preg_match("/(sd|xvd)([a-z]{1,2}[0-9]*)/", $deviceName, $matches);

            if (!empty($matches[2]) && !in_array($matches[2], $mapUsed))
                array_push($mapUsed, $matches[2]);
        }

        $deviceL = false;
        foreach ($n_map as $v) {
            foreach ($map as $letter) {
                if (in_array($letter, $mapUsed))
                    continue;

                $deviceL = "{$letter}{$v}";
                if (!in_array($deviceL, $mapUsed)) {
                    break;
                } else
                    $deviceL = false;
            }

            if ($deviceL)
                break;
        }

        if (!$deviceL)
            throw new Exception(_("There is no available device letter on instance for attaching EBS"));

        return (strlen($deviceL) == 2 && $isHvm) ? "/dev/xvd{$deviceL}" : "/dev/sd{$deviceL}";
    }

    /**
     *
     * @param ServerCreateInfo $serverCreateInfo
     * @param bool $isImport
     * @return DBServer
     */
    public static function Create(ServerCreateInfo $creInfo, $isImport = false, $setPendingStatus = false)
    {
        $db = \Scalr::getDb();

        $startWithLetter = in_array($creInfo->platform, array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::GCE));

        if ($isImport) {
            $startWithLetter = true;
        }

        $server_id = Scalr::GenerateUID(false, $startWithLetter);

        $status = (!$isImport) ? SERVER_STATUS::PENDING_LAUNCH : SERVER_STATUS::IMPORTING;

        if ($setPendingStatus) {
            $status = SERVER_STATUS::PENDING;
        }

        // Assigns Farm index to the server
        if (!$isImport) {
            // This query select the least lower vacant Farm index from the available.
            // If there are no available indexes the query returns NULL so we need cast result to integer
            // to make sure it will use Farm index equal to 1 in this case.
            // We ingore terminated and pending terminating instances to release their indexes.
            $farmIndex = 1 + intval($db->GetOne("
                SELECT s.farm_index
                FROM servers s
                WHERE s.farm_id = ? AND s.status NOT IN (?, ?)
                AND NOT EXISTS (SELECT 1 FROM servers WHERE farm_id = s.farm_id AND farm_index = s.farm_index + 1 AND status NOT IN (?, ?))
                ORDER BY s.farm_index
                LIMIT 1
            ", [
                $creInfo->farmId ? $creInfo->farmId : $creInfo->dbFarmRole->FarmID,
                SERVER_STATUS::TERMINATED,
                SERVER_STATUS::PENDING_TERMINATE,

                SERVER_STATUS::TERMINATED,
                SERVER_STATUS::PENDING_TERMINATE
            ]));
        } else {
            // Default Farm index value is considered to equal 1
            $farmIndex = 1;
        }

        // IF no index defined
        if (!$creInfo->index && !$isImport) {
            $indexes = $db->GetAll("SELECT `index` FROM servers WHERE farm_roleid = ? AND status NOT IN (?, ?)", [
                $creInfo->dbFarmRole->ID,
                SERVER_STATUS::TERMINATED,
                SERVER_STATUS::PENDING_TERMINATE
            ]);

            $usedIndexes = [];
            if (!empty($indexes)) {
                foreach ($indexes as $index) {
                    $usedIndexes[$index['index']] = true;
                }
            }

            for ($i = 1;;$i++) {
                if (!isset($usedIndexes[$i])) {
                    $creInfo->index = $i;
                    break;
                }
            }
        } elseif ($isImport) {
            $creInfo->index = 0;
        }

        $client_id = $creInfo->clientId ? $creInfo->clientId : $creInfo->dbFarmRole->GetFarmObject()->ClientID;

        $instanceTypeName = null;
        $instanceTypeId = $creInfo->dbFarmRole ? $creInfo->dbFarmRole->getInstanceType() : null;

        if (in_array($creInfo->platform, [SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE])) {
            $instanceTypeName = $instanceTypeId;
        }

        $db->Execute("
            INSERT INTO servers
            SET `server_id` = ?,
                `farm_id` = ?,
                `env_id` = ?,
                `farm_roleid` = ?,
                `client_id` = ?,
                `platform` = ?,
                `status` = ?,
                `remote_ip` = ?,
                `local_ip` = ?,
                `dtadded` = NOW(),
                `index` = ?,
                `farm_index` = ?,
                `cloud_location` = ?,
                `type` = ?,
                `instance_type_name`= ?
        ", [
            $server_id,
            $creInfo->farmId ? $creInfo->farmId : $creInfo->dbFarmRole->FarmID,
            $creInfo->envId,
            $creInfo->dbFarmRole ? $creInfo->dbFarmRole->ID : 0,
            $client_id,
            $creInfo->platform,
            $status,
            $creInfo->remoteIp,
            $creInfo->localIp,
            $creInfo->index,
            $farmIndex,
            $creInfo->dbFarmRole ? $creInfo->dbFarmRole->CloudLocation : null,
            $instanceTypeId,
            $instanceTypeName
        ]);

        $DBServer = DBServer::LoadByID($server_id);
        $DBServer->SetProperties($creInfo->GetProperties());
        $DBServer->setOsType($DBServer->osType);

        try {
            if ($DBServer->farmRoleId) {
                $db->Execute("
                    INSERT INTO servers_launch_timelog
                    SET `server_id` = ?,
                        `os_family` = ?,
                        `os_version` = ?,
                        `cloud` = ?,
                        `cloud_location` = ?,
                        `server_type` = ?,
                        `behaviors` = ?,
                        `ts_created` = ?
                ", [
                    $server_id,
                    $DBServer->GetFarmRoleObject()->GetRoleObject()->getOs()->family,
                    $DBServer->GetFarmRoleObject()->GetRoleObject()->getOs()->version,
                    $DBServer->platform,
                    $DBServer->cloudLocation,
                    $DBServer->getType(),
                    implode(",", $DBServer->GetFarmRoleObject()->GetRoleObject()->getBehaviors()),
                    time()
                ]);
            }
        } catch (Exception $e) {
        }

        return $DBServer;
    }

    public function updateTimelog($pointName, $secondsSinceBoot = null, $secondsSinceStart = null)
    {
        if (!in_array($pointName, array('ts_launched','ts_hi','ts_bhu','ts_hu')))
            return false;

        if ($pointName == 'ts_hi') {
            $this->Db->Execute("UPDATE servers_launch_timelog SET `time_to_boot` = ?, `time_to_hi` = ?, `last_init_status` = ?, `{$pointName}` = ? WHERE server_id=?", array(
                $secondsSinceBoot - $secondsSinceStart,
                $secondsSinceStart,
                $this->status,
                time(),
                $this->serverId
            ));
        } else {
            $this->Db->Execute("UPDATE servers_launch_timelog SET `last_init_status` = ?, `{$pointName}` = ? WHERE server_id=?", array(
                $this->status,
                time(),
                $this->serverId
            ));
        }
    }

    private function Unbind () {
        $row = array();
        foreach (self::$FieldPropertyMap as $field => $property) {
            $row[$field] = $this->{$property};
        }

        return $row;
    }

    function Save ()
    {
        $row = $this->Unbind();
        unset($row['server_id']);
        unset($row['id']);

        // Prepare SQL statement
        $set = [];
        $bind = [];
        foreach ($row as $field => $value) {
            $set[] = "`$field` = ?";
            $bind[] = $value;
        }

        $set = join(', ', $set);

        try {
            if ($this->dbId) {
                // Perform Update
                $bind[] = $this->dbId;
                $this->Db->Execute("UPDATE servers SET $set WHERE id = ?", $bind);
            } else {
                // Perform Insert
                $this->Db->Execute("INSERT INTO servers SET $set", $bind);
                $this->dbId = $this->Db->Insert_ID();
            }
        } catch (Exception $e) {
            throw new Exception ("Cannot save server. Error: " . $e->getMessage(), $e->getCode());
        }
    }

    private function GetVersionInfo($v)
    {
        if (preg_match('/^([0-9]+)\.([0-9]+)[-\.]?[r]*([0-9]+)?$/si', $v, $matches)) {
            // For SVN: 0.7.11 or 0.9.r565 or 0.2-151
            $verInfo = array_map("intval", array_slice($matches, 1));
            while (count($verInfo) < 3) {
                $verInfo[] = 0;
            }
        } elseif (preg_match('/^([0-9]+)\.([0-9]+)\.b([0-9]+)\.[a-z0-9]+$/si', $v, $matches)) {
            // For GIT: 0.13.b500.57a5ab9
            $verInfo = array_map("intval", array_slice($matches, 1));
            while (count($verInfo) < 3) {
                $verInfo[] = 0;
            }
        } elseif (preg_match('/^([0-9]+)\.([0-9]+)\.[0-9]+\.[0-9]+$/si', $v, $matches)) {
            // For Windows dev builds: 3.5.0.6
            $verInfo = array_map("intval", array_slice($matches, 1));
            while (count($verInfo) < 3) {
                $verInfo[] = 0;
            }
        } else {
            $verInfo = [0, 0, 0];
        }

        return $verInfo;
    }

    public function setScalarizrVersion($version)
    {
        if (!preg_match('/^[a-z\d\.-]+$/i', $version))
            return false;

        return $this->SetProperty(\SERVER_PROPERTIES::SZR_VESION, $version);
    }

    /**
     * Return information about scalarizr version installed on instance
     *
     * @return array
     */
    public function GetScalarizrVersion()
    {
        return $this->GetVersionInfo($this->GetProperty(\SERVER_PROPERTIES::SZR_VESION, true));
    }

    public function IsSupported($v)
    {
        return $this->GetScalarizrVersion() >= $this->GetVersionInfo($v);
    }

    /**
     * Send message to instance
     * @param Scalr_Messaging_Msg $message
     * @return Scalr_Messaging_Msg
     */
    public function SendMessage(Scalr_Messaging_Msg $message, $isEventNotice = false, $delayed = false)
    {
        $startTime = microtime(true);

        if (!$this->isScalarized)
            return;

        if ($this->farmId && $message->getName() != 'BeforeHostTerminate') {
            if ($this->GetFarmObject()->Status == FARM_STATUS::TERMINATED) {
                $this->Db->Execute("UPDATE messages SET status = ? WHERE messageid = ?", array(MESSAGE_STATUS::FAILED, $message->messageId));
                return;
            }
        }

        // We don't need to send any messages other then it's own to the server that is not in Running state
        if ($message->serverId != $this->serverId && !in_array($this->status, array(SERVER_STATUS::RUNNING, SERVER_STATUS::TEMPORARY, SERVER_STATUS::IMPORTING))) {
            return;
        }

        // Ignore OLD messages (ami-scripts)
        if (!$this->IsSupported("0.5"))
            return;

        // Put access data and reserialize message
        $pl = PlatformFactory::NewPlatform($this->platform);
        $pl->PutAccessData($this, $message);

        $logger = \Scalr::getContainer()->logger('DBServer');
        $serializer = Scalr_Messaging_XmlSerializer::getInstance();
        $cryptoTool = \Scalr::getContainer()->srzcrypto($this->GetKey(true));

        if ($this->GetProperty(\SERVER_PROPERTIES::SZR_MESSAGE_FORMAT) == 'json') {
            $serializer = Scalr_Messaging_JsonSerializer::getInstance();
            $rawMessage = $serializer->serialize($message);
            $messageType = 'json';
        } else {
            $rawMessage = $serializer->serialize($message);
            $messageType = 'xml';
        }

        //$rawJsonMessage = @json_encode($message);

        $time = microtime(true) - $startTime;

        // Add message to database
        $this->Db->Execute("INSERT INTO messages SET
                `messageid`             = ?,
                `processing_time`       = ?,
                `server_id`             = ?,
                `event_server_id`       = ?,
                `message`               = ?,
                `type`                  = 'out',
                `message_name`          = ?,
                `handle_attempts`       = ?,
                `message_version`       = ?,
                `dtlasthandleattempt`   = UTC_TIMESTAMP(),
                `dtadded`               = NOW(),
                `message_format`        = ?,
                `event_id`              = ?
            ON DUPLICATE KEY UPDATE handle_attempts = handle_attempts+1, dtlasthandleattempt = UTC_TIMESTAMP()
            ", array(
            $message->messageId,
            $time,
            $this->serverId,
            $message->serverId,
            $rawMessage,
            $message->getName(),
            ($delayed) ? '0' : '1',
            2,
            $messageType,
            (isset($message->eventId)) ? $message->eventId : ''
        ));

        if ($delayed)
            return $message;

        $isVPC = false;


        if ($this->farmId)
            if (DBFarm::LoadByID($this->farmId)->GetSetting(Entity\FarmSetting::EC2_VPC_ID))
                $isVPC = true;

        if (!$this->remoteIp && !$this->localIp && !$isVPC)
            return;

        $cryptoTool->setCryptoKey($this->GetKey(true));
        $encMessage = $cryptoTool->encrypt($rawMessage);
        $timestamp = date("c", time());
        $signature = $cryptoTool->sign($encMessage, null, $timestamp);

        try {
            $request = new Request();
            $request->setRequestMethod('POST');

            $ctrlPort = $this->getPort(self::PORT_CTRL);

            $requestHost = $this->getSzrHost() . ":{$ctrlPort}";

            if ($isVPC) {
                $routerFarmRoleId = $this->GetFarmRoleObject()->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_SCALR_ROUTER_ID);
                if ($routerFarmRoleId) {
                    $routerRole = DBFarmRole::LoadByID($routerFarmRoleId);
                } else {
                    $routerRole = $this->GetFarmObject()->GetFarmRoleByBehavior(ROLE_BEHAVIORS::VPC_ROUTER);
                }
                if ($routerRole) {
                    // No public IP need to use proxy
                    if (!$this->remoteIp) {
                        $requestHost = $routerRole->GetSetting(Scalr_Role_Behavior_Router::ROLE_VPC_IP) . ":80";
                        $request->addHeaders(array(
                            "X-Receiver-Host" =>  $this->localIp,
                            "X-Receiver-Port" => $ctrlPort
                        ));
                    // There is public IP, can use it
                    } else {
                        $requestHost = "{$this->remoteIp}:{$ctrlPort}";
                    }
                }
            }

            //Prepare request
            $request->setRequestUrl("http://{$requestHost}/control");
            $request->setOptions(array(
                'timeout'   => \Scalr::config('scalr.system.instances_connection_timeout'),
                'connecttimeout' => \Scalr::config('scalr.system.instances_connection_timeout')
            ));
            $request->addHeaders(array(
                "Date" =>  $timestamp,
                "X-Signature" => $signature,
                'X-Server-Id' => $this->serverId
            ));

            if ($messageType == 'json') {
                $request->addHeaders(array(
                    'Content-type' => 'application/json'
                ));
            }

            $request->append($encMessage);

            // Send request
            $response = \Scalr::getContainer()->srzhttp->sendRequest($request);

            // Process response
            if ($response->getResponseCode() == 201) {

                $logger->info(sprintf("[FarmID: %s] Sending message '%s' via REST to server '%s' (server_id: %s) completed",
                    $this->farmId, $message->getName(), $this->remoteIp, $this->serverId));

                if (in_array($message->getName(), array('ExecScript'))) {
                    $this->Db->Execute("DELETE FROM messages WHERE messageid = ?",
                        array($message->messageId));
                } else {
                    if ($messageType != 'json') {
                        $this->Db->Execute("UPDATE messages SET status = ?, message = '' WHERE messageid = ?",
                            array(MESSAGE_STATUS::HANDLED, $message->messageId));
                    } else {
                        $this->Db->Execute("UPDATE messages SET status = ? WHERE messageid = ?",
                            array(MESSAGE_STATUS::HANDLED, $message->messageId));
                    }

                    if (!empty($message->eventId))
                        $this->Db->Execute("UPDATE events SET msg_sent = msg_sent + 1 WHERE event_id = ?", array(
                            $message->eventId
                        ));
                }
            } else {
                $logger->warn(sprintf("[FarmID: %s] Cannot deliver message '%s' (message_id: %s) via REST"
                    . " to server '%s' (server_id: %s). Error: %s %s",
                    $this->farmId, $message->getName(), $message->messageId,
                    $this->remoteIp, $this->serverId, $response->getResponseCode(), $response->getResponseStatus()));
            }
        } catch(http\Exception $e) {
            if (isset($e->innerException))
                $msg = $e->innerException->getMessage();
            else
                $msg = $e->getMessage();

            if ($this->farmId) {
                $logger->warn(new FarmLogMessage($this, sprintf("Cannot deliver message '%s' (message_id: %s) via REST to server '%s' (server_id: %s). Error: %s",
                    $message->getName(),
                    !empty($message->messageId) ? $message->messageId : null,
                    !empty($this->remoteIp) ? $this->remoteIp : null,
                    !empty($this->serverId) ? $this->serverId : null,
                    !empty($msg) ? $msg : null
                )));
            } else {
                $logger->fatal(sprintf("Cannot deliver message '%s' (message_id: %s) via REST"
                    . " to server '%s' (server_id: %s). Error: %s",
                    $message->getName(), $message->messageId,
                    $this->remoteIp, $this->serverId, $msg
                ));
            }

            return false;
        }

        return $message;
    }

    /**
     * Executes script
     *
     * @param array                          $script  Script settings
     * @param Scalr_Messaging_Msg_ExecScript $msg     Scalarizr message
     * @return void
     */
    public function executeScript(array $script, Scalr_Messaging_Msg_ExecScript $msg)
    {
        $itm = new stdClass();

        $itm->asynchronous = ($script['issync'] == 1) ? '0' : '1';
        $itm->timeout = $script['timeout'];

        if ($script['body']) {
            $itm->name = $script['name'];
            $itm->body = $script['body'];
        } else {
            $itm->path = $script['path'];

            if ($msg->eventName == 'Manual') {
                $itm->name = "local-" . crc32($script['path']) . mt_rand(100, 999);
            }
        }

        $itm->executionId = $script['execution_id'];

        $msg->scripts = [$itm];
        $msg->setGlobalVariables($this, true);

        $this->SendMessage($msg, false, true);
    }

    public function applyGlobalVarsToValue($value)
    {
        if (empty($this->globalVariablesCache)) {
            $formats = \Scalr::config("scalr.system.global_variables.format");

            // Get list of Server system vars
            foreach ($this->GetScriptingVars() as $name => $val) {
                $name = "SCALR_".strtoupper($name);
                $val = trim($val);

                if (isset($formats[$name]))
                    $val = @sprintf($formats[$name], $val);

                $this->globalVariablesCache[$name] = $val;
            }

            // Add custom variables
            $gv = new Scalr_Scripting_GlobalVariables($this->clientId, $this->envId, ScopeInterface::SCOPE_SERVER);
            $vars = $gv->listVariables($this->GetFarmRoleObject()->RoleID, $this->farmId, $this->farmRoleId, $this->serverId);
            foreach ($vars as $v)
                $this->globalVariablesCache[$v['name']] = $v['value'];
        }

        //Parse variable
        $keys = array_keys($this->globalVariablesCache);
        $keys = array_map(function ($item) {
            return '{' . $item . '}';
        }, $keys);
        $values = array_values($this->globalVariablesCache);

        $retval = str_replace($keys, $values, $value);

        // Strip undefined variables & return value
        return preg_replace("/{[A-Za-z0-9_-]+}/", "", $retval);
    }

    /**
     *
     * @return array
     */
    public function GetScriptingVars()
    {
        $dbFarmRole = $this->GetFarmRoleObject();
        $roleId = $dbFarmRole->RoleID;
        $dbRole = DBRole::loadById($roleId);

        $isDbMsr = $dbRole->getDbMsrBehavior();
        if ($isDbMsr)
            $isMaster = $this->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER);
        else
            $isMaster = $this->GetProperty(\SERVER_PROPERTIES::DB_MYSQL_MASTER);

        $at = new Entity\Account\Team();
        $ft = new Entity\FarmTeam();
        $teams = join(",", Entity\Account\Team::find([
            \Scalr\Model\AbstractEntity::STMT_FROM => "{$at->table()} JOIN {$ft->table()} ON {$ft->columnTeamId} = {$at->columnId}",
            \Scalr\Model\AbstractEntity::STMT_WHERE => "{$ft->columnFarmId} = '{$this->farmId}'",
        ])->map(function($t) { return $t->name; }));

        $retval =  array(
            'image_id'      => $dbRole->__getNewRoleObject()->getImage($this->platform, $dbFarmRole->CloudLocation)->imageId,
            'external_ip'   => $this->remoteIp,
            'internal_ip'   => $this->localIp,
            'role_name'     => $dbRole->name,
            'isdbmaster'    => $isMaster,
            'instance_index'=> $this->index,
            'instance_farm_index' => $this->farmIndex,
            'server_type'   => $this->type,
            'server_hostname'  => $this->GetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME),
            'server_id'     => $this->serverId,
            'farm_id'       => $this->farmId,
            'farm_role_id'  => $this->farmRoleId,
            'farm_role_alias'   => $this->GetFarmRoleObject()->Alias,
            'farm_name'     => $this->GetFarmObject()->Name,
            'farm_hash'     => $this->GetFarmObject()->Hash,
            'farm_owner_email'    => $this->GetFarmObject()->createdByUserEmail,
            'farm_team'     => $teams,
            'behaviors'     => implode(",", $dbRole->getBehaviors()),
            'env_id'        => $this->GetEnvironmentObject()->id,
            'env_name'      => $this->GetEnvironmentObject()->name,
            'cloud_location' => $this->GetCloudLocation(),
            'cloud_server_id'=> $this->GetCloudServerID()
        );

        if ($this->cloudLocationZone)
            $retval['cloud_location_zone'] = $this->cloudLocationZone;

        if ($this->platform == SERVER_PLATFORMS::EC2) {
            $retval['instance_id'] = $this->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
            $retval['ami_id'] = $this->GetProperty(EC2_SERVER_PROPERTIES::AMIID);
            $retval['region'] = $this->GetProperty(EC2_SERVER_PROPERTIES::REGION);
            $retval['avail_zone'] = $this->GetProperty(EC2_SERVER_PROPERTIES::AVAIL_ZONE);

            if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ENABLED)) {
                $elbId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_ELB_ID);
                $retval['aws_elb_name'] = $elbId;
            }
        }

        if (\Scalr::getContainer()->analytics->enabled) {
            $ccId = $this->GetProperty(\SERVER_PROPERTIES::ENV_CC_ID);
            if ($ccId) {
                $cc = CostCentreEntity::findPk($ccId);
                if ($cc) {
                    /* @var $cc CostCentreEntity */
                    $retval['cost_center_id'] = $ccId;
                    $retval['cost_center_bc'] = $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE);
                    $retval['cost_center_name'] = $cc->name;
                } else
                    throw new Exception("Cost center {$ccId} not found");
            }

            $projectId = $this->GetProperty(\SERVER_PROPERTIES::FARM_PROJECT_ID);
            if ($projectId) {
                $project = ProjectEntity::findPk($projectId);
                /* @var $project ProjectEntity */
                $retval['project_id'] = $projectId;
                $retval['project_bc'] = $project->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE);
                $retval['project_name'] = $project->name;
            }
        }

        return $retval;
    }

    /**
     * Return list of tags that should be applied for Openstack instances
     * @return array
     */
    public function getOpenstackTags()
    {
        $tags = [
            \Scalr_Governance::SCALR_META_TAG_NAME => $this->applyGlobalVarsToValue(\Scalr_Governance::SCALR_META_TAG_VALUE)
        ];

        //Tags governance
        $governance = new \Scalr_Governance($this->envId);
        $gTags = (array)$governance->getValue($this->platform, \Scalr_Governance::OPENSTACK_TAGS);
        $gAllowAdditionalTags = $governance->getValue($this->platform, \Scalr_Governance::OPENSTACK_TAGS, 'allow_additional_tags');
        if (count($gTags) > 0) {
            foreach ($gTags as $tKey => $tValue) {
                $tags[$tKey] = $this->applyGlobalVarsToValue($tValue);
            }
        }
        if (count($gTags) == 0 || $gAllowAdditionalTags) {
            //Custom tags
            $cTags = $this->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_BASE_CUSTOM_TAGS);
            $tagsList = @explode("\n", $cTags);
            foreach ((array)$tagsList as $tag) {
                $tag = trim($tag);
                if ($tag) {
                    $tagChunks = explode("=", $tag);
                    if (!isset($tags[trim($tagChunks[0])])) {
                        $tags[trim($tagChunks[0])] = $this->applyGlobalVarsToValue(trim($tagChunks[1]));
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Return list of tags that should be applied for Azure instances
     *
     * @return array
     */
    public function getAzureTags()
    {
        $tags = [
            \Scalr_Governance::SCALR_META_TAG_NAME => $this->applyGlobalVarsToValue(\Scalr_Governance::SCALR_META_TAG_VALUE)
        ];

        //Tags governance
        $governance = new \Scalr_Governance($this->envId);
        $gTags = (array)$governance->getValue(SERVER_PLATFORMS::AZURE, \Scalr_Governance::AZURE_TAGS);
        $gAllowAdditionalTags = $governance->getValue(SERVER_PLATFORMS::AZURE, \Scalr_Governance::AZURE_TAGS, 'allow_additional_tags');

        if (count($gTags) > 0) {
            foreach ($gTags as $tKey => $tValue) {
                $tags[$tKey] = $this->applyGlobalVarsToValue($tValue);
            }
        }

        if (count($gTags) == 0 || $gAllowAdditionalTags) {
            //Custom tags
            $cTags = $this->GetFarmRoleObject()->GetSetting(\Scalr_Role_Behavior::ROLE_BASE_CUSTOM_TAGS);

            $tagsList = !empty($cTags) ? explode("\n", $cTags) : [];

            foreach ($tagsList as $tag) {
                $tag = trim($tag);

                if ($tag) {
                    $tagChunks = explode("=", $tag);

                    if (!isset($tags[trim($tagChunks[0])])) {
                        $tags[trim($tagChunks[0])] = $this->applyGlobalVarsToValue(trim($tagChunks[1]));
                    }
                }
            }
        }

        return $tags;
    }

    /**
     * Return list of tags that should be applied on EC2 resources
     * @param string $addNameTag
     * @return array
     */
    public function getAwsTags($addNameTag = false)
    {
        $tags = [];

        $governance = new \Scalr_Governance($this->envId);
        if ($addNameTag) {
            $nameFormat = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_INSTANCE_NAME_FORMAT);
            if (!$nameFormat) {
                $nameFormat = $this->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::AWS_INSTANCE_NAME_FORMAT);
                if (!$nameFormat)
                    $nameFormat = "{SCALR_FARM_NAME} -> {SCALR_FARM_ROLE_ALIAS} #{SCALR_INSTANCE_INDEX}";
            }
            $instanceName = $this->applyGlobalVarsToValue($nameFormat);
            $tags['Name'] = $instanceName;
        }

        $tags[\Scalr_Governance::SCALR_META_TAG_NAME] = $this->applyGlobalVarsToValue(\Scalr_Governance::SCALR_META_TAG_VALUE);

        $gTags = (array)$governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_TAGS);
        $gAllowAdditionalTags = $governance->getValue(SERVER_PLATFORMS::EC2, \Scalr_Governance::AWS_TAGS, 'allow_additional_tags');
        if (count($gTags) > 0) {
            foreach ($gTags as $tKey => $tValue) {
                if ($tKey && count($tags) < 10 && !isset($tags[$tKey]))
                    $tags[$tKey] = $this->applyGlobalVarsToValue($tValue);
            }
        }
        if (count($gTags) == 0 || $gAllowAdditionalTags) {
            //Custom tags
            $cTags = $this->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::AWS_TAGS_LIST);
            $tagsList = @explode("\n", $cTags);
            foreach ((array)$tagsList as $tag) {
                $tag = trim($tag);
                if ($tag && count($tags) < 10) {
                    $tagChunks = explode("=", $tag);
                    if (!isset($tags[trim($tagChunks[0])]))
                        $tags[trim($tagChunks[0])] = $this->applyGlobalVarsToValue(trim($tagChunks[1]));
                }
            }
        }

        return $tags;
    }

    public function getScalarizrRepository()
    {
        $config = \Scalr::getContainer()->config;

        $retval = array(
            'client_mode' => Scalr::config('scalr.scalarizr_update.mode')
        );
        if ($retval['client_mode'] == 'client') {
            $retval['server_url'] = $config->get('scalr.scalarizr_update.server_url');
            $retval['api_port'] = $config->get('scalr.scalarizr_update.api_port');
        }

        $configuredRepo = null;

        if ($this->farmRoleId != 0) {
            $scmBranch = $this->GetFarmRoleObject()->GetSetting('user-data.scm_branch');
            $develRepos = $config->get('scalr.scalarizr_update.devel_repos');
            if ($scmBranch != '' && $develRepos) {
                $develRepository = $this->GetFarmRoleObject()->GetSetting('base.devel_repository');
                $normalizedValue = $develRepository === 'snapshot' ? $scmBranch : str_replace(array(".", '/'), array('', '-'), $scmBranch);

                $repo = isset($develRepos[$develRepository]) ? $develRepos[$develRepository] : array_shift($develRepos);

                return array_merge($retval, array(
                    'repository' => $normalizedValue,
                    'deb_repo_url' => sprintf($repo['deb_repo_url'], $normalizedValue),
                    'rpm_repo_url' => sprintf($repo['rpm_repo_url'], $normalizedValue),
                    'win_repo_url' => sprintf($repo['win_repo_url'], $normalizedValue)
                ));
            }

            $configuredRepo = $this->GetFarmRoleObject()->GetSetting(Scalr_Role_Behavior::ROLE_BASE_SZR_UPD_REPOSITORY);
        }

        if ($this->farmId != 0 && !$configuredRepo)
            $configuredRepo = $this->GetFarmObject()->GetSetting(Entity\FarmSetting::SZR_UPD_REPOSITORY);

        $retval['repository'] = ($configuredRepo) ? $configuredRepo : Scalr::config('scalr.scalarizr_update.default_repo');

        if (!$config->defined("scalr.scalarizr_update.repos.{$retval['repository']}"))
            throw new Exception("Scalarizr repository configuration is incorrect. Unknown repository name: {$retval['repository']}");

        $repos = $config->get("scalr.scalarizr_update.repos.{$retval['repository']}");
        return array_merge($retval, $repos);
    }

    /**
     * Marks server as to be suspended.
     *
     * @param   string|array           $reason      The reason possibly with the format parameters.
     * @param   bool                   $forcefully  optional Method: forcefully (true) | gracefully (false)
     * @param   Scalr_Account_User|int $user        optional The user object or its unique identifier
     */
    public function suspend($reason, $forcefully = null, $user = null)
    {
        if (!in_array($this->status, array(
            SERVER_STATUS::RUNNING
        ))) {
            return;
        }

        $forcefully = $forcefully === null ? true : (bool) $forcefully;

        //Ensures handling identifier of the user instead of the object
        if ($user !== null && !($user instanceof \Scalr_Account_User)) {
            try {
                $user = Scalr_Account_User::init()->loadById(intval($user));
            } catch (\Exception $e) {
            }
        }

        $reason = 'TODO';

        //Set who does terminate the server
        if ($user instanceof \Scalr_Account_User) {
            $this->SetProperties(array(
                \SERVER_PROPERTIES::TERMINATED_BY_ID    => $user->id,
                \SERVER_PROPERTIES::TERMINATED_BY_EMAIL => $user->getEmail(),
            ));
        }

        $this->update([
            'status'                => SERVER_STATUS::PENDING_SUSPEND,
            'dateShutdownScheduled' => date("Y-m-d H:i:s", ($forcefully ? time() : strtotime(Scalr::config('scalr.system.server_terminate_timeout'))))
        ]);

        //$this->getServerHistory()->markAsTerminated($reason);
        //$this->getServerHistory()->markAsSuspended($reason);

        if (isset($this->farmId) && !$forcefully) {
            Scalr::FireEvent($this->farmId, new BeforeHostTerminateEvent($this, true));
        }
    }

    /**
     * Marks server as to be terminated.
     *
     * @param   integer|array           $reason      The reason possibly with the format parameters.
     * @param   bool                   $forcefully  optional Method: forcefully (true) | gracefully (false)
     * @param   Scalr_Account_User|int $user        optional The user object or its unique identifier
     */
    public function terminate($reason, $forcefully = null, $user = null)
    {
        if (in_array($this->status, array(
            SERVER_STATUS::PENDING_TERMINATE,
            SERVER_STATUS::TERMINATED
        ))) {
            return;
        }

        $forcefully = $forcefully === null ? true : (bool) $forcefully;

        //Ensures handling identifier of the user instead of the object
        if ($user !== null && !($user instanceof \Scalr_Account_User)) {
            try {
                $user = Scalr_Account_User::init()->loadById(intval($user));
            } catch (\Exception $e) {
            }
        }

        $fnGetReason = function ($reasonId) {
            $args = func_get_args();
            $args[0] = DBServer::getTerminateReason($reasonId);
            return [call_user_func_array('sprintf', $args), $reasonId];
        };

        list($reason, $reasonId) = is_array($reason) ? call_user_func_array($fnGetReason, $reason) : $fnGetReason($reason);

        //Set who does terminate the server
        if ($user instanceof \Scalr_Account_User) {
            $this->SetProperties(array(
                \SERVER_PROPERTIES::TERMINATED_BY_ID    => $user->id,
                \SERVER_PROPERTIES::TERMINATED_BY_EMAIL => $user->getEmail(),
            ));
        }

        $this->SetProperties([
            SERVER_PROPERTIES::REBOOTING => 0
        ]);

        $this->update([
            'status'                => SERVER_STATUS::PENDING_TERMINATE,
            'dateShutdownScheduled' => date("Y-m-d H:i:s", ($forcefully ? time() : strtotime(Scalr::config('scalr.system.server_terminate_timeout'))))
        ]);

        $this->getServerHistory()->markAsTerminated($reason, $reasonId);

        if (isset($this->farmId)) {
            Scalr::FireEvent($this->farmId, new BeforeHostTerminateEvent($this, false));

            // If instance was terminated outside scalr, we need manually fire HostDown
            if ($reasonId == self::TERMINATE_REASON_CRASHED) {
                Scalr::FireEvent($this->farmId, new HostDownEvent($this, false));
            }
        }
    }

    /**
     * Gets server history object
     *
     * @deprecated
     * @see \Scalr\Model\Entity\Server::getHistory()
     *
     * @return  \Scalr\Model\Entity\Server\History Returns server history object
     */
    public function getServerHistory()
    {
        $bSave = false;
        $mapping = ['envId' => 'envId', 'farmId' => 'farmId', 'farmRoleId' => 'farmRoleId', 'serverIndex' => 'index', 'cloudLocation' => 'cloudLocation'];

        if (!isset($this->serverHistory)) {
            $entity = Entity\Server\History::findPk($this->serverId);

            if (!$entity) {
                $this->serverHistory = new Entity\Server\History();
                $this->serverHistory->clientId = $this->clientId;
                $this->serverHistory->serverId = $this->serverId;
                $this->serverHistory->platform = $this->platform;
                $this->serverHistory->cloudLocation = $this->cloudLocation;
                $this->serverHistory->instanceTypeName = $this->instanceTypeName;
                $this->serverHistory->roleId = $this->GetProperty(SERVER_PROPERTIES::ROLE_ID);
                $this->serverHistory->farmCreatedById = $this->GetProperty(SERVER_PROPERTIES::FARM_CREATED_BY_ID);
                $this->serverHistory->osType = $this->osType;
                $this->serverHistory->type = $this->type;

                $bSave = true;
            } else {
                $this->serverHistory = $entity;
            }

            if ($this->GetEnvironmentObject()->analytics->enabled) {
                $this->serverHistory->projectId = $this->GetProperty(SERVER_PROPERTIES::FARM_PROJECT_ID);
                $this->serverHistory->ccId = $this->GetProperty(SERVER_PROPERTIES::ENV_CC_ID);
                $bSave = true;
            }
        }

        foreach ($mapping as $prop => $key) {
            if ($this->serverHistory->$prop != $this->$key) {
                $this->serverHistory->$prop = $this->$key;
                $bSave = true;
            }
        }

        if (!empty($bSave)) {
            $this->serverHistory->save();
        }

        return $this->serverHistory;
    }

    /**
     * Gets a list of servers which in the termination process
     *
     * @return array Returns the list of the servers
     */
    public static function getTerminatingServers()
    {
        return Scalr::getDb()->GetAll("
            SELECT
                s.`server_id`, s.`client_id`, s.`env_id`, s.`platform`, s.`status`, s.`dtshutdownscheduled`,
                te.`attempts`
            FROM `servers` s
            LEFT JOIN `server_termination_errors` te ON te.`server_id` = s.`server_id`
            WHERE (s.`status` = ? OR (s.`dtshutdownscheduled` IS NOT NULL AND (s.`status` = ? OR s.`status` = ?)))
            AND (te.`server_id` IS NULL OR te.retry_after <= NOW())
            ORDER BY s.`dtshutdownscheduled`, te.`attempts`
        ", [
            SERVER_STATUS::TERMINATED,
            SERVER_STATUS::PENDING_TERMINATE,
            SERVER_STATUS::PENDING_SUSPEND
        ]);
    }

    public function __get($name)
    {
        if ($name == 'scalarizr') {
            $this->scalarizr = new stdClass();

            // Get list of namespaces
            $refl = new ReflectionClass('Scalr_Net_Scalarizr_Client');
            foreach ($refl->getConstants() as $c => $v) {
                if (substr($c, 0, 9) == 'NAMESPACE') {
                    $this->scalarizr->{$v} = Scalr_Net_Scalarizr_Client::getClient(
                        $this,
                        $v,
                        $this->getPort(self::PORT_API)
                    );
                }
            }
        } elseif ($name == 'scalarizrUpdateClient') {
            $this->scalarizrUpdateClient = new Scalr_Net_Scalarizr_UpdateClient(
                $this,
                $this->getPort(self::PORT_UPDC),
                \Scalr::config('scalr.system.instances_connection_timeout')
            );
        }

        if (isset($this->{$name})) {
            return $this->{$name};
        } else {
            throw new InvalidArgumentException("Unknown property '{$name}' in class DBServer");
        }
     }

     /**
      * Sets OS type
      *
      * @param   string    $osType  Operating System type (linux/windows)
      * @return  DBServer
      */
     public function setOsType($osType)
     {
         // Invar: use ServerHistory
         $this->osType = $osType;

         if ($this->serverId)
            $this->SetProperty(\SERVER_PROPERTIES::OS_TYPE, $osType);

         return $this;
     }
}
