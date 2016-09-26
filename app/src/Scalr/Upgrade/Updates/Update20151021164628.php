<?php

namespace Scalr\Upgrade\Updates\Update20151021164628
{
    use DateTime;
    use Exception;
    use Scalr\Model\AbstractEntity;
    use stdClass;

    /**
     * @Entity
     * @Table(name="cloud_locations")
     */
    class CloudLocation extends AbstractEntity
    {

        /**
         * Identifier
         *
         * This identifier is calculated using:
         * substr(sha1(platform + ';' + $cloud_location + ';' + $normalizedUrl), 0, 36)
         *
         * @Id
         * @Column(type="uuid")
         * @var string
         */
        public $cloudLocationId;

        /**
         * Cloud platform
         *
         * @Column(type="string")
         * @var string
         */
        public $platform;

        /**
         * Normalized endpoint url
         *
         * @Column(type="string")
         * @var string
         */
        public $url;

        /**
         * The cloud location
         *
         * @Column(type="string")
         * @var string
         */
        public $cloudLocation;

        /**
         * Update date
         *
         * The date when this record was updated.
         *
         * @Column(type="datetime")
         * @var \DateTime
         */
        public $updated;

        /**
         * Collection of the instance types
         *
         * @var \Scalr\Model\Collections\ArrayCollection
         */
        private $instanceTypes;

        /**
         * Constructor
         */
        public function __construct()
        {
            $this->updated = new DateTime('now');
            $this->url = '';
        }

        /**
         * Gets instance types for this cloud location
         *
         * @return  \Scalr\Model\Collections\ArrayCollection Returns collection of the instance types
         */
        public function fetchInstanceTypes()
        {
            $this->instanceTypes = CloudInstanceType::result(CloudInstanceType::RESULT_ENTITY_COLLECTION)->find([['cloudLocationId' => $this->cloudLocationId]]);

            return $this->instanceTypes;
        }

        /**
         * Gets instance types associated with the cloud location
         *
         * @return \Scalr\Model\Collections\ArrayCollection
         */
        public function getInstanceTypes()
        {
            if ($this->instanceTypes === null) {
                $this->fetchInstanceTypes();
            }

            return $this->instanceTypes;
        }

        /**
         * Gets active instance types associated with the cloud location
         *
         * @return \Scalr\Model\Collections\ArrayCollection
         */
        public function getActiveInstanceTypes()
        {
            return $this->getInstanceTypes()->filterByStatus(CloudInstanceType::STATUS_ACTIVE);
        }

        /**
         * Initializes a new Entity using specified parameters
         *
         * @param   string   $platform      A cloud platform
         * @param   string   $cloudLocation A cloud location
         * @param   string   $url           optional A cloud endpoint url
         * @return  CloudLocation
         */
        public static function init($platform, $cloudLocation, $url = '')
        {
            $entity = new static;
            $entity->cloudLocationId = static::calculateCloudLocationId($platform, $cloudLocation, $url);
            $entity->platform = $platform;
            $entity->url = static::normalizeUrl($url);
            $entity->cloudLocation = $cloudLocation;

            return $entity;
        }

