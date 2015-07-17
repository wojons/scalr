<?php

use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity\Script;

class DBFarmRole
{
    const SETTING_EXCLUDE_FROM_DNS                  =   'dns.exclude_role';
    const SETTING_DNS_INT_RECORD_ALIAS              =   'dns.int_record_alias';
    const SETTING_DNS_EXT_RECORD_ALIAS              =   'dns.ext_record_alias';

    const SETTING_DNS_CREATE_RECORDS                =   'dns.create_records';


    const SETTING_SCALING_ENABLED                   =   'scaling.enabled';
    const SETTING_SCALING_MIN_INSTANCES             =   'scaling.min_instances';
    const SETTING_SCALING_MAX_INSTANCES             =   'scaling.max_instances';
    const SETTING_SCALING_POLLING_INTERVAL          =   'scaling.polling_interval';
    const SETTING_SCALING_LAST_POLLING_TIME         =   'scaling.last_polling_time';
    const SETTING_SCALING_KEEP_OLDEST               =   'scaling.keep_oldest';
    const SETTING_SCALING_IGNORE_FULL_HOUR          =   'scaling.ignore_full_hour';
    const SETTING_SCALING_SAFE_SHUTDOWN             =   'scaling.safe_shutdown';
    const SETTING_SCALING_EXCLUDE_DBMSR_MASTER      =   'scaling.exclude_dbmsr_master';
    const SETTING_SCALING_ONE_BY_ONE                =   'scaling.one_by_one';

    //advanced timeout limits for scaling
    const SETTING_SCALING_UPSCALE_TIMEOUT           =   'scaling.upscale.timeout';
    const SETTING_SCALING_DOWNSCALE_TIMEOUT         =   'scaling.downscale.timeout';
    const SETTING_SCALING_UPSCALE_TIMEOUT_ENABLED   =   'scaling.upscale.timeout_enabled';
    const SETTING_SCALING_DOWNSCALE_TIMEOUT_ENABLED =   'scaling.downscale.timeout_enabled';
    const SETTING_SCALING_UPSCALE_DATETIME          =   'scaling.upscale.datetime';
    const SETTING_SCALING_DOWNSCALE_DATETIME        =   'scaling.downscale.datetime';

    const SETTING_BALANCING_USE_ELB         =       'lb.use_elb';
    const SETTING_BALANCING_HOSTNAME        =       'lb.hostname';
    const SETTING_BALANCING_NAME            =       'lb.name';
    const SETTING_BALANCING_HC_TIMEOUT      =       'lb.healthcheck.timeout';
    const SETTING_BALANCING_HC_TARGET       =       'lb.healthcheck.target';
    const SETTING_BALANCING_HC_INTERVAL     =       'lb.healthcheck.interval';
    const SETTING_BALANCING_HC_UTH          =       'lb.healthcheck.unhealthythreshold';
    const SETTING_BALANCING_HC_HTH          =       'lb.healthcheck.healthythreshold';
    const SETTING_BALANCING_HC_HASH         =       'lb.healthcheck.hash';
    const SETTING_BALANCING_AZ_HASH         =       'lb.avail_zones.hash';

    /** RACKSPACE Settings **/
    const SETTING_RS_FLAVOR_ID              =       'rs.flavor-id';

    /** OPENSTACK Settings **/
    const SETTING_OPENSTACK_FLAVOR_ID       =       'openstack.flavor-id';
    const SETTING_OPENSTACK_IP_POOL         =       'openstack.ip-pool';
    const SETTING_OPENSTACK_NETWORKS        =       'openstack.networks';
    const SETTING_OPENSTACK_SECURITY_GROUPS_LIST =  'openstack.security_groups.list';
    const SETTING_OPENSTACK_KEEP_FIP_ON_SUSPEND =   'openstack.keep_fip_on_suspend';
    const SETTING_OPENSTACK_AVAIL_ZONE      =       'openstack.availability_zone';

    /** GCE Settings **/
    const SETTING_GCE_MACHINE_TYPE          =       'gce.machine-type';
    const SETTING_GCE_NETWORK               =       'gce.network';
    const SETTING_GCE_CLOUD_LOCATION        =       'gce.cloud-location';
    const SETTING_GCE_ON_HOST_MAINTENANCE   =       'gce.on-host-maintenance';
    const SETTING_GCE_USE_STATIC_IPS        =       'gce.use_static_ips';
    const SETTING_GCE_STATIC_IPS_MAP        =       'gce.static_ips.map';
    const SETTING_GCE_REGION                =       'gce.region';

    /** Cloudstack Settings **/
    const SETTING_CLOUDSTACK_SERVICE_OFFERING_ID        =       'cloudstack.service_offering_id';
    const SETTING_CLOUDSTACK_NETWORK_OFFERING_ID        =       'cloudstack.network_offering_id';
    const SETTING_CLOUDSTACK_DISK_OFFERING_ID           =       'cloudstack.disk_offering_id';
    const SETTING_CLOUDSTACK_NETWORK_ID                 =       'cloudstack.network_id';
    const SETTING_CLOUDSTACK_NETWORK_TYPE               =       'cloudstack.network_type';
    const SETTING_CLOUDSTACK_SHARED_IP_ADDRESS          =       'cloudstack.shared_ip.address';
    const SETTING_CLOUDSTACK_SHARED_IP_ID               =       'cloudstack.shared_ip.id';
    const SETTING_CLOUDSTACK_SECURITY_GROUPS_LIST       =       'cloudstack.security_groups.list';

    const SETIING_CLOUDSTACK_USE_STATIC_NAT             =       'cloudstack.use_static_nat';
    const SETIING_CLOUDSTACK_STATIC_NAT_MAP             =       'cloudstack.static_nat.map';
    const SETIING_CLOUDSTACK_STATIC_NAT_PRIVATE_MAP     =       'cloudstack.static_nat.private_map';

    /** EUCA Settings **/
    const SETTING_EUCA_INSTANCE_TYPE        =       'euca.instance_type';
    const SETTING_EUCA_AVAIL_ZONE           =       'euca.availability_zone';
    const SETTING_EUCA_EKI_ID               =       'euca.eki_id';
    const SETTING_EUCA_ERI_ID               =       'euca.eri_id';
    const SETTING_EUCA_SECURITY_GROUPS_LIST =       'euca.security_groups.list';

