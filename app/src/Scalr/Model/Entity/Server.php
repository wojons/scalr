<?php

namespace Scalr\Model\Entity;

use BeforeHostTerminateEvent;
use DateTime;
use DBServer;
use Exception;
use HostDownEvent;
use InvalidArgumentException;
use ReflectionClass;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Model\AbstractEntity;
use RuntimeException;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Model\Entity\Account\Environment;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity;
use Scalr\Acl\Acl;
use Scalr;
use Scalr\Model\Loader\Field;
use Scalr\Model\Type\DatetimeType;
use Scalr\Modules\AbstractPlatformModule;
use Scalr\Modules\PlatformFactory;
use Scalr_Net_Scalarizr_Client;
use Scalr_Net_Scalarizr_UpdateClient;
use Scalr_Role_Behavior;
use SERVER_PROPERTIES;
use SERVER_STATUS;
use SERVER_PLATFORMS;
use stdClass;

/**
 * Server entity
 *
 * @author N.V.
 *
 * @property    SettingsCollection                   $properties             The list of Server Properties
 * @property-read  Scalr_Net_Scalarizr_Client        $scalarizr              Scalarizr API client
 * @property-read  Scalr_Net_Scalarizr_UpdateClient  $scalarizrUpdateClient  Scalarizr Update API client
 *
 * @Entity
 * @Table(name="servers")
 */
class Server extends AbstractEntity implements AccessPermissionsInterface
{

    //System properties
    const SYSTEM_IGNORE_INBOUND_MESSAGES = 'system.ignore_inbound_messages';
    const SYSTEM_USER_DATA_METHOD = 'system.user_data_method';

    //Scalarizr properties
    const SZR_KEY = 'scalarizr.key';
    //permanent, one-time
    const SZR_KEY_TYPE = 'scalarizr.key_type';
    const SZR_MESSAGE_FORMAT = 'scalarizr.message_format';

    const SZR_ONETIME_KEY_EXPIRED = 'scalarizr.onetime_key_expired';

    //0.5 or 0.2-139
    const SZR_VESION = 'scalarizr.version';
    const SZR_UPD_CLIENT_VERSION = 'scalarizr.update_client.version';

    //New Importing process
    const SZR_IMPORTING_VERSION = 'scalarizr.import.version';
    const SZR_IMPORTING_STEP = 'scalarizr.import.step';
    const SZR_IMPORTING_OUT_CONNECTION = 'scalarizr.import.outbound_connection';
    const SZR_IMPORTING_OUT_CONNECTION_ERROR = 'scalarizr.import.outbound_connection.error';

    const SZR_IMPORTING_IMAGE_ID = 'scalarizr.import.image_id';
    const SZR_IMPORTING_ROLE_NAME = 'scalarizr.import.role_name';
    const SZR_IMPORTING_OBJECT = 'scalarizr.import.object';
    const SZR_IMPORTING_BEHAVIOR = 'scalarizr.import.behaviour';
    const SZR_IMPORTING_LAST_LOG_MESSAGE = 'scalarizr.import.last_log_msg';
    const SZR_IMPORTING_BUNDLE_TASK_ID = 'scalarizr.import.bundle_task_id';
    const SZR_IMPORTING_OS_FAMILY = 'scalarizr.import.os_family';
    const SZR_IMPORTING_LEAVE_ON_FAIL = 'scalarizr.import.leave_on_fail';
    const SZR_DEV_SCALARIZR_BRANCH = 'scalarizr.dev.scalarizr.branch';

    const SZR_IMPORTING_CHEF_SERVER_ID = 'scalarizr.import.chef.server_id';
    const SZR_IMPORTING_CHEF_ENVIRONMENT = 'scalarizr.import.chef.environment';
    const SZR_IMPORTING_CHEF_ROLE_NAME = 'scalarizr.import.chef.role_name';

    const SZR_IS_INIT_FAILED = 'scalarizr.is_init_failed';
    const SZR_IS_INIT_ERROR_MSG = 'scalarizr.init_error_msg';
    const LAUNCH_LAST_TRY = 'system.launch.last_try';
    const LAUNCH_ATTEMPT = 'system.launch.attempt';
    const LAUNCH_ERROR = 'system.launch.error';
    const LAUNCH_REASON = 'system.launch.reason';
    const LAUNCH_REASON_ID = 'system.launch.reason_id';
    const SUB_STATUS = 'system.sub-status';