        /**
         * Checks whether current platform has cached instance types in database
         *
         * @param   string    $platform      A cloud platform
         * @param   string    $url           optional A cloud endpoint url
         * @param   string    $cloudLocation optional A cloud location
         * @param   int       $lifetime      optional Cache lifetime in seconds.
         *                                   If it isn't provided it will use scalr.cache.instance_types.lifetime config value
         * @return  boolean   Returns true if current platform has cached instance types in database
         */
        public static function hasInstanceTypes($platform, $url = '', $cloudLocation = null, $lifetime = null)
        {
            $db = \Scalr::getDb();

            $options = [$platform, CloudInstanceType::STATUS_ACTIVE, static::normalizeUrl($url)];
            $stmt = "";

            if ($cloudLocation !== null) {
                $options[] = $cloudLocation;
                $stmt .= " AND cl.`cloud_location` = ?";
            }

            if ($lifetime === null) {
                $lifetime = (int) \Scalr::config('scalr.cache.instance_types.lifetime');
            }

            $stmt .= " AND cl.`updated` > '" . date("Y-m-d H:i:s", time() - intval($lifetime)) . "'";

            $res = $db->GetOne("
            SELECT EXISTS(
                SELECT 1 FROM `cloud_locations` cl
                JOIN `cloud_instance_types` cit ON cit.cloud_location_id = cl.cloud_location_id
                WHERE cl.`platform` = ? AND cit.`status` = ? AND cl.`url` = ? {$stmt}
            )
        " , $options);

            return $res ? true : false;
        }

        /**
         * Updates instance types in a database
         *
         * @param   string    $platform       A cloud platform
         * @param   string    $url            A cloud endpoint url
         * @param   string    $cloudLocation  A cloud location
         * @param   array     $instanceTypes  Array of the instance types looks like [instanceTypeId => [prop => value]]
         */
        public static function updateInstanceTypes($platform, $url, $cloudLocation, array $instanceTypes)
        {
            //One representation for platforms which does not support different cloud locations
            if (empty($cloudLocation)) {
                $cloudLocation = '';
            }

            //Normalizes url to use in queries
            $url = static::normalizeUrl($url);

            //Search for cloud location record
            $cl = static::findOne([['platform' => $platform], ['url' => $url], ['cloudLocation' => $cloudLocation]]);

            if (!($cl instanceof CloudLocation)) {
                $isNew = true;
                //There are no records yet
                $cl = static::init($platform, $cloudLocation, $url);
            }

            //Starts database transaction
            $cl->db()->BeginTrans();

            try {
                if (!empty($isNew)) {
                    //We have to create a parent table record in order to foreign key does not bark
                    $cl->save();
                }

                //Existing instance types
                $updatedIds = [];

                //Updates instance types which were known before
                foreach ($cl->getInstanceTypes() as $cit) {
                    /* @var $cit \Scalr\Model\Entity\CloudInstanceType */
                    $changes = 0;

                    if (!empty($instanceTypes[$cit->instanceTypeId]) && is_array($instanceTypes[$cit->instanceTypeId])) {
                        //Updates status
                        $changes = $cit->updateProperties(array_merge($instanceTypes[$cit->instanceTypeId], ['status' => $cit::STATUS_ACTIVE]));

                        //Remembers which instances have been handled
                        $updatedIds[] = $cit->instanceTypeId;
                    } else {
                        //Deactivates this instance type as it does not exist for now
                        $cit->status = $cit::STATUS_INACTIVE;

                        $changes++;
                    }

                    //Updates a record only if real changes happen
                    if ($changes) $cit->save();
                }

                //New instance types which were not known before
                foreach (array_diff_key($instanceTypes, array_flip($updatedIds)) as $instanceTypeId => $array) {
                    if (empty($array) || !is_array($array)) {
                        continue;
                    }

                    $cit = new CloudInstanceType($cl->cloudLocationId, $instanceTypeId);
                    $cit->updateProperties($array);
                    $cit->status = $cit::STATUS_ACTIVE;
                    $cit->save();
                }

                //Checks whether we need to refresh an update time
                if (empty($isNew)) {
                    $cl->updated = new DateTime('now');
                    $cl->save();
                }
            } catch (Exception $e) {
                $cl->db()->RollbackTrans();

                throw $e;
            }

            $cl->db()->CommitTrans();
        }

        /**
         * Calculates uuid for the specified entity
         *
         * @param   string    $platform       Cloud platform
         * @param   string    $cloudLocation  Cloud location
         * @param   string    $url            optional Cloud url
         * @return  string    Returns UUID
         */
        public static function calculateCloudLocationId($platform, $cloudLocation, $url = '')
        {
            $hash = sha1(sprintf("%s;%s;%s", $platform, $cloudLocation, self::normalizeUrl($url)));

            return sprintf(
                "%s-%s-%s-%s-%s",
                substr($hash, 0, 8),
                substr($hash, 8, 4),
                substr($hash, 12, 4),
                substr($hash, 16, 4),
                substr($hash, 20, 12)
            );
        }

        /**
         * Normalizes url
         *
         * @param   string    $url  The url
         * @return  string    Returns normalized url
         */
        public static function normalizeUrl($url)
        {
            if (empty($url)) return '';

            $arr = parse_url($url);

            if (empty($arr['scheme'])) {
                //IMPORTANT! Normalized url can be used as a parameter
                $arr = parse_url('http://' . $url);
            }

            //Scheme should be omitted
            $ret = $arr['host'] . (isset($arr['port']) ? ':' . $arr['port'] : '') .
                (isset($arr['path']) ? rtrim($arr['path'], '/') : '');

            return $ret;
        }

        /**
         * Forces cache to warm-up.
         */
        public static function warmUp()
        {
            \Scalr::getDb()->Execute("
            UPDATE cloud_instance_types
            SET `status` = ?
            WHERE `status` = ?
        ", [
                CloudInstanceType::STATUS_OBSOLETE,
                CloudInstanceType::STATUS_ACTIVE
            ]);
        }
    }