    /** AWS EC2 Settings **/
    const SETTING_AWS_INSTANCE_TYPE         =       'aws.instance_type';
    const SETTING_AWS_AVAIL_ZONE            =       'aws.availability_zone';
    const SETTING_AWS_USE_ELASIC_IPS        =       'aws.use_elastic_ips';
    const SETTING_AWS_ELASIC_IPS_MAP        =       'aws.elastic_ips.map';

    const SETTING_AWS_IAM_INSTANCE_PROFILE_ARN =    'aws.iam_instance_profile_arn';

    const SETTING_AWS_EBS_OPTIMIZED         =       'aws.ebs_optimized';

    const SETTING_AWS_ELB_ENABLED           =       'aws.elb.enabled';
    const SETTING_AWS_ELB_ID                =       'aws.elb.id';

    const SETTING_AWS_USE_EBS               =       'aws.use_ebs';
    const SETTING_AWS_EBS_IOPS              =       'aws.ebs_iops';
    const SETTING_AWS_EBS_TYPE              =       'aws.ebs_type';
    const SETTING_AWS_EBS_SIZE              =       'aws.ebs_size';
    const SETTING_AWS_EBS_SNAPID            =       'aws.ebs_snapid';
    const SETTING_AWS_EBS_MOUNT             =       'aws.ebs_mount';
    const SETTING_AWS_EBS_MOUNTPOINT        =       'aws.ebs_mountpoint';
    const SETTING_AWS_AKI_ID                =       'aws.aki_id';
    const SETTING_AWS_ARI_ID                =       'aws.ari_id';
    const SETTING_AWS_ENABLE_CW_MONITORING  =       'aws.enable_cw_monitoring';
    const SETTING_AWS_SECURITY_GROUPS_LIST  =       'aws.security_groups.list';
    const SETTING_AWS_S3_BUCKET             =       'aws.s3_bucket';
    const SETTING_AWS_CLUSTER_PG            =       'aws.cluster_pg';

    const SETTING_AWS_INSTANCE_NAME_FORMAT  =       'aws.instance_name_format';
    const SETTING_AWS_SHUTDOWN_BEHAVIOR     =       'aws.instance_initiated_shutdown_behavior';

    const SETTING_AWS_SG_LIST               =       'aws.additional_security_groups';
    const SETTING_AWS_TAGS_LIST             =       'aws.additional_tags';
    const SETTING_AWS_SG_LIST_APPEND        =       'aws.additional_security_groups.append';

    const SETTING_AWS_VPC_AVAIL_ZONE        =       'aws.vpc_avail_zone';
    const SETTING_AWS_VPC_INTERNET_ACCESS   =       'aws.vpc_internet_access';
    const SETTING_AWS_VPC_SUBNET_ID         =       'aws.vpc_subnet_id';
    const SETTING_AWS_VPC_ROUTING_TABLE_ID  =       'aws.vpc_routing_table_id';
    const SETTING_AWS_VPC_ASSOCIATE_PUBLIC_IP   =       'aws.vpc_associate_public_ip';

    /** MySQL options **/
    const SETTING_MYSQL_PMA_USER            =       'mysql.pma.username';
    const SETTING_MYSQL_PMA_PASS            =       'mysql.pma.password';
    const SETTING_MYSQL_PMA_REQUEST_TIME    =       'mysql.pma.request_time';
    const SETTING_MYSQL_PMA_REQUEST_ERROR   =       'mysql.pma.request_error';

    const SETTING_MYSQL_BUNDLE_WINDOW_START =       'mysql.bundle_window.start';
    const SETTING_MYSQL_BUNDLE_WINDOW_END   =       'mysql.bundle_window.end';

    const SETTING_MYSQL_BUNDLE_WINDOW_START_HH = 'mysql.pbw1_hh';
    const SETTING_MYSQL_BUNDLE_WINDOW_START_MM = 'mysql.pbw1_mm';

    const SETTING_MYSQL_BUNDLE_WINDOW_END_HH = 'mysql.pbw2_hh';
    const SETTING_MYSQL_BUNDLE_WINDOW_END_MM = 'mysql.pbw2_mm';

    const SETTING_MYSQL_EBS_SNAPS_ROTATE            = 'mysql.ebs.rotate';
    const SETTING_MYSQL_EBS_SNAPS_ROTATION_ENABLED  = 'mysql.ebs.rotate_snaps';

    const SETTING_MYSQL_BCP_ENABLED                 = 'mysql.enable_bcp';
    const SETTING_MYSQL_BCP_EVERY                   = 'mysql.bcp_every';
    const SETTING_MYSQL_BUNDLE_ENABLED              = 'mysql.enable_bundle';
    const SETTING_MYSQL_BUNDLE_EVERY                = 'mysql.bundle_every';
    const SETTING_MYSQL_LAST_BCP_TS                 = 'mysql.dt_last_bcp';
    const SETTING_MYSQL_LAST_BUNDLE_TS              = 'mysql.dt_last_bundle';
    const SETTING_MYSQL_IS_BCP_RUNNING              = 'mysql.isbcprunning';
    const SETTING_MYSQL_IS_BUNDLE_RUNNING           = 'mysql.isbundlerunning';
    const SETTING_MYSQL_BCP_SERVER_ID               = 'mysql.bcp_server_id';
    const SETTING_MYSQL_BUNDLE_SERVER_ID            = 'mysql.bundle_server_id';
    /*Scalr_Db_Msr*/ const SETTING_MYSQL_DATA_STORAGE_ENGINE        = 'mysql.data_storage_engine';
    const SETTING_MYSQL_SLAVE_TO_MASTER             = 'mysql.slave_to_master';

    /* MySQL users credentials */
    const SETTING_MYSQL_ROOT_PASSWORD               = 'mysql.root_password';
    const SETTING_MYSQL_REPL_PASSWORD               = 'mysql.repl_password';
    const SETTING_MYSQL_STAT_PASSWORD               = 'mysql.stat_password';