    const SZR_IMPORTING_MYSQL_SERVER_TYPE = 'scalarizr.import.mysql_server_type';

    const SZR_SNMP_PORT = 'scalarizr.snmp_port';
    const SZR_CTRL_PORT = 'scalarizr.ctrl_port';
    const SZR_API_PORT = 'scalarizr.api_port';
    const SZR_UPDC_PORT = 'scalarizr.updc_port';
    const CUSTOM_SSH_PORT = 'scalarizr.ssh_port';

    //Database properties
    const DB_MYSQL_MASTER = 'db.mysql.master';
    const DB_MYSQL_REPLICATION_STATUS = 'db.mysql.replication_status';

    //DNS properties
    const EXCLUDE_FROM_DNS = 'dns.exclude_instance';

    //System properties
    const ARCHITECTURE = "system.architecture";
    const REBOOTING = "system.rebooting";
    const MISSING = "system.missing";
    const TERMINATION_REQUEST_UNIXTIME = "system.termination.request.unixtime";

    //Healthcheck properties
    const HEALTHCHECK_FAILED = "system.healthcheck.failed";
    const HEALTHCHECK_TIME = "system.healthcheck.time";

    //Statistics
    const STATISTICS_BW_IN = "statistics.bw.in";
    const STATISTICS_BW_OUT = "statistics.bw.out";
    const STATISTICS_LAST_CHECK_TS = "statistics.lastcheck_ts";

    //Farm derived properties
    const FARM_CREATED_BY_ID = 'farm.created_by_id';
    const FARM_CREATED_BY_EMAIL = 'farm.created_by_email';
    //They are necessary for cost analytics
    const FARM_PROJECT_ID = 'farm.project_id';
    const FARM_ROLE_ID = 'farm_role.id';
    const ROLE_ID = 'role.id';

    //Environment derived properties
    const ENV_CC_ID = 'env.cc_id';

    //It is used in CA
    const OS_TYPE = 'os_type';

    const LAUNCHED_BY_ID = 'audit.launched_by_id';
    const LAUNCHED_BY_EMAIL = 'audit.launched_by_email';
    const TERMINATED_BY_ID = 'audit.terminated_by_id';
    const TERMINATED_BY_EMAIL = 'audit.terminated_by_email';

    const SCALR_INBOUND_REQ_RATE = 'scalr.inbound.req.rate';

    const INFO_INSTANCE_VCPUS = 'info.instance_vcpus';

    // Statuses
    const STATUS_TEMPORARY = "Temporary";

    const STATUS_RUNNING = "Running";
    const STATUS_PENDING_LAUNCH = "Pending launch";
    const STATUS_PENDING = "Pending";
    const STATUS_INIT = "Initializing";
    const STATUS_IMPORTING = "Importing";

    const STATUS_PENDING_TERMINATE = "Pending terminate";
    const STATUS_TERMINATED = "Terminated";

    const STATUS_PENDING_SUSPEND = "Pending suspend";
    const STATUS_SUSPENDED = "Suspended";
    const STATUS_RESUMING = "Resuming";

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

    const TERMINATE_REASON_CRASHED = 16;

    const PORT_API = SERVER_PROPERTIES::SZR_API_PORT;
    const PORT_CTRL = SERVER_PROPERTIES::SZR_CTRL_PORT;
    const PORT_SNMP = SERVER_PROPERTIES::SZR_SNMP_PORT;
    const PORT_UPDC = SERVER_PROPERTIES::SZR_UPDC_PORT;
    const PORT_SSH = SERVER_PROPERTIES::CUSTOM_SSH_PORT;

    /**
     * The identifier of the Server
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var int
     */
    public $id;

    /**
     * Server unique identifier
     *
     * @Column(type="string")
     * @var string
     */
    public $serverId;

    /**
     * Farm identifier
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $farmId;

    /**
     * Identifier for the role in a farm
     *
     * @Column(type="integer",name="farm_roleid",nullable=true)
     * @var int
     */
    public $farmRoleId;

    /**
     * Identifier of the Client's Account
     *
     * @Column(type="integer",name="client_id")
     * @var int
     */
    public $accountId;

    /**
     * Environment identifier
     *
     * @Column(type="integer")
     * @var int
     */
    public $envId;