    /**
     * @Entity
     * @Table(name="cloud_instance_types")
     */
    class CloudInstanceType extends AbstractEntity
    {

        /**
         * Instance type is inactive
         */
        const STATUS_INACTIVE = 0;

        /**
         * Instance type is active
         */
        const STATUS_ACTIVE = 1;

        /**
         * Instance type is marked as obsolete and has to be refreshed
         */
        const STATUS_OBSOLETE = 2;

        /**
         * This type of flavour is unsupported by Scalr
         */
        const STATUS_UNSUPPORTED = 3;

        /**
         * Identifier
         *
         * This identifier is calculated using:
         * substr(sha1(platform + ';' + $cloud_location + ';' + $normalizedUrl), 0, 36)
         *
         * @Id
         * @Column(type="uuid")
         * @var string
         */
        public $cloudLocationId;

        /**
         * Identifier of the Instance Type
         *
         * @Id
         * @Column(type="string")
         * @var string
         */
        public $instanceTypeId;

        /**
         * The name of the instance type
         *
         * @Column(type="string")
         * @var string
         */
        public $name;

        /**
         * Memory info
         *
         * @Column(type="string")
         * @var string
         */
        public $ram = '';

        /**
         * CPU info
         *
         * @Column(type="string")
         * @var string
         */
        public $vcpus = '';

        /**
         * Disk info
         *
         * @Column(type="string")
         * @var string
         */
        public $disk = '';

        /**
         * Storage type info
         *
         * @Column(type="string")
         * @var string
         */
        public $type = '';

        /**
         * Notes
         *
         * @Column(type="string")
         * @var string
         */
        public $note = '';

        /**
         * Misc options
         *
         * @Column(type="json")
         * @var object
         */
        public $options;

        /**
         * A status
         *
         * @Column(type="integer")
         * @var string
         */
        public $status;

        /**
         * Options which are supported and should be stored in options property
         *
         * @var array
         */
        protected static $_allowedOptions = ['ebsencryption', 'description'];

        /**
         * Options which are natively supported by the entity
         *
         * @var array
         */
        protected static $_allowedProperties = ['name', 'ram', 'vcpus', 'disk', 'type', 'note', 'status'];

        /**
         * Constructor
         *
         * @param   string $cloudLocationId optional An identifier of the cloud location (UUID)
         * @param   string $instanceTypeId  optional An identifier of the instance type
         */
        public function __construct($cloudLocationId = null, $instanceTypeId = null)
        {
            $this->cloudLocationId = $cloudLocationId;
            $this->instanceTypeId = $instanceTypeId;
            $this->options = new stdClass();
            $this->status = self::STATUS_ACTIVE;
        }

