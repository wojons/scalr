<?php

use Scalr\Server\Operations;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\StatusAdapterInterface;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Model\Entity\ServerTerminationError;

/**
 * Core Server object
 *
 * @author   Igor Savchenko  <igor@scalr.com>
 * @since    18.09.2012
 *
 * @property-read  Scalr_Net_Scalarizr_Client        $scalarizr        Scalarizr API client
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
    const TERMINATE_REASON_REPLACE_SERVER_FROM_SNAPSHOT = 14;
    const TERMINATE_REASON_OPERATION_CANCELLATION = 15;
    const TERMINATE_REASON_CRASHED = 16;

    const LAUNCH_REASON_REPLACE_SERVER_FROM_SNAPSHOT = 1;
    const LAUNCH_REASON_SCALING_UP = 2;
    const LAUNCH_REASON_FARM_LAUNCHED = 3;
    const LAUNCH_REASON_MANUALLY_API = 4;
    const LAUNCH_REASON_MANUALLY = 5;


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

    public $dateShutdownScheduled;

    public $dateRebootStart;

    public $dateLastSync;

    public $osType = 'linux';

    public $replaceServerID;

    public $index;

    public $cloudLocation;

    public $cloudLocationZone;

    public $imageId;

    public $operations;


    private $platformProps;

    private $realStatus;

    private $cloudServerID;

    private $flavor;

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
     * @var \Scalr\Server\History
     */
    private $serverHistory;

    public static $platformPropsClasses = array(
        SERVER_PLATFORMS::EC2 => 'EC2_SERVER_PROPERTIES',
        SERVER_PLATFORMS::RACKSPACE => 'RACKSPACE_SERVER_PROPERTIES',
        SERVER_PLATFORMS::EUCALYPTUS => 'EUCA_SERVER_PROPERTIES',

        SERVER_PLATFORMS::CLOUDSTACK => 'CLOUDSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::IDCF => 'CLOUDSTACK_SERVER_PROPERTIES',

        SERVER_PLATFORMS::GCE => 'GCE_SERVER_PROPERTIES',

        SERVER_PLATFORMS::OPENSTACK => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::RACKSPACENG_UK => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::RACKSPACENG_US => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::ECS => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::CONTRAIL => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::OCS => 'OPENSTACK_SERVER_PROPERTIES',
        SERVER_PLATFORMS::NEBULA => 'OPENSTACK_SERVER_PROPERTIES'
    );

    private static $FieldPropertyMap = array(
        'id'			=> 'dbId',
        'server_id' 	=> 'serverId',
        'env_id'		=> 'envId',
        'farm_id'		=> 'farmId',
        'farm_roleid'	=> 'farmRoleId',
        'client_id'		=> 'clientId',
        'platform'		=> 'platform',
        'status'		=> 'status',
        'os_type'       => 'osType',
        'remote_ip'		=> 'remoteIp',
        'local_ip'		=> 'localIp',
        'dtadded'		=> 'dateAdded',
        'dtshutdownscheduled'	=> 'dateShutdownScheduled',
        'dtrebootstart'	=> 'dateRebootStart',
        'dtlastsync'	=> 'dateLastSync',
        'index'			=> 'index',
        'cloud_location'		=> 'cloudLocation',
        'cloud_location_zone'	=> 'cloudLocationZone',
        'image_id'          => 'imageId',
        'replace_server_id' => 'replaceServerID'
    );

    private $tmpFiles = array();

    const PORT_API = SERVER_PROPERTIES::SZR_API_PORT;
    const PORT_CTRL = SERVER_PROPERTIES::SZR_CTRL_PORT;
    const PORT_SNMP = SERVER_PROPERTIES::SZR_SNMP_PORT;
    const PORT_UPDC = SERVER_PROPERTIES::SZR_UPDC_PORT;
    const PORT_SSH = SERVER_PROPERTIES::CUSTOM_SSH_PORT;

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
        $this->operations = new Operations($this);
    }

    public function __destruct() {
        if (count($this->tmpFiles) > 0)
            foreach ($this->tmpFiles as $file)
                @unlink($file);
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
            16 => 'Server was crashed or terminated outside scalr'
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
            5 => 'Manually launched using UI'
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
            case SERVER_PLATFORMS::RACKSPACE:
                $ssh2Client->addPassword(
                    'root',
                    $this->GetProperty(RACKSPACE_SERVER_PROPERTIES::ADMIN_PASS)
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
                    $key = Scalr_Model::init(Scalr_Model::SSH_KEY)->loadGlobalByName(
                        $keyName,
                        "",
                        $this->envId,
                        SERVER_PLATFORMS::GCE
                    );

                    if (!$key)
                        throw new Exception(_("There is no SSH key for server: {$this->serverId}"));
                }
                catch(Exception $e){
                    throw new Exception("Cannot init SshKey object: {$e->getMessage()}");
                }

                $priv_key_file = tempnam("/tmp", "GCEPK");
                @file_put_contents($priv_key_file, $key->getPrivate());
                $this->tmpFiles[] = $priv_key_file;

                $pub_key_file = tempnam("/tmp", "GCEK");
                @file_put_contents($pub_key_file, $key->getPublic());
                $this->tmpFiles[] = $pub_key_file;

                $ssh2Client->addPubkey($userName, $pub_key_file, $priv_key_file);

                 break;

            case SERVER_PLATFORMS::IDCF:
            case SERVER_PLATFORMS::ECS:
            case SERVER_PLATFORMS::EC2:

                $userName = 'root';
                $skipKeyValidation = false;

                // Temporary server for role builder
                $sshKey = \Scalr_SshKey::init();
                if ($this->status == SERVER_STATUS::TEMPORARY) {
                    $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID . "-{$this->envId}";
                    if (!$sshKey->loadGlobalByName($keyName, $this->GetCloudLocation(), $this->envId, $this->platform))
                        $keyName = "SCALR-ROLESBUILDER-" . SCALR_ID;

                    try {
                        $bundleTaskId = $this->GetProperty(SERVER_PROPERTIES::SZR_IMPORTING_BUNDLE_TASK_ID);
                        $bundleTask = BundleTask::LoadById($bundleTaskId);
                        if ($bundleTask->osFamily == 'amazon') {
                            $userName = 'ec2-user';
                        }
                    } catch (Exception $e) {}
                } else {
                    $keyName = "FARM-{$this->farmId}-".SCALR_ID;
                    $oldKeyName = "FARM-{$this->farmId}";
                    $key = $sshKey->loadGlobalByName($oldKeyName, $this->GetCloudLocation(), $this->envId, $this->platform);
                    if ($key) {
                        $keyName = $oldKeyName;
                        $skipKeyValidation = true;
                    }
                }

                if (!$skipKeyValidation) {
                    try {
                        $key = $sshKey->loadGlobalByName(
                            $keyName,
                            $this->GetCloudLocation(),
                            $this->envId,
                            $this->platform
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
                @file_put_contents($priv_key_file, $key->getPrivate());
                $this->tmpFiles[] = $priv_key_file;

                $pub_key_file = tempnam("/tmp", "AWSK");
                $this->tmpFiles[] = $pub_key_file;

                $pubKey = $key->getPublic();
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

        $platform = $this->isOpenstack() ? SERVER_PLATFORMS::OPENSTACK : $this->platform;

        $retval = array(
            "farmid" 			=> $this->farmId,
            "role"				=> implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
            //"eventhandlerurl"	=> \Scalr::config('scalr.endpoint.host'),
            "httpproto"			=> \Scalr::config('scalr.endpoint.scheme'),
            "region"			=> $this->GetCloudLocation(),

            /*** For Scalarizr ***/
            "hash"				    => $this->GetFarmObject()->Hash,
            "realrolename"		    => $dbFarmRole->GetRoleObject()->name,
            "szr_key"				=> $this->GetKey(),
            "serverid"				=> $this->serverId,
            'p2p_producer_endpoint'	=> $baseurl . "/messaging",
            'queryenv_url'			=> $baseurl . "/query-env",
            'behaviors'				=> implode(",", $dbFarmRole->GetRoleObject()->getBehaviors()),
            'farm_roleid'			=> $dbFarmRole->ID,
            'roleid'				=> $dbFarmRole->RoleID,
            'env_id'				=> $dbFarmRole->GetFarmObject()->EnvID,
            'platform'				=> $platform,
            'server_index'          => $this->index,
            'cloud_server_id'       => $this->GetCloudServerID(),
            'cloud_location_zone'   => $this->cloudLocationZone,

            /*** General information ***/
            "owner_email"			=> $dbFarmRole->GetFarmObject()->createdByUserEmail
        );

        $retval['message_format'] = 'json';

        if (PlatformFactory::isOpenstack($this->platform)) {
            $retval["cloud_storage_path"] = "swift://";
        } else {
            switch($this->platform) {
            	case SERVER_PLATFORMS::EC2:

            	    $retval["s3bucket"]	= $dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_S3_BUCKET);
            	    $retval["cloud_storage_path"] = "s3://".$dbFarmRole->GetSetting(DBFarmRole::SETTING_AWS_S3_BUCKET);

            	    break;

            	case SERVER_PLATFORMS::RACKSPACE:

            	    $retval["cloud_storage_path"] = "cf://scalr-{$this->GetFarmObject()->Hash}";

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

    public function GetOsFamily()
    {
        try {
            $os = $this->GetFarmRoleObject()->GetRoleObject()->os;
        } catch (Exception $e) {
            return 'unknown';
        }

        if (stristr($os, 'windows'))
            return 'windows';
        else
            return 'linux';
    }

    public function IsRebooting()
    {
        return $this->GetProperty(SERVER_PROPERTIES::REBOOTING, true);
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
     *
     * Return server flavor (instance type)
     * @param bool $skipCache
     * @return string
     */
    public function GetFlavor($skipCache = false)
    {
        if (!$this->flavor || $skipCache == true)
            $this->flavor = PlatformFactory::NewPlatform($this->platform)->GetServerFlavor($this);

        return $this->flavor;
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
        $key = $this->GetProperty(SERVER_PROPERTIES::SZR_KEY, true);

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
     * Checks scalr inbound request rate (req/min)
     *
     * @param   string $resourseName optional Resourse name
     * @return  int    Returns the number of the requests per minute
     */
    public function checkRequestRate($resourseName = '')
    {
        $key = SERVER_PROPERTIES::SCALR_INBOUND_REQ_RATE . ($resourseName ? '.' . $resourseName : '');

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
            $this->Db->BeginTrans();

            // We need to perpetuate server_properties records for removed servers
            $this->Db->Execute('DELETE FROM servers WHERE server_id=?', array($this->serverId));
            $this->Db->Execute('DELETE FROM messages WHERE server_id=?', array($this->serverId));

            $this->Db->Execute("
                UPDATE `dm_deployment_tasks` SET status=? WHERE server_id=?
            ", array(
                Scalr_Dm_DeploymentTask::STATUS_ARCHIVED, $this->serverId
            ));

            $this->Db->CommitTrans();
        } catch (Exception $e) {
            $this->Db->RollbackTrans();
            throw $e;
        }
    }

    static public function IsExists($serverId)
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
    static public function LoadByFarmRoleIDAndIndex($farm_roleid, $index)
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
     *
     * @param $serverId
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

        $container = Scalr::getContainer();
        $container->dbServer = $DBServer;

        return $DBServer;
    }



    public function GetFreeDeviceName()
    {
        if (!$this->IsSupported('0.11.0'))
            return $this->GetFreeDeviceNameSNMP();

        $list = $this->scalarizr->system->blockDevices();

        $map = array("f", "g", "h", "i", "j", "k", "l", "m", "n", "p");
        $n_map = array("1", "2", "3", "4", "5", "6", "7", "8", "9");
        $mapUsed = array();

        foreach ($list as $deviceName) {
            preg_match("/(sd|xvd)([a-z][0-9]*)/", $deviceName, $matches);

            if (!in_array($matches[2], $mapUsed))
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
                    $mapUsed = false;
            }

            if ($deviceL)
                break;
        }

        if (!$deviceL)
            throw new Exception(_("There is no available device letter on instance for attaching EBS"));

        return "/dev/sd{$deviceL}";
    }

    public function GetFreeDeviceNameSNMP()
    {
        $DBFarm = $this->GetFarmObject();

        $snmpClient = new Scalr_Net_Snmp_Client();

        $port = $this->GetProperty(SERVER_PROPERTIES::SZR_SNMP_PORT);
        $snmpClient->Connect($this->remoteIp, $port ? $port : 161, $DBFarm->Hash, false, false, true);

        $result = implode(":", $snmpClient->getTree("UCD-DISKIO-MIB::diskIODevice"));

        $map = array(
            "f", "g", "h", "i", "j",
            "k", "l", "m", "n", "p"
        );

        $n_map = array(
            "1", "2", "3", "4", "5", "6", "7", "8", "9"
        );

        $map_used = array();

        preg_match_all("/(sd|xvd)([a-z][0-9]*)/", $result, $matches);
        foreach ($matches[2] as $v)
        {
            if (!in_array($map_used, $v))
                array_push($map_used, $v);
        }

        if (count($map_used) == 0)
            throw new Exception(_("Cannot get a list of used disk devices: {$result}"));

        $device_l = false;
        foreach ($n_map as $v) {
            foreach ($map as $letter) {
                if (in_array($letter, $map_used))
                    continue;

                $device_l = "{$letter}{$v}";
                if (!in_array($device_l, $map_used)) {
                    break;
                } else
                    $device_l = false;
            }

            if ($device_l)
                break;
        }

        if (!$device_l)
            throw new Exception(_("There is no available device letter on instance for attaching EBS"));

        return "/dev/sd{$device_l}";
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
        if ($isImport)
            $startWithLetter = true;

        $server_id = Scalr::GenerateUID(false, $startWithLetter);

        $status = (!$isImport) ? SERVER_STATUS::PENDING_LAUNCH : SERVER_STATUS::IMPORTING;

        if ($setPendingStatus)
            $status = SERVER_STATUS::PENDING;

        // IF no index defined
        if (!$creInfo->index && !$isImport) {
            $indexes = $db->GetAll("
                SELECT `index` FROM servers
                WHERE farm_roleid=?
                AND status NOT IN (?,?,?)
            ", array(
                $creInfo->dbFarmRole->ID,
                SERVER_STATUS::TERMINATED,
                SERVER_STATUS::PENDING_TERMINATE,
                SERVER_STATUS::TROUBLESHOOTING
            ));

            $used_indexes = array();

            if (count($indexes) > 0)
                foreach ($indexes as $index)
                    $used_indexes[$index['index']] = true;

            for ($i = 1;;$i++) {
                if (!$used_indexes[$i]) {
                    $creInfo->index = $i;
                    break;
                }
            }
        } elseif ($isImport) {
            $creInfo->index = 0;
        }

        $client_id = $creInfo->clientId ? $creInfo->clientId : $creInfo->dbFarmRole->GetFarmObject()->ClientID;

        $db->Execute("
            INSERT INTO servers SET
            `server_id`		= ?,
            `farm_id`		= ?,
            `env_id`		= ?,
            `farm_roleid`	= ?,
            `client_id`		= ?,
            `platform`		= ?,
            `status`		= ?,
            `remote_ip`		= ?,
            `local_ip`		= ?,
            `dtadded`		= NOW(),
            `index`			= ?
        ", array(
            $server_id,
            $creInfo->farmId ? $creInfo->farmId : $creInfo->dbFarmRole->FarmID,
            $creInfo->envId,
            $creInfo->dbFarmRole ? $creInfo->dbFarmRole->ID : 0,
            $client_id,
            $creInfo->platform,
            $status,
            $creInfo->remoteIp,
            $creInfo->localIp,
            $creInfo->index
        ));

        $DBServer = DBServer::LoadByID($server_id);
        $DBServer->SetProperties($creInfo->GetProperties());
        $DBServer->setOsType($DBServer->osType);

        try {
            if ($DBServer->farmRoleId) {
                $db->Execute("INSERT INTO servers_launch_timelog SET
                    `server_id`		= ?,
                    `os_family`		= ?,
                    `os_version`    = ?,
                    `cloud`		    = ?,
                    `cloud_location`	= ?,
                    `server_type`		= ?,
                    `behaviors`		= ?,
                    `ts_created`    = ?
                ", array(
                    $server_id,
                    $DBServer->GetFarmRoleObject()->GetRoleObject()->osFamily,
                    $DBServer->GetFarmRoleObject()->GetRoleObject()->osVersion,
                    $DBServer->platform,
                    $DBServer->cloudLocation,
                    $DBServer->GetFlavor(),
                    implode(",", $DBServer->GetFarmRoleObject()->GetRoleObject()->getBehaviors()),
                    time()
                ));
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

    function Save () {

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

        try	{
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
        } else {
            $verInfo = array(0, 0, 0);
        }

        return $verInfo;
    }

    public function setScalarizrVersion($version)
    {
        if (!preg_match('/^[0-9a-zA-Z\.-]+$/si', $version))
            return false;

        return $this->SetProperty(SERVER_PROPERTIES::SZR_VESION, $version);
    }

    /**
     * Return information about scalarizr version installed on instance
     *
     * @return array
     */
    public function GetScalarizrVersion()
    {
        return $this->GetVersionInfo($this->GetProperty(SERVER_PROPERTIES::SZR_VESION, true));
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

        if ($this->farmId && $message->getName() != 'BeforeHostTerminate') {
            if ($this->GetFarmObject()->Status == FARM_STATUS::TERMINATED) {
                $this->Db->Execute("UPDATE messages SET status = ? WHERE messageid = ?", array(MESSAGE_STATUS::FAILED, $message->messageId));
                return;
            }
        }

        //
        // To avoid message flood if server cannot respond
        // Protection from DDOS
        // Log only right now
        /*
        $pendingMessagesCount = $this->Db->GetOne("SELECT COUNT(*) FROM messages WHERE status = '0' OR server_id = ?", array(
        	$this->serverId
        ));
        if ($pendingMessagesCount > 50) {
            if ($message->serverId != $this->serverId) {
                $this->SetProperty('tmp.flood-alert', 1);
            }
        }
        */


        // Ignore OLD messages (ami-scripts)
        if (!$this->IsSupported("0.5"))
            return;

        // Put access data and reserialize message
        $pl = PlatformFactory::NewPlatform($this->platform);
        $pl->PutAccessData($this, $message);

        $logger = Logger::getLogger('DBServer');
        $serializer = Scalr_Messaging_XmlSerializer::getInstance();
        $cryptoTool = Scalr_Messaging_CryptoTool::getInstance();

        if ($this->GetProperty(SERVER_PROPERTIES::SZR_MESSAGE_FORMAT) == 'json') {
            $serializer = Scalr_Messaging_JsonSerializer::getInstance();
            $rawMessage = $serializer->serialize($message);
            $messageType = 'json';
        } else {
            $rawMessage = $serializer->serialize($message);
            $messageType = 'xml';
        }

        //$rawJsonMessage = @json_encode($message);

        $time = microtime(true) - $startTime;

        if (!$message->dbMessageId) {
            // Add message to database
            $this->Db->Execute("INSERT INTO messages SET
                `messageid`	= ?,
                `processing_time` = ?,
                `server_id`	= ?,
                `event_server_id` = ?,
                `message`	= ?,
                `type`		= 'out',
                `message_name` = ?,
                `handle_attempts` = ?,
                `message_version` = ?,
                `dtlasthandleattempt` = NOW(),
                `dtadded` = NOW(),
                `message_format` = ?,
                `event_id` = ?
            ON DUPLICATE KEY UPDATE handle_attempts = handle_attempts+1, dtlasthandleattempt = NOW()
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

            $message->dbMessageId = $this->Db->Insert_ID();
        } else {
            $this->Db->Execute("UPDATE messages SET handle_attempts = handle_attempts+1, dtlasthandleattempt = NOW() WHERE id = ?", array(
                $message->dbMessageId
            ));
        }

        if ($delayed)
            return $message;

        $isVPC = false;


        if ($this->farmId)
            if (DBFarm::LoadByID($this->farmId)->GetSetting(DBFarm::SETTING_EC2_VPC_ID))
                $isVPC = true;

        if (!$this->remoteIp && !$this->localIp && !$isVPC)
            return;


        $cryptoKey = $this->GetKey(true);
        $encMessage = $cryptoTool->encrypt($rawMessage, $cryptoKey);
        list($signature, $timestamp) = $cryptoTool->sign($encMessage, $cryptoKey);

        try {
            $request = new HttpRequest();
            $request->setMethod(HTTP_METH_POST);

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
            $request->setUrl("http://{$requestHost}/control");
            $request->setOptions(array(
                'timeout'	=> \Scalr::config('scalr.system.instances_connection_timeout'),
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

            $request->setBody($encMessage);

            // Send request
            $request->send();

            // Process response
            if ($request->getResponseCode() == 201) {

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
                    $this->remoteIp, $this->serverId, $request->getResponseCode(), $request->getResponseStatus()));
            }
        } catch(HttpException $e) {
            if (isset($e->innerException))
                $msg = $e->innerException->getMessage();
            else
                $msg = $e->getMessage();

            if ($this->farmId) {
                $logger->warn(new FarmLogMessage($this->farmId, sprintf("Cannot deliver message '%s' (message_id: %s) via REST"
                    . " to server '%s' (server_id: %s). Error: %s %s",
                    $message->getName(), $message->messageId,
                    $this->remoteIp, $this->serverId, $request->getResponseCode(), $msg
                )));
            } else {
                $logger->fatal(sprintf("Cannot deliver message '%s' (message_id: %s) via REST"
                    . " to server '%s' (server_id: %s). Error: %s %s",
                    $message->getName(), $message->messageId,
                    $this->remoteIp, $this->serverId, $request->getResponseCode(), $msg
                ));
            }

            return false;
        }

        return $message;
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
                    $value = @sprintf($formats[$name], $val);

                $this->globalVariablesCache[$name] = $val;
            }

            // Add custom variables
            $gv = new Scalr_Scripting_GlobalVariables($this->clientId, $this->envId, Scalr_Scripting_GlobalVariables::SCOPE_SERVER);
            $vars = $gv->listVariables($this->GetFarmRoleObject()->RoleID, $this->farmId, $this->farmRoleId, $this->serverId);
            foreach ($vars as $v)
                $this->globalVariablesCache[$v['name']] = $v['value'];
        }

        //Parse variable
        $keys = array_keys($this->globalVariablesCache);
        $f = create_function('$item', 'return "{".$item."}";');
        $keys = array_map($f, $keys);
        $values = array_values($this->globalVariablesCache);

        return str_replace($keys, $values, $value);
    }

    /**
     *
     * @return array
     */
    public function GetScriptingVars()
    {
        $dbFarmRole = $this->GetFarmRoleObject();
        $roleId = $dbFarmRole->NewRoleID ? $dbFarmRole->NewRoleID : $dbFarmRole->RoleID;
        $dbRole = DBRole::loadById($roleId);

        $isDbMsr = $dbRole->getDbMsrBehavior();
        if ($isDbMsr)
            $isMaster = $this->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER);
        else
            $isMaster = $this->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER);

        $retval =  array(
            'image_id'		=> $dbRole->__getNewRoleObject()->getImage($this->platform, $dbFarmRole->CloudLocation)->imageId,
            'external_ip' 	=> $this->remoteIp,
            'internal_ip'	=> $this->localIp,
            'role_name'		=> $dbRole->name,
            'isdbmaster'	=> $isMaster,
            'instance_index'=> $this->index,
            'server_type'   => $this->GetFlavor(),
            'server_hostname'  => $this->GetProperty(Scalr_Role_Behavior::SERVER_BASE_HOSTNAME),
            'server_id'		=> $this->serverId,
            'farm_id'		=> $this->farmId,
            'farm_role_id'	=> $this->farmRoleId,
            'farm_role_alias'	=> $this->GetFarmRoleObject()->Alias,
            'farm_name'		=> $this->GetFarmObject()->Name,
            'farm_hash'     => $this->GetFarmObject()->Hash,
            'farm_owner_email'    => $this->GetFarmObject()->createdByUserEmail,
            'behaviors'		=> implode(",", $dbRole->getBehaviors()),
            'env_id'		=> $this->GetEnvironmentObject()->id,
            'env_name'		=> $this->GetEnvironmentObject()->name,
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
        }

        if (\Scalr::getContainer()->analytics->enabled) {
            $ccId = $this->GetProperty(SERVER_PROPERTIES::ENV_CC_ID);
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

            $projectId = $this->GetProperty(SERVER_PROPERTIES::FARM_PROJECT_ID);
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
                $normalizedValue = str_replace(array(".", '/'), array('', '-'), $scmBranch);
                $develRepository = $this->GetFarmRoleObject()->GetSetting('base.devel_repository');

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
            $configuredRepo = $this->GetFarmObject()->GetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY);

        $retval['repository'] = ($configuredRepo) ? $configuredRepo : Scalr::config('scalr.scalarizr_update.default_repo');

        if ($retval['repository'] == 'latest') {
            try {
                $client = Scalr_Account::init()->loadById($this->clientId);
                if ($client->priority == 0 && $config->defined("scalr.scalarizr_update.repos.testing")) {
                    $retval['repository'] = 'testing';
                }
            } catch (Exception $e) {}
        }

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

        if ($this->farmRoleId != 0) {
            if (PlatformFactory::NewPlatform($this->platform)->getResumeStrategy() == Scalr_Role_Behavior::RESUME_STRATEGY_REBOOT) {
                $forcefully = true;
            } else {
                $forcefully = false;
            }
        }

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
                SERVER_PROPERTIES::TERMINATED_BY_ID    => $user->id,
                SERVER_PROPERTIES::TERMINATED_BY_EMAIL => $user->getEmail(),
            ));
        }

        $this->status = SERVER_STATUS::PENDING_SUSPEND;
        $this->dateShutdownScheduled = date("Y-m-d H:i:s", ($forcefully ? time() : strtotime(Scalr::config('scalr.system.server_terminate_timeout'))));
        $this->Save();

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
            	SERVER_PROPERTIES::TERMINATED_BY_ID    => $user->id,
                SERVER_PROPERTIES::TERMINATED_BY_EMAIL => $user->getEmail(),
            ));
        }

        $this->status = SERVER_STATUS::PENDING_TERMINATE;
        $this->dateShutdownScheduled = date("Y-m-d H:i:s", ($forcefully ? time() : strtotime(Scalr::config('scalr.system.server_terminate_timeout'))));
        $this->Save();

        $this->getServerHistory()->markAsTerminated($reason, $reasonId);

        if (isset($this->farmId)) {
            Scalr::FireEvent($this->farmId, new BeforeHostTerminateEvent($this, false));
        }
    }

    /**
     * Gets server history object
     *
     * @return  \Scalr\Server\History Returns server history object
     */
    public function getServerHistory()
    {
        if (!isset($this->serverHistory)) {
            $this->serverHistory = new \Scalr\Server\History();

            $info = $this->Db->GetRow("
                SELECT * FROM servers_history WHERE server_id = ? LIMIT 1
            ", array($this->serverId));

            $bSave = false;
            $mapping = array('envId' => 'envId', 'farmId' => 'farmId', 'farmRoleid' => 'farmRoleId', 'serverIndex' => 'index');

            if (!$info) {
                $this->serverHistory->clientId = $this->clientId;
                $this->serverHistory->serverId = $this->serverId;
                $this->serverHistory->platform = $this->platform;
                foreach ($mapping as $prop => $key) {
                    $this->serverHistory->$prop = $this->$key;
                }
                $bSave = true;
            } else {
                foreach ($info as $key => $value) {
                    $prop = lcfirst(\Scalr::camelize($key));
                    $this->serverHistory->$prop = $value;
                    if (array_key_exists($prop, $mapping)) {
                        if ($value != $this->{$mapping[$prop]}) {
                            $this->serverHistory->$prop = $this->{$mapping[$prop]};
                            $bSave = true;
                        }
                    }
                }
            }

            if (!$this->serverHistory->cloudServerId) {
                $this->serverHistory->cloudServerId = $this->GetCloudServerID();
                $this->serverHistory->type = $this->GetFlavor();
                $bSave = true;
            }

            if ($bSave) {
                $this->serverHistory->save();
            }
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
        $te = new ServerTerminationError();

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
            SERVER_STATUS::TERMINATED, SERVER_STATUS::PENDING_TERMINATE, SERVER_STATUS::PENDING_SUSPEND,
        ]);
    }

    public function __get($name)
    {
        //TODO: Discuss and refactor at the end of March
        if ($name == 'scalarizr') {
            $this->scalarizr = new stdClass();

            // Get list of namespaces
            $refl = new ReflectionClass('Scalr_Net_Scalarizr_Client');
            foreach ($refl->getConstants() as $c => $v) {
                if (substr($c, 0, 9) == 'NAMESPACE')
                    $this->scalarizr->{$v} = Scalr_Net_Scalarizr_Client::getClient(
                        $this,
                        $v,
                        $this->getPort(self::PORT_API)
                    );
            }
        }

        if (isset($this->{$name}))
            return $this->{$name};
        else
            throw new InvalidArgumentException("Unknown property '{$name}' in class DBServer");
     }

     /**
      * Sets OS type
      *
      * @param   string    $osType  Operating System type (linux/windows)
      * @return  DBServer
      */
     public function setOsType($osType)
     {
         $this->osType = $osType;

         if ($this->serverId)
            $this->SetProperty(SERVER_PROPERTIES::OS_TYPE, $osType);

         return $this;
     }
}