    /**
     * Server platform
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $platform;

    /**
     * Server instance type
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $type;

    /**
     * Server instance type name
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $instanceTypeName;

    /**
     * Status of a server
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $status;

    /**
     * Server remote Ip
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $remoteIp;

    /**
     * Server local Ip
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $localIp;

    /**
     * Added time
     *
     * @Column(type="datetime",name="dtadded",nullable=true)
     * @var \DateTime
     */
    public $added;

    /**
     * Initialized time
     *
     * @Column(type="datetime",name="dtinitialized",nullable=true)
     * @var \DateTime
     */
    public $initialized;

    /**
     * Index within the Farm Role
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $index;

    /**
     * Index within the Farm
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $farmIndex;

    /**
     * Server cloud location
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $cloudLocation;

    /**
     * Zone a cloud is located
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $cloudLocationZone;

    /**
     * Image identifier
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $imageId;

    /**
     * Sheduled time for shutdown
     *
     * @Column(type="datetime",name="dtshutdownscheduled",nullable=true)
     * @var \DateTime
     */
    public $shutdownScheduled;

    /**
     * Time when server has been either rebooted or started
     *
     * @Column(type="datetime",name="dtrebootstart",nullable=true)
     * @var \DateTime
     */
    public $rebootStart;

    /**
     * Time the synchronization occured last
     *
     * @Column(type="datetime",name="dtlastsync",nullable=true)
     * @var \DateTime
     */
    public $lastSync;

    /**
     * Server OS type
     *
     * @Column(type="string",name="os_type")
     * @var string
     */
    public $os = 'linux';

    /**
     * Whether server is scalarized
     *
     * @Column(type="boolean",name="is_scalarized")
     * @var bool
     */
    public $scalarized = true;

    /**
     * Server properties collection
     *
     * @var SettingsCollection
     */
    protected $_properties;

    /**
     * @var Farm
     */
    protected $_farm;

    /**
     * @var FarmRole
     */
    protected $_farmRole;

    /**
     * @var Server\History
     */
    protected $_serverHistory;

    /**
     * @var Image
     */
    protected $_image;

    /**
     * @var Environment
     */
    protected $_environment;

    /**
     * @var Scalr_Net_Scalarizr_Client
     */
    protected $_scalarizr;

    /**
     * @var Scalr_Net_Scalarizr_UpdateClient
     */
    protected $_scalarizrUpdateClient;

    /**
     * {@inheritdoc}
     * @see AbstractEntity::delete()
     */
    public function delete()
    {
        $db = $this->db();

        try {
            $db->BeginTrans();

            // We need to perpetuate server_properties records for removed servers
            $db->Execute("DELETE FROM messages WHERE server_id=?", [$this->serverId]);

            $db->Execute(
                "DELETE FROM `server_properties` WHERE `server_id` = ? AND `name` NOT IN (" .
                implode(", ", array_map([$db, "qstr"], self::getImportantPropertyList())) . ")",
                [$this->serverId]
            );

            $db->CommitTrans();
        } catch (Exception $e) {
            $db->RollbackTrans();

            throw $e;
        }

        parent::delete();
    }

    /**
     * {@inheritdoc}
     * @see AbstractEntity::save()
     */
    public function save()
    {
        parent::save();

        if (!empty($this->_properties)) {
            $this->_properties->save();
        }
    }

    /**
     * Temporary solution until we make serverId as primary key
     *
     * @param $serverId
     * @return mixed
     */
    public static function findPk($serverId)
    {
        return self::findOneByServerId($serverId);
    }