        /**
         * Synchronizes object's properties with those specified in array
         *
         * @param   array   $array  Array of the properties' values
         * @return  int     Returns number of the changes
         */
        public function updateProperties($array)
        {
            $changes = 0;

            //Options which are natively supported by the entity
            $props = array_merge(static::$_allowedProperties, static::$_allowedOptions);

            //Updates properties
            foreach ($props as $property) {
                if (array_key_exists($property, $array)) {
                    if (in_array($property, static::$_allowedOptions)) {
                        if (!property_exists($this->options, $property) || $this->options->$property != $array[$property]) {
                            $this->options->$property = $array[$property];
                            $changes++;
                        }
                    } else if ($this->$property != $array[$property]) {
                        $this->$property = $array[$property];
                        $changes++;
                    }
                }
            }

            return $changes;
        }

        /**
         * Gets array of the properties
         *
         * @return  array Returns all properties as array
         */
        public function getProperties()
        {
            $result = [];
            // All properties
            foreach (static::$_allowedProperties as $property) {
                $result[$property] = $this->$property;
            }

            $opt = (array) $this->options;
            //Proceeds with options
            foreach (static::$_allowedOptions as $property) {
                if (array_key_exists($property, $opt)) {
                    $result[$property] = $opt[$property];
                }
            }

            unset($result['status']);

            return $result;
        }
    }

    /**
     * Server entity
     *
     * @author N.V.
     *
     * @Entity
     * @Table(name="servers")
     */
    class Server extends AbstractEntity
    {

        //System properties
        const SYSTEM_IGNORE_INBOUND_MESSAGES = 'system.ignore_inbound_messages';
        const SYSTEM_USER_DATA_METHOD        = 'system.user_data_method';

        //Scalarizr properties
        const SZR_KEY			 = 'scalarizr.key';
        //permanent, one-time
        const SZR_KEY_TYPE		         = 'scalarizr.key_type';
        const SZR_MESSAGE_FORMAT             = 'scalarizr.message_format';

        const SZR_ONETIME_KEY_EXPIRED        = 'scalarizr.onetime_key_expired';

        //0.5 or 0.2-139
        const SZR_VESION		         = 'scalarizr.version';
        const SZR_UPD_CLIENT_VERSION         = 'scalarizr.update_client.version';

        //New Importing process
        const SZR_IMPORTING_VERSION              = 'scalarizr.import.version';
        const SZR_IMPORTING_STEP                 = 'scalarizr.import.step';
        const SZR_IMPORTING_OUT_CONNECTION       = 'scalarizr.import.outbound_connection';
        const SZR_IMPORTING_OUT_CONNECTION_ERROR = 'scalarizr.import.outbound_connection.error';

        const SZR_IMPORTING_IMAGE_ID         = 'scalarizr.import.image_id';
        const SZR_IMPORTING_ROLE_NAME        = 'scalarizr.import.role_name';
        const SZR_IMPORTING_OBJECT           = 'scalarizr.import.object';
        const SZR_IMPORTING_BEHAVIOR         = 'scalarizr.import.behaviour';
        const SZR_IMPORTING_LAST_LOG_MESSAGE = 'scalarizr.import.last_log_msg';
        const SZR_IMPORTING_BUNDLE_TASK_ID   = 'scalarizr.import.bundle_task_id';
        const SZR_IMPORTING_OS_FAMILY        = 'scalarizr.import.os_family';
        const SZR_IMPORTING_LEAVE_ON_FAIL    = 'scalarizr.import.leave_on_fail';
        const SZR_DEV_SCALARIZR_BRANCH       = 'scalarizr.dev.scalarizr.branch';

        const SZR_IMPORTING_CHEF_SERVER_ID   = 'scalarizr.import.chef.server_id';
        const SZR_IMPORTING_CHEF_ENVIRONMENT = 'scalarizr.import.chef.environment';
        const SZR_IMPORTING_CHEF_ROLE_NAME   = 'scalarizr.import.chef.role_name';