    const SETTING_MYSQL_LOG_FILE                    = 'mysql.log_file';
    const SETTING_MYSQL_LOG_POS                     = 'mysql.log_pos';

    /*Scalr_Db_Msr*/ const SETTING_MYSQL_SCALR_SNAPSHOT_ID          = 'mysql.scalr.snapshot_id';
    /*Scalr_Db_Msr*/ const SETTING_MYSQL_SCALR_VOLUME_ID                = 'mysql.scalr.volume_id';

    /*
     * @deprecated
     */
    const SETTING_MYSQL_SNAPSHOT_ID         = 'mysql.snapshot_id';
    const SETTING_MYSQL_MASTER_EBS_VOLUME_ID= 'mysql.master_ebs_volume_id';
    const SETTING_MYSQL_EBS_VOLUME_SIZE     = 'mysql.ebs_volume_size';
    const SETTING_MYSQL_EBS_TYPE            = 'mysql.ebs.type';
    const SETTING_MYSQL_EBS_IOPS            = 'mysql.ebs.iops';

    /////////////////////////////////////////////////

    const SETTING_SYSTEM_REBOOT_TIMEOUT     =       'system.timeouts.reboot';
    const SETTING_SYSTEM_LAUNCH_TIMEOUT     =       'system.timeouts.launch';
    const SETTING_SYSTEM_NEW_PRESETS_USED   =       'system.new_presets_used';

    const SETTING_INFO_INSTANCE_TYPE_NAME   =       'info.instance_type_name';

    const TYPE_CFG = 1; // For configuration
    const TYPE_LCL = 2; // For lifecycle

    public
        $ID,
        $FarmID,
        $LaunchIndex,
        $RoleID,
        $NewRoleID,
        $Alias,
        $CloudLocation,
        $Platform;

    private $DB,
            $dbRole,
            $DBFarm,
            $SettingsCache = array();

    private static $FieldPropertyMap = array(
        'id'            => 'ID',
        'farmid'        => 'FarmID',
        'role_id'       => 'RoleID',
        'alias'         => 'Alias',
        'new_role_id'   => 'NewRoleID',
        'launch_index'  => 'LaunchIndex',
        'platform'      => 'Platform',
        'cloud_location'=> 'CloudLocation'
    );

    /**
     * Constructor
     * @param $instance_id
     * @return void
     */
    public function __construct($farm_roleid)
    {
        $this->DB = \Scalr::getDb();

        $this->ID = $farm_roleid;
    }

    public function __sleep()
    {
        return array_values(self::$FieldPropertyMap);
    }

    public function __wakeup()
    {
        $this->DB = \Scalr::getDb();
    }

    public function applyDefinition($definition, $reset = false)
    {
        $resetSettings = array(
            DBFarmRole::SETTING_BALANCING_USE_ELB,
            DBFarmRole::SETTING_BALANCING_HOSTNAME,
            DBFarmRole::SETTING_BALANCING_NAME,
            DBFarmRole::SETTING_BALANCING_HC_TIMEOUT,
            DBFarmRole::SETTING_BALANCING_HC_TARGET,
            DBFarmRole::SETTING_BALANCING_HC_INTERVAL,
            DBFarmRole::SETTING_BALANCING_HC_UTH,
            DBFarmRole::SETTING_BALANCING_HC_HTH,
            DBFarmRole::SETTING_BALANCING_HC_HASH,
            DBFarmRole::SETTING_BALANCING_AZ_HASH,

            DBFarmRole::SETIING_CLOUDSTACK_STATIC_NAT_MAP,
            DBFarmRole::SETTING_AWS_ELASIC_IPS_MAP,

            DBFarmRole::SETTING_AWS_S3_BUCKET,
            DBFarmRole::SETTING_MYSQL_PMA_USER,
            DBFarmRole::SETTING_MYSQL_PMA_PASS,
            DBFarmRole::SETTING_MYSQL_PMA_REQUEST_ERROR,
            DBFarmRole::SETTING_MYSQL_PMA_REQUEST_TIME,
            DBFarmRole::SETTING_MYSQL_LAST_BCP_TS,
            DBFarmRole::SETTING_MYSQL_LAST_BUNDLE_TS,
            DBFarmRole::SETTING_MYSQL_IS_BCP_RUNNING,
            DBFarmRole::SETTING_MYSQL_IS_BUNDLE_RUNNING,
            DBFarmRole::SETTING_MYSQL_BCP_SERVER_ID,
            DBFarmRole::SETTING_MYSQL_BUNDLE_SERVER_ID,
            DBFarmRole::SETTING_MYSQL_SLAVE_TO_MASTER,
            DBFarmRole::SETTING_MYSQL_ROOT_PASSWORD,
            DBFarmRole::SETTING_MYSQL_REPL_PASSWORD,
            DBFarmRole::SETTING_MYSQL_STAT_PASSWORD,
            DBFarmRole::SETTING_MYSQL_LOG_FILE,
            DBFarmRole::SETTING_MYSQL_LOG_POS,
            DBFarmRole::SETTING_MYSQL_SCALR_SNAPSHOT_ID,
            DBFarmRole::SETTING_MYSQL_SCALR_VOLUME_ID,
            DBFarmRole::SETTING_MYSQL_SNAPSHOT_ID,
            DBFarmRole::SETTING_MYSQL_MASTER_EBS_VOLUME_ID,

            DBFarmRole::SETTING_AWS_ELB_ID,
            DBFarmRole::SETTING_AWS_ELB_ENABLED,

            Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING,
            Scalr_Db_Msr::DATA_BACKUP_LAST_TS,
            Scalr_Db_Msr::DATA_BACKUP_SERVER_ID,
            Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING,
            Scalr_Db_Msr::DATA_BUNDLE_LAST_TS,
            Scalr_Db_Msr::DATA_BUNDLE_SERVER_ID,
            Scalr_Db_Msr::SLAVE_TO_MASTER,
            Scalr_Db_Msr::SNAPSHOT_ID,
            Scalr_Db_Msr::VOLUME_ID,


            Scalr_Role_Behavior_RabbitMQ::ROLE_COOKIE_NAME,
            Scalr_Role_Behavior_RabbitMQ::ROLE_PASSWORD,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_SERVER_ID,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUESTED,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_ERROR_MSG,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_URL,

            Scalr_Role_Behavior_MongoDB::ROLE_PASSWORD,
            Scalr_Role_Behavior_MongoDB::ROLE_KEYFILE,
            Scalr_Role_Behavior_MongoDB::ROLE_CLUSTER_STATUS,
            Scalr_Role_Behavior_MongoDB::ROLE_CLUSTER_IS_REMOVING_SHARD_INDEX,
            Scalr_Role_Behavior_MongoDB::DATA_BUNDLE_IS_RUNNING,
            Scalr_Role_Behavior_MongoDB::DATA_BUNDLE_SERVER_ID,
            Scalr_Role_Behavior_MongoDB::DATA_BUNDLE_LAST_TS,

            Scalr_Role_Behavior_Router::ROLE_VPC_NID,
            Scalr_Role_Behavior_Router::ROLE_VPC_IP,
            Scalr_Role_Behavior_Router::ROLE_VPC_AID,
            Scalr_Role_Behavior_Router::ROLE_VPC_ROUTER_CONFIGURED
        );

        // Set settings
        foreach ($definition->settings as $key => $value) {
            if ($reset && in_array($key, $resetSettings))
                continue;
            $this->SetSetting($key, $value, self::TYPE_CFG);
        }

        //Farm Global Variables
        $variables = new Scalr_Scripting_GlobalVariables($this->GetFarmObject()->ClientID, $this->GetFarmObject()->EnvID, Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);
        $variables->setValues($definition->globalVariables, $this->RoleID, $this->FarmID, $this->ID);

        //Storage
        $this->getStorage()->setConfigs($definition->storage);

        // Scripts
        $scripts = array();
        foreach ($definition->scripts as $script) {
            $scripts[] = array(
                'params' => $script->params,
                'target' => $script->target,
                'order_index' => $script->orderIndex,
                'version' => (int) $script->version,
                'isSync' => (int) $script->isSync,
                'timeout' => $script->timeout,
                'event' => $script->event,
                'script_id' => (int) $script->scriptId,
                'script_path' => $script->scriptPath,
                'script_type' => $script->scriptType,
                'run_as' => $script->runAs,
                'target_roles' => $script->targetRoles,
                'target_farmroles' => $script->targetFarmroles,
                'target_behaviors' => $script->targetBehaviors
            );
        }
        $this->SetScripts($scripts);

        // Scaling times
        $this->DB->Execute("DELETE FROM farm_role_scaling_times WHERE farm_roleid=?",
            array($this->ID)
        );

        foreach ($definition->scalingTimes as $scalingPeriod) {
            $this->DB->Execute("INSERT INTO farm_role_scaling_times SET
                farm_roleid     = ?,
                start_time      = ?,
                end_time        = ?,
                days_of_week    = ?,
                instances_count = ?
            ", array(
                $this->ID,
                $scalingPeriod->startTime,
                $scalingPeriod->endTime,
                $scalingPeriod->daysOfWeek,
                $scalingPeriod->instanceCount
            ));
        }

