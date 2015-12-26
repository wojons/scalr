<?php

namespace Scalr\Model\Entity;

use Exception;
use Scalr\Model\AbstractEntity;
use RuntimeException;

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

            $db->Execute("
                UPDATE `dm_deployment_tasks` SET status=? WHERE server_id=?
            ", [
                \Scalr_Dm_DeploymentTask::STATUS_ARCHIVED,
                $this->serverId
            ]);

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
     * Updates Server status
     *
     * @param    string    $serverStatus  The status
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
            self::INFO_INSTANCE_TYPE_NAME,
            self::INFO_INSTANCE_VCPUS,
        ];
    }
}