        const SZR_IS_INIT_FAILED    = 'scalarizr.is_init_failed';
        const SZR_IS_INIT_ERROR_MSG = 'scalarizr.init_error_msg';
        const LAUNCH_LAST_TRY       = 'system.launch.last_try';
        const LAUNCH_ATTEMPT        = 'system.launch.attempt';
        const LAUNCH_ERROR          = 'system.launch.error';
        const LAUNCH_REASON         = 'system.launch.reason';
        const LAUNCH_REASON_ID      = 'system.launch.reason_id';
        const SUB_STATUS 		= 'system.sub-status';

        const SZR_IMPORTING_MYSQL_SERVER_TYPE = 'scalarizr.import.mysql_server_type';

        const SZR_SNMP_PORT   = 'scalarizr.snmp_port';
        const SZR_CTRL_PORT   = 'scalarizr.ctrl_port';
        const SZR_API_PORT    = 'scalarizr.api_port';
        const SZR_UPDC_PORT   = 'scalarizr.updc_port';
        const CUSTOM_SSH_PORT = 'scalarizr.ssh_port';

        //Database properties
        const DB_MYSQL_MASTER             = 'db.mysql.master';
        const DB_MYSQL_REPLICATION_STATUS = 'db.mysql.replication_status';

        //DNS properties
        const EXCLUDE_FROM_DNS = 'dns.exclude_instance';

        //System properties
        const ARCHITECTURE                 = "system.architecture";
        const REBOOTING                    = "system.rebooting";
        const MISSING                      = "system.missing";
        const INITIALIZED_TIME             = "system.date.initialized";
        const TERMINATION_REQUEST_UNIXTIME = "system.termination.request.unixtime";

        //Healthcheck properties
        const HEALTHCHECK_FAILED = "system.healthcheck.failed";
        const HEALTHCHECK_TIME   = "system.healthcheck.time";

        //Statistics
        const STATISTICS_BW_IN         = "statistics.bw.in";
        const STATISTICS_BW_OUT        = "statistics.bw.out";
        const STATISTICS_LAST_CHECK_TS = "statistics.lastcheck_ts";

        //Farm derived properties
        const FARM_CREATED_BY_ID    = 'farm.created_by_id';
        const FARM_CREATED_BY_EMAIL = 'farm.created_by_email';
        //They are necessary for cost analytics
        const FARM_PROJECT_ID = 'farm.project_id';
        const FARM_ROLE_ID    = 'farm_role.id';
        const ROLE_ID         = 'role.id';

        //Environment derived properties
        const ENV_CC_ID = 'env.cc_id';

        //It is used in CA
        const OS_TYPE = 'os_type';

        const LAUNCHED_BY_ID      = 'audit.launched_by_id';
        const LAUNCHED_BY_EMAIL   = 'audit.launched_by_email';
        const TERMINATED_BY_ID    = 'audit.terminated_by_id';
        const TERMINATED_BY_EMAIL = 'audit.terminated_by_email';

        const SCALR_INBOUND_REQ_RATE  = 'scalr.inbound.req.rate';

        const INFO_INSTANCE_TYPE_NAME = 'info.instance_type_name';
        const INFO_INSTANCE_VCPUS     = 'info.instance_vcpus';

        // Statuses
        const STATUS_TEMPORARY         = "Temporary";

        const STATUS_RUNNING           = "Running";
        const STATUS_PENDING_LAUNCH    = "Pending launch";
        const STATUS_PENDING           = "Pending";
        const STATUS_INIT              = "Initializing";
        const STATUS_IMPORTING         = "Importing";

        const STATUS_PENDING_TERMINATE = "Pending terminate";
        const STATUS_TERMINATED        = "Terminated";

        const STATUS_PENDING_SUSPEND   = "Pending suspend";
        const STATUS_SUSPENDED         = "Suspended";
        const STATUS_RESUMING          = "Resuming";

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
         * Index within the Farm Role
         *
         * @Column(type="integer",nullable=true)
         * @var int
         */
        public $index;

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
    }
}