    /**
     * Searches servers by criteria and selecting and initiating their Properties
     *
     * @param    array        $criteria     optional The search criteria.
     * @param    array        $group        optional The group by looks like [property1, ...], by default groups by `serverId`
     * @param    array        $order        optional The results order looks like [property1 => true|false, ... ]
     * @param    int          $limit        optional The records limit
     * @param    int          $offset       optional The offset
     * @param    bool         $countRecords optional True to calculate total number of the records without limit
     *
     * @return   ArrayCollection|Server[]    Returns collection of the entities.
     *
     * @throws \Scalr\Exception\ModelException
     */
    public static function findWithProperties(array $criteria = null, array $group = null, array $order = null, $limit = null, $offset = null, $countRecords = null)
    {
        $server = new Server();
        /* @var $servers Server[] */
        $servers = [];

        if (!isset($group)) {
            $group = ['serverId'];
        }

        $collection = $server->result(AbstractEntity::RESULT_ENTITY_COLLECTION)->find($criteria, $group, $order, $limit, $offset, $countRecords);

        foreach ($collection as $server) {
            /* @var $server Server */
            $servers[$server->serverId] = $server;
        }

        if (count($servers) > 0) {
            $propertiesCollection = ServerProperty::find([
                ['serverId' => ['$in' => array_keys($servers)]],
                ['$or' => [
                    ['name' => Scalr_Role_Behavior::SERVER_BASE_HOSTNAME],
                    ['name' => self::LAUNCH_REASON],
                    ['name' => self::LAUNCHED_BY_ID]
                ]]
            ]);

            /* @var $serverProperties ServerProperty[] */
            $serverProperties = [];

            foreach ($propertiesCollection as $property) {
                /* @var $property ServerProperty */
                $serverProperties[$property->serverId][$property->name] = $property;
            }

            foreach ($servers as $serverId => $server) {
                if (isset($serverProperties[$serverId][Scalr_Role_Behavior::SERVER_BASE_HOSTNAME])) {
                    $servers[$serverId]->properties[Scalr_Role_Behavior::SERVER_BASE_HOSTNAME] = $serverProperties[$serverId][Scalr_Role_Behavior::SERVER_BASE_HOSTNAME]->value;
                } else {
                    $servers[$serverId]->properties[Scalr_Role_Behavior::SERVER_BASE_HOSTNAME] = null;
                }

                if (isset($serverProperties[$serverId][self::LAUNCH_REASON])) {
                    $servers[$serverId]->properties[self::LAUNCH_REASON] = $serverProperties[$serverId][self::LAUNCH_REASON]->value;
                } else {
                    $servers[$serverId]->properties[self::LAUNCH_REASON] = null;
                }

                if (isset($serverProperties[$serverId][self::LAUNCHED_BY_ID])) {
                    $servers[$serverId]->properties[self::LAUNCHED_BY_ID] = $serverProperties[$serverId][self::LAUNCHED_BY_ID]->value;
                } else {
                    $servers[$serverId]->properties[self::LAUNCHED_BY_ID] = null;
                }
            }
        }

        return $collection;
    }