        // metrics
        $scalingManager = new Scalr_Scaling_Manager($this);
        $metrics = array();
        foreach ($definition->scalingMetrics as $metric)
            $metrics[$metric->metricId] = $metric->settings;

        $scalingManager->setFarmRoleMetrics($metrics);

        return true;
    }

    public function getDefinition()
    {
        $roleDefinition = new stdClass();
        $roleDefinition->roleId = $this->RoleID;
        $roleDefinition->platform = $this->Platform;
        $roleDefinition->cloudLocation = $this->CloudLocation;
        $roleDefinition->alias = $this->Alias;

        // Settings
        $roleDefinition->settings = array();
        foreach ($this->GetAllSettings() as $k=>$v) {
            $roleDefinition->settings[$k] = $v;
        }

        //Farm Global Variables
        $variables = new Scalr_Scripting_GlobalVariables($this->GetFarmObject()->ClientID, $this->GetFarmObject()->EnvID, Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);
        $roleDefinition->globalVariables = $variables->getValues($this->RoleID, $this->FarmID, $this->ID);

        //Storage
        $storage = $this->getStorage();
        $configs = $storage->getConfigs();
        if (!empty($configs)) {
            foreach ($configs as $cfg) {
                $cfg = (array)$cfg;
                unset($cfg['id']);
                unset($cfg['status']);
                $roleDefinition->storage[] = $cfg;
            }
        }

        // Scripts
        $scripts = $this->DB->GetAll("SELECT * FROM farm_role_scripts WHERE farm_roleid=? AND issystem='1'", array($this->ID));
        $roleDefinition->scripts = array();
        foreach ($scripts as $script) {
            $itm = new stdClass();
            $itm->event = $script['event_name'];
            $itm->scriptId = (int) $script['scriptid'];
            $itm->params = unserialize($script['params']);
            $itm->target = $script['target'];
            $itm->version = (int) $script['version'];
            $itm->timeout = $script['timeout'];
            $itm->isSync = $script['issync'];
            $itm->isMenuItem = $script['ismenuitem'];
            $itm->orderIndex = $script['order_index'];
            $itm->scriptPath = $script['script_path'];
            $itm->scriptType = $script['script_type'];
            $itm->runAs = $script['run_as'];

            switch ($script['target']) {
                case $script['target'] == Script::TARGET_ROLES:
                    $varName = 'targetRoles';
                    break;
                case $script['target'] == Script::TARGET_FARMROLES:
                    $varName = 'targetFarmroles';
                    break;
                case $script['target'] == Script::TARGET_BEHAVIORS:
                    $varName = 'targetBehaviors';
                    break;
            }

            $targets = $this->DB->GetAll("SELECT target FROM `farm_role_scripting_targets` WHERE farm_role_script_id = ?", array($script['id']));
            foreach ($targets as $target)
                $itm->{$varName}[] = $target['target'];

            $roleDefinition->scripts[] = $itm;
        }

        // Scaling times
        $scalingTimes = $this->DB->GetAll("SELECT * FROM farm_role_scaling_times WHERE farm_roleid = ?", array($this->ID));
        $roleDefinition->scalingTimes = array();
        foreach ($scalingTimes as $time) {
            $itm = new stdClass();
            $itm->startTime = $time['start_time'];
            $itm->endTime = $time['end_time'];
            $itm->daysOfWeek = $time['days_of_week'];
            $itm->instanceCount = $time['instances_count'];

            $roleDefinition->scalingTimes[] = $itm;
        }

        // Scaling metrics
        $scalingMetrics = $this->DB->GetAll("SELECT * FROM farm_role_scaling_metrics WHERE farm_roleid = ?", array($this->ID));
        $roleDefinition->scalingMetrics = array();
        foreach ($scalingMetrics as $metric) {
            $itm = new stdClass();
            $itm->metricId = $metric['metric_id'];
            $itm->settings = unserialize($metric['settings']);

            $roleDefinition->scalingMetrics[] = $itm;
        }

        return $roleDefinition;
    }

    /**
     *
     * Returns DBFarmRole object by id
     * @param $id
     * @return DBFarmRole
     */
    static public function LoadByID($id)
    {
        $db = \Scalr::getDb();

        $farm_role_info = $db->GetRow("SELECT * FROM farm_roles WHERE id=?", array($id));
        if (!$farm_role_info)
            throw new Exception(sprintf(_("Farm Role ID #%s not found"), $id));

        $DBFarmRole = new DBFarmRole($farm_role_info['id']);
        foreach (self::$FieldPropertyMap as $k=>$v)
            $DBFarmRole->{$v} = $farm_role_info[$k];

        return $DBFarmRole;
    }

    /**
     * Load DBInstance by database id
     * @param $id
     * @return DBFarmRole
     */
    static public function Load($farmid, $roleid, $cloudLocation)
    {
        $db = \Scalr::getDb();

        $farm_role_info = $db->GetRow("SELECT * FROM farm_roles WHERE farmid=? AND (role_id=? OR new_role_id=?) AND cloud_location=? LIMIT 1", array($farmid, $roleid, $roleid, $cloudLocation));
        if (!$farm_role_info)
            throw new Exception(sprintf(_("Role #%s is not assigned to farm #%s"), $roleid, $farmid));

        $DBFarmRole = new DBFarmRole($farm_role_info['id']);
        foreach (self::$FieldPropertyMap as $k=>$v)
            $DBFarmRole->{$v} = $farm_role_info[$k];

        return $DBFarmRole;
    }

    /**
     * Returns DBFarm Object
     * @return DBFarm
     */
    public function GetFarmObject()
    {
        if (!$this->DBFarm)
            $this->DBFarm = DBFarm::LoadByID($this->FarmID);

        return $this->DBFarm;
    }

    /**
     *
     * @return DBRole
     */
    public function GetRoleObject()
    {
        if (!$this->dbRole)
            $this->dbRole = DBRole::loadById($this->RoleID);

        return $this->dbRole;
    }

    /**
     * Returns role prototype
     * @return string
     */
    public function GetRoleID()
    {
        return $this->RoleID;
    }

    /**
     * Delete role from farm
     * @return void
     */
    public function Delete()
    {
        foreach ($this->GetServersByFilter() as $DBServer) {
            /* @var $DBServer \DBServer */
            if ($DBServer->status != \SERVER_STATUS::TERMINATED) {
                try {
                    $DBServer->terminate(DBServer::TERMINATE_REASON_ROLE_REMOVED);
                } catch (Exception $e){}

                $event = new HostDownEvent($DBServer);
                Scalr::FireEvent($DBServer->farmId, $event);
            }
        }

        $this->DB->Execute("DELETE FROM farm_roles WHERE id=?", array($this->ID));

        // Clear farm role options & scripts
        $this->DB->Execute("DELETE FROM farm_role_options WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("DELETE FROM farm_role_service_config_presets WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("DELETE FROM farm_role_scaling_metrics WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("DELETE FROM farm_role_scaling_times WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("DELETE FROM farm_role_service_config_presets WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("DELETE FROM farm_role_settings WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("DELETE FROM farm_role_scripting_targets WHERE `target`=? AND `target_type` = 'farmrole'", array($this->ID));

        $this->DB->Execute("DELETE FROM ec2_ebs WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("DELETE FROM elastic_ips WHERE farm_roleid=?", array($this->ID));

        $this->DB->Execute("DELETE FROM storage_volumes WHERE farm_roleid=?", array($this->ID));

        // Clear apache vhosts and update DNS zones
        $this->DB->Execute("UPDATE apache_vhosts SET farm_roleid='0', farm_id='0' WHERE farm_roleid=?", array($this->ID));
        $this->DB->Execute("UPDATE dns_zones SET farm_roleid='0' WHERE farm_roleid=?", array($this->ID));

        // Clear scheduler tasks
        $this->DB->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type IN(?,?)", array(
            $this->ID,
            Scalr_SchedulerTask::TARGET_ROLE,
            Scalr_SchedulerTask::TARGET_INSTANCE
        ));
    }

    public function GetServiceConfiguration($behavior)
    {
        $preset_id = $this->DB->GetOne("SELECT preset_id FROM farm_role_service_config_presets WHERE farm_roleid=? AND behavior=? LIMIT 1", array(
            $this->ID,
            $behavior
        ));

        if ($preset_id)
            return Scalr_Model::init(Scalr_Model::SERVICE_CONFIGURATION)->loadById($preset_id);
        else
            return null;
    }

    public function GetPendingInstancesCount()
    {
        return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status IN(?,?,?) AND farm_roleid=? LIMIT 1",
            array(\SERVER_STATUS::INIT, \SERVER_STATUS::PENDING, \SERVER_STATUS::PENDING_LAUNCH, $this->ID)
        );
    }

    public function GetSuspendedInstancesCount()
    {
        return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status IN(?,?) AND farm_roleid=? LIMIT 1",
                array(\SERVER_STATUS::SUSPENDED, \SERVER_STATUS::PENDING_SUSPEND, $this->ID)
        );
    }

    public function GetRunningInstancesCount()
    {
        $considerSuspendedServers = $this->GetSetting(Scalr_Role_Behavior::ROLE_BASE_CONSIDER_SUSPENDED);

        if (!$considerSuspendedServers || $considerSuspendedServers == 'running')
            return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status IN (?,?,?) AND farm_roleid=? LIMIT 1",
                    array(\SERVER_STATUS::RUNNING, \SERVER_STATUS::PENDING_SUSPEND, \SERVER_STATUS::SUSPENDED, $this->ID)
            );
        else
            return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status = ? AND farm_roleid=? LIMIT 1",
                array(\SERVER_STATUS::RUNNING, $this->ID)
            );
    }

    public function GetServersByFilter($filter_args = array(), $ufilter_args = array())
    {
        $sql = "SELECT server_id FROM servers WHERE `farm_roleid`=?";
        $args = array($this->ID);
        foreach ((array)$filter_args as $k=>$v)
        {
            if (is_array($v))
            {
                foreach ($v as $vv)
                    array_push($args, $vv);

                $sql .= " AND `{$k}` IN (".implode(",", array_fill(0, count($v), "?")).")";
            }
            else
            {
                $sql .= " AND `{$k}`=?";
                array_push($args, $v);
            }
        }

        foreach ((array)$ufilter_args as $k=>$v)
        {
            if (is_array($v))
            {
                foreach ($v as $vv)
                    array_push($args, $vv);

                $sql .= " AND `{$k}` NOT IN (".implode(",", array_fill(0, count($v), "?")).")";
            }
            else
            {
                $sql .= " AND `{$k}`!=?";
                array_push($args, $v);
            }
        }

        $res = $this->DB->GetAll($sql, $args);

        $retval = array();
        foreach ((array)$res as $i)
        {
            if ($i['server_id'])
                $retval[] = DBServer::LoadByID($i['server_id']);
        }

        return $retval;
    }

    public function GetServiceConfiguration2($behavior)
    {
        $config = array();
        $cfg = $this->DB->Execute("SELECT * FROM farm_role_config_presets WHERE farm_roleid = ? AND behavior = ?", array($this->ID, $behavior));
        while ($c = $cfg->FetchRow())
            $config[$c['cfg_filename']][$c['cfg_key']] = $c['cfg_value'];

        return $config;
    }

    public function SetServiceConfiguration($behavior, $config) {
        $this->DB->BeginTrans();
        try  {
            $this->DB->Execute("DELETE FROM farm_role_config_presets WHERE farm_roleid = ? AND behavior = ?", array($this->ID, $behavior));
            foreach ($config as $configFile => $cfg) {
                foreach ($cfg as $k => $v) {
                    $this->DB->Execute("INSERT INTO farm_role_config_presets SET
                        farm_roleid = ?,
                        behavior = ?,
                        cfg_filename = ?,
                        cfg_key = ?,
                        cfg_value =?
                    ", array($this->ID, $behavior, $configFile, $k, $v));
                }
            }
        } catch (Exception $e) {
            $this->DB->RollbackTrans();
            throw $e;
        }
        $this->DB->CommitTrans();

        $this->SetSetting(self::SETTING_SYSTEM_NEW_PRESETS_USED, 1, self::TYPE_CFG);

        return true;
    }

    /**
     * @deprecated
     */
    public function SetServiceConfigPresets(array $presets)
    {
        foreach ($this->GetRoleObject()->getBehaviors() as $behavior) {
            $farm_preset_id = $this->DB->GetOne("SELECT preset_id FROM farm_role_service_config_presets WHERE farm_roleid=? AND behavior=? LIMIT 1", array(
                $this->ID,
                $behavior
            ));

            $send_message = false;
            $msg = false;

            if ($presets[$behavior]) {
                if (!$farm_preset_id) {
                    $this->DB->Execute("INSERT INTO farm_role_service_config_presets SET
                        preset_id   = ?,
                        farm_roleid = ?,
                        behavior    = ?,
                        restart_service = '1'
                    ", array(
                        $presets[$behavior],
                        $this->ID,
                        $behavior
                    ));

                    $send_message = true;
                }
                elseif ($farm_preset_id != $presets[$behavior]) {
                    $this->DB->Execute("UPDATE farm_role_service_config_presets SET
                        preset_id   = ?
                    WHERE farm_roleid = ? AND behavior = ?
                    ", array(
                        $presets[$behavior],
                        $this->ID,
                        $behavior
                    ));

                    $send_message = true;
                }

                if ($send_message) {
                    $msg = new Scalr_Messaging_Msg_UpdateServiceConfiguration(
                        $behavior,
                        0,
                        1
                    );
                }
            }
            else {
                if ($farm_preset_id) {
                    $this->DB->Execute("DELETE FROM farm_role_service_config_presets WHERE farm_roleid=? AND behavior=?", array($this->ID, $behavior));
                    $msg = new Scalr_Messaging_Msg_UpdateServiceConfiguration(
                        $behavior,
                        1,
                        1
                    );
                }
            }

            if ($msg)
            {
                foreach ($this->GetServersByFilter(array('status' => \SERVER_STATUS::RUNNING)) as $dbServer)
                {
                    if ($dbServer->IsSupported("0.6"))
                        $dbServer->SendMessage($msg);
                }
            }
        }
    }

    public function SetScripts(array $scripts, array $params = array())
    {

        if (count($params) > 0) {
            foreach ($params as $param) {
                if (isset($param['hash']) && count($param['params']) > 0) {
                    $roleId = ($this->NewRoleID) ? $this->NewRoleID : $this->RoleID;
                    $roleParams = $this->DB->GetOne("SELECT params FROM role_scripts WHERE role_id = ? AND `hash` = ? LIMIT 1", array($roleId, $param['hash']));
                    $newParams = serialize($param['params']);
                    if ($newParams != $roleParams) {
                        //UNIQUE KEY `uniq` (`farm_role_id`,`hash`,`farm_role_script_id`),
                        $this->DB->Execute("
                            INSERT INTO farm_role_scripting_params
                            SET farm_role_id = ?,
                                `hash` = ?,
                                farm_role_script_id = ?,
                                role_script_id = ?,
                                params = ?
                            ON DUPLICATE KEY UPDATE
                                role_script_id = ?,
                                params = ?
                        ", array(
                            $this->ID,
                            $param['hash'],
                            0,
                            0, $newParams,
                            0, $newParams,
                        ));
                    }
                }
            }
        }

        $this->DB->Execute("DELETE FROM farm_role_scripts WHERE farm_roleid=?", array($this->ID));

        if (count($scripts) > 0) {
            foreach ($scripts as $script) {

                $timeout = empty($script['timeout']) ? 0 : intval($script['timeout']);

                if (!$timeout)
                    $timeout = \Scalr::config('scalr.script.timeout.sync');

                $event_name = isset($script['event']) ? $script['event'] : null;

                if ($event_name == 'AllEvents')
                    $event_name = '*';

                if ($event_name && (
                    !empty($script['script_id']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_SCALR ||
                    !empty($script['script_path']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_LOCAL ||
                    !empty($script['params']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_CHEF))
                {
                    $this->DB->Execute("INSERT INTO farm_role_scripts SET
                        scriptid    = ?,
                        farmid      = ?,
                        farm_roleid = ?,
                        params      = ?,
                        event_name  = ?,
                        target      = ?,
                        version     = ?,
                        timeout     = ?,
                        issync      = ?,
                        order_index = ?,
                        issystem    = '1',
                        script_path = ?,
                        run_as = ?,
                        script_type = ?
                    ", array(
                        $script['script_id'],
                        $this->FarmID,
                        $this->ID,
                        serialize($script['params']),
                        $event_name,
                        $script['target'],
                        $script['version'],
                        $timeout,
                        $script['isSync'],
                        (int)$script['order_index'],
                        $script['script_path'],
                        $script['run_as'],
                        $script['script_type']
                    ));

                    $farmRoleScriptId = $this->DB->Insert_ID();

                    if ($script['target'] == Script::TARGET_ROLES || $script['target'] == Script::TARGET_BEHAVIORS || $script['target'] == Script::TARGET_FARMROLES) {
                        switch ($script['target']) {
                            case $script['target'] == Script::TARGET_ROLES:
                                $targetType = 'farmrole';
                                $varName = 'target_roles';
                                break;
                            case $script['target'] == Script::TARGET_FARMROLES:
                                $targetType = 'farmrole';
                                $varName = 'target_farmroles';
                                break;
                            case $script['target'] == Script::TARGET_BEHAVIORS:
                                $targetType = 'behavior';
                                $varName = 'target_behaviors';
                                break;
                        }

                        if (is_array($script[$varName])) {
                            foreach ($script[$varName] as $t) {

                                // Workaround to be able to specify self role when it's not yet saved in farm.
                                if ($t == '*self*')
                                    $t = $this->ID;

                                $this->DB->Execute("INSERT INTO farm_role_scripting_targets SET
                                    `farm_role_script_id` = ?,
                                    `target_type` = ?,
                                    `target` =?
                                ", array(
                                    $farmRoleScriptId, $targetType, $t
                                ));
                            }
                        }
                    }
                }
            }
        }
    }

    public function GetParameters()
    {

    }

    public function SetParameters(array $p_params)
    {
        if (count($p_params) > 0) {
            $current_role_options = $this->DB->GetAll("SELECT * FROM farm_role_options WHERE farm_roleid=?", array($this->ID));

            $role_opts = array();
            foreach ($current_role_options as $cro) {
                $role_opts[$cro['hash']] = md5($cro['value']);
            }

            $params = array();
            foreach ($p_params as $name => $value) {
                if (preg_match('/^(.*?)\[(.*?)\]$/', $name, $matches)) {
                    $params[$matches[1]] = array();

                    if ($matches[2] != '' && $value == 1) {
                        $params[$matches[1]][] = $matches[2];
                    }

                    continue;
                } else {
                    $params[$name] = $value;
                }
            }

            $saved_opts = array();
            foreach($params as $name => $value) {
                if ($name) {
                    $val = (is_array($value)) ? implode(',', $value) : $value;
                    $hash = preg_replace("/[^A-Za-z0-9]+/", "_", strtolower($name));

                    if (!$role_opts[$hash]) {
                        $this->DB->Execute("INSERT INTO farm_role_options SET
                            farmid      = ?,
                            farm_roleid = ?,
                            name        = ?,
                            value       = ?,
                            hash        = ?
                            ON DUPLICATE KEY UPDATE name = ?
                        ", array(
                            $this->FarmID,
                            $this->ID,
                            $name,
                            $val,
                            $hash,
                            $name
                        ));
                    } else {
                        if (md5($val) != $role_opts[$hash]) {
                            $this->DB->Execute("UPDATE farm_role_options SET value = ? WHERE
                                farm_roleid = ? AND hash = ?
                            ", array(
                                $val,
                                $this->ID,
                                $hash
                            ));
                        }
                    }
                    $saved_opts[] = $hash;
                }
            }

            foreach ($role_opts as $k => $v) {
                if (!in_array($k, array_values($saved_opts))) {
                    $this->DB->Execute("DELETE FROM farm_role_options WHERE farm_roleid = ? AND hash = ?",
                        array($this->ID, $k)
                    );
                }
            }
        }
    }

    public function isOpenstack()
    {
        return PlatformFactory::isOpenstack($this->Platform);
    }

    public function isCloudstack()
    {
        return PlatformFactory::isCloudstack($this->Platform);
    }

    /**
     * @return Scalr\Farm\Role\FarmRoleStorage
     */
    public function getStorage()
    {
        $storage = new \Scalr\Farm\Role\FarmRoleStorage($this);
        return $storage;
    }

    /**
     * Returns all role settings
     * @return unknown_type
     */
    public function GetAllSettings()
    {
        return $this->GetSettingsByFilter();
    }

    /**
     * Set farm role setting
     *
     * @param   string       $name   The name of the setting
     * @param   string|null  $value  The value of the setting
     * @param   string       $type   optional
     * @return  void
     */
    public function SetSetting($name, $value, $type = null)
    {
        if ($value === "" || $value === null) {
            $this->DB->Execute("DELETE FROM farm_role_settings WHERE name=? AND farm_roleid=?", array(
                $name, $this->ID
            ));
        } else {
            $this->DB->Execute("
                INSERT INTO farm_role_settings
                SET `name`=?,
                    `value`=?,
                    `farm_roleid`=?,
                    `type`=?
                ON DUPLICATE KEY UPDATE
                    `value`=?,
                    `type`=?
            ", array(
                $name,
                $value,
                $this->ID,
                $type,

                $value,
                $type)
            );
        }

        $this->SettingsCache[$name] = $value;

        return true;
    }

    /**
     * Get Role setting by name
     *
     * @param string $name
     * @return mixed
     */
    public function GetSetting($name)
    {
        if (!isset($this->SettingsCache[$name])) {
            $this->SettingsCache[$name] = $this->DB->GetOne("
                SELECT value
                FROM farm_role_settings
                WHERE name=? AND farm_roleid=?
                LIMIT 1
            ",[$name, $this->ID]);
        }

        return $this->SettingsCache[$name];
    }

    public function GetSettingsByFilter($filter = "")
    {
        $settings = $this->DB->GetAll("SELECT * FROM farm_role_settings WHERE farm_roleid=? AND name LIKE '%{$filter}%'", array($this->ID));
        $retval = array();
        foreach ($settings as $setting)
            $retval[$setting['name']] = $setting['value'];

        $this->SettingsCache = array_merge($this->SettingsCache, $retval);

        return $retval;
    }

    public function ClearSettings($filter = "")
    {
        $this->DB->Execute("DELETE FROM farm_role_settings WHERE name LIKE '%{$filter}%' AND farm_roleid=?",
            array($this->ID)
        );

        $this->SettingsCache = array();
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

        try {
            // Perform Update
            $bind[] = $this->ID;
            $this->DB->Execute("UPDATE farm_roles SET $set WHERE id = ?", $bind);
        } catch (Exception $e) {
            throw new Exception("Cannot save farm role. Error: " . $e->getMessage(), $e->getCode());
        }

        if (!empty($this->ID) && !empty($this->FarmID) && \Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                $this->GetFarmObject()->ClientID, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_FARM_ROLE, $this->ID,
                sprintf('%s', $this->Alias)
            );
        }
    }

    public function getReplacementRoles($includeSelf = false)
    {
        $dbRole = $this->GetRoleObject();

        $roles_sql = '
            SELECT r.id
            FROM roles r
            INNER JOIN role_images ri ON r.id = ri.role_id
            INNER JOIN os ON os.id = r.os_id
            WHERE r.generation = \'2\' AND env_id IN(0, ?)
            AND ri.platform = ?' .
            (in_array($this->Platform, array(SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::ECS)) ? '' : 'AND ri.cloud_location = ?') .
            'AND os.family = ?' .
            ($includeSelf ? '' : 'AND r.id != ?') .
            'GROUP BY r.id
        ';
        $args = array($this->GetFarmObject()->EnvID, $this->Platform);

        if (!in_array($this->Platform, array(SERVER_PLATFORMS::GCE, SERVER_PLATFORMS::ECS)))
            $args[] = $this->CloudLocation;

        $args[] = $dbRole->getOs()->family;

        if ($includeSelf)
            $args[] = $dbRole->id;


        $behaviors = $dbRole->getBehaviors();
        sort($behaviors);

        $result = array();
        foreach ($this->DB->GetCol($roles_sql, $args) as $roleId) {
            $role = DBRole::loadById($roleId);
            $behaviors2 = $role->getBehaviors();
            sort($behaviors2);

            if ($behaviors == $behaviors2) {
                $image = $role->__getNewRoleObject()->getImage($this->Platform, $this->CloudLocation)->getImage();
                if ($image) {
                    $result[] = array(
                        'id'            => $role->id,
                        'name'          => $role->name,
                        'osId'          => $role->osId,
                        'shared'        => $role->envId == 0,
                        'behaviors'     => $role->getBehaviors(),
                        'image'         => [
                            'id' => $image->id,
                            'type' => $image->type,
                            'architecture' => $image->architecture
                        ]
                    );
                }
            } else {
                //var_dump($behaviors2);
            }
        }

        return $result;
    }

    public function getChefSettings()
    {
        if ($this->GetRoleObject()->getProperty(Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP)) {
            $result = $this->GetRoleObject()->getProperties('chef.');
            if ($this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ATTRIBUTES)) {
                $result[Scalr_Role_Behavior_Chef::ROLE_CHEF_ATTRIBUTES] = $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ATTRIBUTES);
            }
            if ($this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_LOG_LEVEL)) {
                $result[Scalr_Role_Behavior_Chef::ROLE_CHEF_LOG_LEVEL] = $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_LOG_LEVEL);
            }
            if ($this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT)) {
                $result[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT] = $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT);
            }
        } else {
            $result = $this->GetSettingsByFilter('chef.');
        }

        return $result;
    }

    /**
     * Gets the status of the farm which corresponds to the farm role
     *
     * @return  int|null  Returns the status of the farm which corresponds to the farm role.
     *                    It returns NULL if farm does not exist.
     */
    public function getFarmStatus()
    {
        return $this->DB->GetOne("SELECT f.status FROM `farms` f WHERE f.id = ? LIMIT 1", [
            $this->FarmID,
        ]);
    }
    public function getInstanceType()
    {
        switch ($this->Platform) {
            case SERVER_PLATFORMS::EC2:
                $name = self::SETTING_AWS_INSTANCE_TYPE;
                break;
            case SERVER_PLATFORMS::EUCALYPTUS:
                $name = self::SETTING_EUCA_INSTANCE_TYPE;
                break;
            case SERVER_PLATFORMS::GCE:
                $name = self::SETTING_GCE_MACHINE_TYPE;
                break;
            case SERVER_PLATFORMS::RACKSPACE:
                $name = self::SETTING_RS_FLAVOR_ID;
                break;
            default:
                if (PlatformFactory::isOpenstack($this->Platform)) {
                    $name = self::SETTING_OPENSTACK_FLAVOR_ID;
                } else if (PlatformFactory::isCloudstack($this->Platform)) {
                    $name = self::SETTING_CLOUDSTACK_SERVICE_OFFERING_ID;
                }
        }
        return $this->GetSetting($name);
    }
}