namespace Scalr\Upgrade\Updates
{
    use DBServer;
    use Exception;
    use Scalr\Modules\PlatformFactory;
    use Scalr\Upgrade\SequenceInterface;
    use Scalr\Upgrade\AbstractUpdate;
    use Scalr\Upgrade\Updates\Update20151021164628\CloudInstanceType;
    use Scalr\Upgrade\Updates\Update20151021164628\CloudLocation;
    use Scalr\Upgrade\Updates\Update20151021164628\Server;
    use Scalr_Environment;
    use SERVER_PLATFORMS;

    class Update20151021164628 extends AbstractUpdate implements SequenceInterface
    {

        protected $uuid = 'eae93935-fc0c-432f-9fa8-b55530a8b7cc';

        protected $depends = [];

        protected $description = 'Initialize instance type names for servers and servers_history.';

        protected $ignoreChanges = true;

        protected $dbservice = 'adodb';

        /**
         * {@inheritdoc}
         * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
         */
        public function getNumberStages()
        {
            return 2;
        }

        protected function run1($stage)
        {
            $this->db->BeginTrans();

            try {
                $this->console->out("Initialize type field with data from server_properties.");
                $this->db->Execute("
                UPDATE `servers` s
                JOIN `server_properties` sp ON sp.`server_id` = s.`server_id`
                    AND sp.`name` = ?
                SET s.`type` = sp.`value`
                WHERE s.`platform` = ?
                    AND s.`type` IS NULL
                    AND sp.`value` <> ''
            ", ['cloudstack.server_type', SERVER_PLATFORMS::CLOUDSTACK]);

                $this->console->out("Initialize instance_type_name field with data for ec2 and gce platforms.");
                $this->db->Execute("
                UPDATE `servers` SET `instance_type_name` = `type`
                WHERE `platform` IN (?, ?)
                    AND `type` <> ''
                    AND `type` IS NOT NULL
                    AND `instance_type_name` IS NULL
            ", [SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE]);

                $this->db->Execute("
                UPDATE `servers_history` SET `instance_type_name` = `type`
                WHERE `type` IS NOT NULL
                    AND `type` <> ''
                    AND `platform` IN (?, ?)
                    AND `instance_type_name` IS NULL
            ", [SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE]);

                $this->db->CommitTrans();
            } catch (Exception $e) {
                $this->db->RollbackTrans();
                throw $e;
            }
        }

        public function run2($stage)
        {
            $this->console->out("Initialize instance_type_name field in servers table for other platforms.");

            $servers = Server::find([
                ['instanceTypeName' => null],
                ['platform'         => ['$nin' => [SERVER_PLATFORMS::EC2, SERVER_PLATFORMS::GCE]]],
                ['type'             => ['$ne' => null]],
                ['cloudLocation'    => ['$ne' => null]]
            ], null, ['envId' => true, 'platform' => true]);

            /* @var $env Scalr_Environment */
            $env = null;
            $platform = null;

            foreach ($servers as $server) {
                /* @var $server Server */
                try {
                    if (!isset($env) || $env->id != $server->envId) {
                        $env = Scalr_Environment::init()->loadById($server->envId);
                        $platform = null;
                    }

                    if (!isset($platform) || $platform != $server->platform) {
                        $platform = $server->platform;
                        $platformModule = PlatformFactory::NewPlatform($server->platform);
                        $url = $platformModule->getEndpointUrl($env);
                    }

                    $cloudLocationId = CloudLocation::calculateCloudLocationId($server->platform, $server->cloudLocation, $url);
                    $instanceTypeEntity = CloudInstanceType::findPk($cloudLocationId, $server->type);
                    /* @var $instanceTypeEntity CloudInstanceType */
                    if ($instanceTypeEntity) {
                        $dbServer = DBServer::LoadByID($server->serverId);
                        $dbServer->update(['instanceTypeName' => $instanceTypeEntity->name]);
                    }
                } catch (Exception $e) {
                }
            }
        }
    }
}