    /**
     * Parses version info into machine representation
     *
     * @param string $v String representation of a version
     * @return int[]
     */
    public static function versionInfo($v)
    {
        $matches = null;

        if (preg_match('/^(\d+)\.(\d+)(?:(?:-|\.|r)+(\d+))?$/i', $v, $matches)) {
            // For SVN: 0.7.11 or 0.9.r565 or 0.2-151
            $verInfo = array_map("intval", array_slice($matches, 1));
            while (count($verInfo) < 3) {
                $verInfo[] = 0;
            }
        } elseif (preg_match('/^(\d+)\.(\d+)\.b(\d+)\.[a-f\d]+$/i', $v, $matches)) {
            // For GIT: 0.13.b500.57a5ab9
            $verInfo = array_map("intval", array_slice($matches, 1));
            while (count($verInfo) < 3) {
                $verInfo[] = 0;
            }
        } elseif (preg_match('/^(\d+)\.(\d+)(?:\.\d+){2}$/', $v, $matches)) {
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

    /**
     * Gets port
     *
     * @param string $portType
     * @return int
     */
    public function getPort($portType)
    {
        $port = $this->properties[$portType];

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

    /**
     * Check if given version is less than scalarizr version
     *
     * @param   string  $v  Version
     * @return  bool
     */
    public function isVersionSupported($v)
    {
        /* @var $property ServerProperty */
        $property = ServerProperty::findOne([['serverId' => $this->serverId], ['name' => Server::SZR_VESION]]);
        return self::versionInfo($property ? $property->value : '') >= self::versionInfo($v);
    }

    /**
     * Updates Server status
     *
     * @param    string $serverStatus The status
     * @return   Server
     */
    public function updateStatus($serverStatus)
    {
        if (!$this->serverId) {
            throw new RuntimeException(sprintf(
                "Server identifier has not been set in %s object yet.",
                get_class($this)
            ));
        }

        $this->db()->Execute("
            UPDATE " . $this->table() . " SET {$this->columnStatus} = " . $this->qstr('status', $serverStatus) . "
            WHERE {$this->columnServerId} = " . $this->qstr('serverId', $this->serverId) . "
        ");

        $this->status = $serverStatus;

        return $this;
    }

    /**
     * Gets a list of important properties which must not be deleted
     *
     * @return array Returns array of properties
     */
    public static function getImportantPropertyList()
    {
        return [
            self::FARM_CREATED_BY_ID,
            self::FARM_CREATED_BY_EMAIL,
            self::FARM_PROJECT_ID,
            self::FARM_ROLE_ID,
            self::ROLE_ID,
            self::ENV_CC_ID,
            self::OS_TYPE,
            self::LAUNCHED_BY_ID,
            self::LAUNCHED_BY_EMAIL,
            self::TERMINATED_BY_ID,
            self::TERMINATED_BY_EMAIL,
            self::INFO_INSTANCE_VCPUS,
        ];
    }

    /**
     * Magic getter
     *
     * @param   string $name Name of property that is accessed
     *
     * @return  mixed   Returns property value
     */
    public function __get($name)
    {
        switch ($name) {
            case 'properties':
                if (empty($this->_properties)) {
                    $this->_properties = new SettingsCollection(
                        'Scalr\Model\Entity\ServerProperty',
                        [['serverId' => &$this->serverId]],
                        ['serverId' => &$this->serverId]
                    );
                }

                return $this->_properties;

            case 'scalarizr':
                $this->_scalarizr = new stdClass();
                // Get list of namespaces
                $refl = new ReflectionClass('Scalr_Net_Scalarizr_Client');

                foreach ($refl->getConstants() as $c => $v) {
                    if (substr($c, 0, 9) == 'NAMESPACE') {
                        $this->_scalarizr->{$v} = Scalr_Net_Scalarizr_Client::getClient(
                            $this->__getDBServer(),
                            $v,
                            $this->getPort(self::PORT_API)
                        );
                    }
                }

                return $this->_scalarizr;

            case 'scalarizrUpdateClient':
                $this->_scalarizrUpdateClient = new Scalr_Net_Scalarizr_UpdateClient(
                    $this->__getDBServer(),
                    $this->getPort(self::PORT_UPDC),
                    \Scalr::config('scalr.system.instances_connection_timeout')
                );

                return $this->_scalarizrUpdateClient;

            default:
                return parent::__get($name);
        }
    }

    /**
     * {@inheritdoc}
     * @see AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        /* @var $user User */
        /* @var $environment Environment */
        $access = $environment && $this->envId == $environment->id;

        if ($access) {
            if ($this->farmId) {
                /* @var $farm Farm */
                $farm = Farm::findPk($this->farmId);
                if ($farm) {
                    $access = $farm->hasAccessPermissions($user, $environment, $modify ? Acl::PERM_FARMS_SERVERS : NULL);
                } else {
                    // farm not found, let's check access to all farms
                    $access = $user->getAclRolesByEnvironment($environment->id)->isAllowed(Acl::RESOURCE_FARMS);
                }
            } else {
                // image builder/import servers have farmId as null, for them we check another permissions
                $access = $user->getAclRolesByEnvironment($environment->id)->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);
            }
        }

        return $access;
    }

    /**
     * Get Farm entity
     *
     * @return Farm|null    Returns the Farm entity which is associated with the Server or null otherwise
     */
    public function getFarm()
    {
        if (empty($this->_farm) && !empty($this->farmId)) {
            $this->_farm = Farm::findPk($this->farmId);
        }

        return $this->_farm;
    }

    /**
     * Get FarmRole entity
     *
     * @return FarmRole|null    Returns the FarmRole entity which is associated with the Server or null otherwise
     */
    public function getFarmRole()
    {
        if (empty($this->_farmRole) && !empty($this->farmRoleId)) {
            $this->_farmRole = FarmRole::findPk($this->farmRoleId);
        }

        return $this->_farmRole;
    }

    /**
     * Gets server history entity
     *
     * @return  Entity\Server\History   Returns server history entity
     */
    public function getHistory()
    {
        $bSave = false;
        $mapping = ['envId' => 'envId', 'farmId' => 'farmId', 'farmRoleId' => 'farmRoleId', 'serverIndex' => 'index', 'cloudLocation' => 'cloudLocation'];

        if (!isset($this->_serverHistory)) {
            $entity = Entity\Server\History::findPk($this->serverId);

            if (!$entity) {
                $this->_serverHistory = new Entity\Server\History();

                $this->_serverHistory->clientId         = $this->accountId;
                $this->_serverHistory->serverId         = $this->serverId;
                $this->_serverHistory->platform         = $this->platform;
                $this->_serverHistory->cloudLocation    = $this->cloudLocation;
                $this->_serverHistory->instanceTypeName = $this->instanceTypeName;
                $this->_serverHistory->roleId           = $this->properties[Entity\Server::ROLE_ID];
                $this->_serverHistory->farmCreatedById  = $this->properties[Entity\Server::FARM_CREATED_BY_ID];
                $this->_serverHistory->osType           = $this->os;
                $this->_serverHistory->type             = $this->type;

                $bSave = true;
            } else {
                $this->_serverHistory = $entity;
            }

            if (Scalr::getContainer()->analytics->enabled) {
                $this->_serverHistory->projectId = $this->properties[Entity\Server::FARM_PROJECT_ID];
                $this->_serverHistory->ccId = $this->properties[Entity\Server::ENV_CC_ID];
                $bSave = true;
            }
        }

        foreach ($mapping as $prop => $key) {
            if ($this->_serverHistory->$prop != $this->$key) {
                $this->_serverHistory->$prop = $this->$key;
                $bSave = true;
            }
        }

        if (!empty($bSave)) {
            $this->_serverHistory->save();
        }

        return $this->_serverHistory;
    }

    /**
     * Get Image entity
     *
     * @return  Image|null  Return Image entity or null
     */
    public function getImage()
    {
        if (!empty($this->imageId) && empty($this->_image)) {
            $this->_image = Image::findOne([
                ['platform' => $this->platform],
                ['cloudLocation' => in_array($this->platform, [SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::AZURE]) ? '' : $this->cloudLocation],
                ['id' => $this->imageId],
                ['$or' => [
                    ['accountId' => null],
                    ['$and' => [
                        ['accountId' => $this->accountId],
                        ['$or' => [
                            ['envId' => $this->envId],
                            ['envId' => null]
                        ]]
                    ]]
                ]]
            ], null, ['envId' => false]);
        }

        return $this->_image;
    }

    /**
     * Get Environment entity
     *
     * @return  Environment|null     Return Environment entity or null
     */
    public function getEnvironment()
    {
        if (!empty($this->envId) && empty($this->_environment)) {
            $this->_environment = Environment::findPk($this->envId);
        }

        return $this->_environment;
    }

    /**
     * Sets OS type
     *
     * @param   string    $osType  Operating System type (linux/windows)
     * @return  Server
     */
    public function setOs($osType)
    {
        $this->os = $osType;
        $this->properties[Server::OS_TYPE] = $osType;

        return $this;
    }

    /**
     * Set free farm index for new server
     */
    public function setFreeFarmIndex()
    {
        // We ingore terminated and pending terminating instances to release their indexes.
        $used = $this->db()->GetCol("
            SELECT {$this->columnFarmIndex}
            FROM {$this->table()}
            WHERE {$this->columnFarmId} = ? AND {$this->columnStatus} NOT IN (?, ?)
            ORDER BY {$this->columnFarmIndex}
        ", [
            $this->farmId,
            SERVER_STATUS::TERMINATED,
            SERVER_STATUS::PENDING_TERMINATE,
        ]);

        if (empty($used)) {
            $index = 1;
        } else {
            $minIndex = min($used);
            $maxIndex = max($used);
            $freeIndexes = array_diff(range($minIndex, $maxIndex), $used);
            if (empty($freeIndexes)) {
                $index = $maxIndex + 1;
            } else {
                $index = min($freeIndexes);
            }
        }

        $this->farmIndex = $index;
    }

    /**
     * Set free farm role index for new server
     */
    public function setFreeFarmRoleIndex()
    {
        // We ingore terminated and pending terminating instances to release their indexes.
        $used = $this->db()->GetCol("
            SELECT {$this->columnIndex}
            FROM {$this->table()}
            WHERE {$this->columnFarmRoleId} = ? AND {$this->columnStatus} NOT IN (?,?)
            ORDER BY {$this->columnIndex}
        ", [
            $this->farmRoleId,
            SERVER_STATUS::TERMINATED,
            SERVER_STATUS::PENDING_TERMINATE,
        ]);

        if (empty($used)) {
            $index = 1;
        } else {
            $minIndex = min($used);
            $maxIndex = max($used);
            $freeIndexes = array_diff(range($minIndex, $maxIndex), $used);
            if (empty($freeIndexes)) {
                $index = $maxIndex + 1;
            } else {
                $index = min($freeIndexes);
            }
        }

        $this->index = $index;
    }

    /**
     * Add record for servers_launch_timelog
     *
     * @param   string  $field                          Field name which we should update
     * @param   int     $secondsSinceBoot   optional    Seconds since boot. Field is used for field ts_hi
     *                                                  If field is ts_launched, ts_hi, ts_bhu, ts_hu, value could be used as timestamp
     * @param   int     $secondsSinceStart  optional    Seconds since start. Field is used for field ts_hi
     * @return  bool
     */
    public function setTimeLog($field, $secondsSinceBoot = null, $secondsSinceStart = null)
    {
        if ($this->farmRoleId) {
            if ($field == 'ts_created') {
                $this->db()->Execute("
                    INSERT INTO servers_launch_timelog
                    SET `server_id` = ?,
                        `os_family` = ?,
                        `os_version` = ?,
                        `cloud` = ?,
                        `cloud_location` = ?,
                        `server_type` = ?,
                        `behaviors` = ?,
                        `ts_created` = ?
                    ON DUPLICATE KEY UPDATE
                        `ts_created` = ?
                ", [
                    $this->serverId,
                    $this->getFarmRole()->getRole()->getOs()->family,
                    $this->getFarmRole()->getRole()->getOs()->version,
                    $this->platform,
                    $this->cloudLocation,
                    $this->type,
                    implode(",", $this->getFarmRole()->getRole()->getBehaviors()),
                    time(),
                    time()
                ]);
            } else if ($field == 'ts_hi') {
                $this->db()->Execute("
                    UPDATE servers_launch_timelog
                    SET `time_to_boot` = ?,
                        `time_to_hi` = ?,
                        `last_init_status` = ?,
                        `{$field}` = ?
                    WHERE server_id=?
                ", [
                    $secondsSinceBoot - $secondsSinceStart,
                    $secondsSinceStart,
                    $this->status,
                    time(),
                    $this->serverId
                ]);
            } else if (in_array($field, ['ts_launched','ts_hi','ts_bhu','ts_hu'])) {
                $this->db()->Execute("
                    UPDATE servers_launch_timelog
                    SET `last_init_status` = ?,
                        `{$field}` = ?
                    WHERE server_id=?
                ", [
                    $this->status,
                    $secondsSinceBoot ?: time(),
                    $this->serverId
                ]);
            } else {
                return false;
            }
        } else {
            return false;
        }

        return true;
    }

    /**
     * Gets terminate reason
     *
     * @param int   $reasonId     Reason id
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
            throw new InvalidArgumentException(sprintf('Terminate reason %d doesn\'t have message', $reasonId));
        }

        return $reasonId ? $reasons[$reasonId] : '';
    }

    /**
     * Gets filter criteria by the setting
     *
     * @param   string  $name                Setting name
     * @param   string  $value      optional Setting value
     * @param   array   $criteria   optional Criteria, if already exists
     *
     * @return  array   Returns extended criteria
     */
    public function getSettingCriteria($name, $value = null, array $criteria = null)
    {
        $serverProperty = new ServerProperty();

        $alias = "sp_" . trim($this->db()->qstr($name), "'");

        $join = "
            JOIN {$serverProperty->table($alias)} ON {$this->columnServerId()} = {$serverProperty->columnServerId($alias)}
                AND {$serverProperty->columnName($alias)} = {$serverProperty->qstr('name', $name)}";

        if (isset($criteria[AbstractEntity::STMT_FROM])) {
            $criteria[AbstractEntity::STMT_FROM] .= " {$join}";
        } else {
            $criteria[AbstractEntity::STMT_FROM] = " {$join}";
        }

        if (isset($value)) {
            $where = "{$serverProperty->columnValue($alias)} = {$serverProperty->qstr('value', $value)}";

            if (isset($criteria[AbstractEntity::STMT_WHERE])) {
                $criteria[AbstractEntity::STMT_WHERE] .= " AND ($where)";
            } else {
                $criteria[AbstractEntity::STMT_WHERE] = $where;
            }
        }

        return $criteria;
    }

    /**
     * Gets old model from entity
     *
     * @return \DBServer
     */
    public function __getDBServer()
    {
        // TODO get rid of this function asap
        $dbServerInfo = [];

        foreach ($this->getIterator()->fields() as $field) {
            /* @var $field Field */
            if ($this->{$field->name} instanceof DateTime) {
                $dbServerInfo[$field->column->name] = $this->{$field->name}->format('Y-m-d H:i:s');
            } else {
                $dbServerInfo[$field->column->name] = $this->{$field->name};
            }
        }

        $DBServer = DBServer::load($dbServerInfo);

        return $DBServer;
    }

    /**
     * Gets entity from old model
     *
     * @param DBServer $DBServer   Db server object
     * @return Server
     */
    public function __getEntityFromDBServer(DBServer $DBServer)
    {
        // TODO get rid of this function asap
        $propertyMap = DBServer::getFieldPropertyMap();

        foreach ($this->getIterator()->fields() as $field) {
            /* @var $field Field */
            $value = $DBServer->{$propertyMap[$field->column->name]};

            if (($field->getType() instanceof DatetimeType) && !empty($value)) {
                $this->{$field->name} = new DateTime($value);
            } else {
                if ($this->{$field->name} != $value) {
                    $this->{$field->name} = $value;
                }
            }
        }

        return $this;
    }

    /**
     * Marks server as to be suspended.
     *
     * @param   User $user        optional The user entity
     * @return  bool
     */
    public function suspend($user = null)
    {
        if ($this->status != Server::STATUS_RUNNING) {
            return false;
        }

        if ($user instanceof User) {
            $properties = $this->properties;

            $properties[self::TERMINATED_BY_ID] = $user->getId();
            $properties[self::TERMINATED_BY_EMAIL] = $user->getEmail();

            $properties->save();
        }

        $this->update([
            'status'            => Server::STATUS_PENDING_SUSPEND,
            'shutdownScheduled' => new DateTime(Scalr::config('scalr.system.server_terminate_timeout'))
        ]);

        if (isset($this->farmId)) {
            $DBServer = $this->__getDBServer();
            Scalr::FireEvent($this->farmId, new BeforeHostTerminateEvent($DBServer, true));
        }

        return true;
    }

    /**
     * Marks server as to be terminated.
     *
     * @param   int|array       $reason      The reason possibly with the format parameters.
     * @param   bool            $forcefully  optional Method: forcefully (true) | gracefully (false)
     * @param   User            $user        optional The user entity
     * @return  bool
     */
    public function terminate($reason, $forcefully = null, $user = null)
    {
        if (in_array($this->status, [
            Server::STATUS_PENDING_TERMINATE,
            Server::STATUS_TERMINATED
        ])) {
            return false;
        }

        $forcefully = $forcefully === null ? true : (bool) $forcefully;

        $fnGetReason = function ($reasonId) {
            $args = func_get_args();
            $args[0] = Server::getTerminateReason($reasonId);
            return [call_user_func_array('sprintf', $args), $reasonId];
        };

        list($reason, $reasonId) = is_array($reason) ? call_user_func_array($fnGetReason, $reason) : $fnGetReason($reason);

        $properties = $this->properties;

        if ($user instanceof User) {
            $properties[self::TERMINATED_BY_ID] = $user->getId();
            $properties[self::TERMINATED_BY_EMAIL] = $user->getEmail();
        }

        $properties[self::REBOOTING] = 0;

        $properties->save();

        $this->update([
            'status'            => Server::STATUS_PENDING_TERMINATE,
            'shutdownScheduled' => new DateTime($forcefully ? 'now' : Scalr::config('scalr.system.server_terminate_timeout'))
        ]);

        $this->getHistory()->markAsTerminated($reason, $reasonId);

        if (isset($this->farmId)) {
            $DBServer = $this->__getDBServer();
            Scalr::FireEvent($this->farmId, new BeforeHostTerminateEvent($DBServer, false));

            // If instance was terminated outside scalr, we need manually fire HostDown
            if ($reasonId == self::TERMINATE_REASON_CRASHED) {
                Scalr::FireEvent($this->farmId, new HostDownEvent($DBServer, false));
            }
        }

        return true;
    }

    /**
     * Resume action
     *
     * @throws Exception
     */
    public function resume()
    {
        $platfromModule = PlatformFactory::NewPlatform($this->platform);
        /* @var $platfromModule AbstractPlatformModule */
        $DBServer = $this->__getDBServer();

        $platfromModule->ResumeServer($DBServer);

        $this->__getEntityFromDBServer($DBServer);
    }

    /**
     * Reboot action
     *
     * @param   bool  $hard  optional Method: Hard (true) | Soft (false)
     */
    public function reboot($hard = false)
    {
        if ($hard) {
            $DBServer = $this->__getDBServer();

            PlatformFactory::NewPlatform($this->platform)->RebootServer($DBServer, false);

            $this->__getEntityFromDBServer($DBServer);
        } else {
            $this->scalarizr->system->reboot();
        }
    }

}
