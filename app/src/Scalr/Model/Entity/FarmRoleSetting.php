<?php

namespace Scalr\Model\Entity;

/**
 * FarmRole Setting entity
 *
 * @author N.V.
 *
 * @Entity
 * @Table(name="farm_role_settings")
 */
class FarmRoleSetting extends Setting
{

    const EXCLUDE_FROM_DNS = 'dns.exclude_role';
    const DNS_INT_RECORD_ALIAS = 'dns.int_record_alias';
    const DNS_EXT_RECORD_ALIAS = 'dns.ext_record_alias';

    const DNS_CREATE_RECORDS = 'dns.create_records';


    const SCALING_ENABLED = 'scaling.enabled';
    const SCALING_MIN_INSTANCES = 'scaling.min_instances';
    const SCALING_MAX_INSTANCES = 'scaling.max_instances';
    const SCALING_POLLING_INTERVAL = 'scaling.polling_interval';
    const SCALING_LAST_POLLING_TIME = 'scaling.last_polling_time';
    const SCALING_KEEP_OLDEST = 'scaling.keep_oldest';
    const SCALING_IGNORE_FULL_HOUR = 'scaling.ignore_full_hour';
    const SCALING_SAFE_SHUTDOWN = 'scaling.safe_shutdown';
    const SCALING_EXCLUDE_DBMSR_MASTER = 'scaling.exclude_dbmsr_master';
    const SCALING_ONE_BY_ONE = 'scaling.one_by_one';
    const SCALING_DOWN_ONLY_IF_ALL_METRICS_TRUE = 'scaling.downscale_only_if_all_metrics_true';

    //advanced timeout limits for scaling
    const SCALING_UPSCALE_TIMEOUT = 'scaling.upscale.timeout';
    const SCALING_DOWNSCALE_TIMEOUT = 'scaling.downscale.timeout';
    const SCALING_UPSCALE_TIMEOUT_ENABLED = 'scaling.upscale.timeout_enabled';
    const SCALING_DOWNSCALE_TIMEOUT_ENABLED = 'scaling.downscale.timeout_enabled';
    const SCALING_UPSCALE_DATETIME = 'scaling.upscale.datetime';
    const SCALING_DOWNSCALE_DATETIME = 'scaling.downscale.datetime';

    //CloudFoundry Settings
    const CF_STORAGE_ENGINE = 'cf.data_storage.engine';
    const CF_STORAGE_EBS_SIZE = 'cf.data_storage.ebs.size';
    const CF_STORAGE_VOLUME_ID = 'cf.data_storage.volume_id';

    const BALANCING_USE_ELB = 'lb.use_elb';
    const BALANCING_HOSTNAME = 'lb.hostname';
    const BALANCING_NAME = 'lb.name';
    const BALANCING_HC_TIMEOUT = 'lb.healthcheck.timeout';
    const BALANCING_HC_TARGET = 'lb.healthcheck.target';
    const BALANCING_HC_INTERVAL = 'lb.healthcheck.interval';
    const BALANCING_HC_UTH = 'lb.healthcheck.unhealthythreshold';
    const BALANCING_HC_HTH = 'lb.healthcheck.healthythreshold';
    const BALANCING_HC_HASH = 'lb.healthcheck.hash';
    const BALANCING_AZ_HASH = 'lb.avail_zones.hash';

    /** RACKSPACE Settings **/
    const RS_FLAVOR_ID = 'rs.flavor-id';

    /** Azure Settings **/
    const SETTING_AZURE_RESOURCE_GROUP      =       'azure.resource-group';
    const SETTING_AZURE_VM_SIZE             =       'azure.vm-size';
    const SETTING_AZURE_STORAGE_ACCOUNT     =       'azure.storage-account';
    const SETTING_AZURE_AVAIL_SET           =       'azure.availability-set';
    const SETTING_AZURE_VIRTUAL_NETWORK     =       'azure.virtual-network';
    const SETTING_AZURE_SUBNET              =       'azure.subnet';
    const SETTING_AZURE_USE_PUBLIC_IPS      =       'azure.use_public_ips';
    const SETTING_AZURE_SECURITY_GROUPS_LIST      =       'azure.security_groups.list';

    /** OPENSTACK Settings **/
    const OPENSTACK_FLAVOR_ID = 'openstack.flavor-id';
    const OPENSTACK_IP_POOL = 'openstack.ip-pool';
    const OPENSTACK_NETWORKS = 'openstack.networks';
    const OPENSTACK_SECURITY_GROUPS_LIST = 'openstack.security_groups.list';
    const OPENSTACK_KEEP_FIP_ON_SUSPEND = 'openstack.keep_fip_on_suspend';
    const OPENSTACK_AVAIL_ZONE = 'openstack.availability_zone';

    /** GCE Settings **/
    const GCE_MACHINE_TYPE = 'gce.machine-type';
    const GCE_NETWORK = 'gce.network';
    const GCE_CLOUD_LOCATION = 'gce.cloud-location';
    const GCE_ON_HOST_MAINTENANCE = 'gce.on-host-maintenance';
    const GCE_USE_STATIC_IPS = 'gce.use_static_ips';
    const GCE_STATIC_IPS_MAP = 'gce.static_ips.map';
    const GCE_REGION = 'gce.region';

    /** Cloudstack Settings **/
    const CLOUDSTACK_SERVICE_OFFERING_ID = 'cloudstack.service_offering_id';
    const CLOUDSTACK_NETWORK_OFFERING_ID = 'cloudstack.network_offering_id';
    const CLOUDSTACK_DISK_OFFERING_ID = 'cloudstack.disk_offering_id';
    const CLOUDSTACK_NETWORK_ID = 'cloudstack.network_id';
    const CLOUDSTACK_NETWORK_TYPE = 'cloudstack.network_type';
    const CLOUDSTACK_SHARED_IP_ADDRESS = 'cloudstack.shared_ip.address';
    const CLOUDSTACK_SHARED_IP_ID = 'cloudstack.shared_ip.id';
    const CLOUDSTACK_SECURITY_GROUPS_LIST = 'cloudstack.security_groups.list';

    const CLOUDSTACK_USE_STATIC_NAT = 'cloudstack.use_static_nat';
    const CLOUDSTACK_STATIC_NAT_MAP = 'cloudstack.static_nat.map';
    const CLOUDSTACK_STATIC_NAT_PRIVATE_MAP = 'cloudstack.static_nat.private_map';

    /** AWS EC2 Settings **/
    const AWS_INSTANCE_TYPE = 'aws.instance_type';
    const AWS_AVAIL_ZONE = 'aws.availability_zone';
    const AWS_USE_ELASIC_IPS = 'aws.use_elastic_ips';
    const AWS_ELASIC_IPS_MAP = 'aws.elastic_ips.map';
    const AWS_PRIVATE_IPS_MAP = 'aws.private_ips.map';

    const AWS_IAM_INSTANCE_PROFILE_ARN = 'aws.iam_instance_profile_arn';

    const AWS_EBS_OPTIMIZED = 'aws.ebs_optimized';

    const AWS_ELB_ENABLED = 'aws.elb.enabled';
    const AWS_ELB_ID = 'aws.elb.id';

    const AWS_USE_EBS = 'aws.use_ebs';
    const AWS_EBS_IOPS = 'aws.ebs_iops';
    const AWS_EBS_TYPE = 'aws.ebs_type';
    const AWS_EBS_SIZE = 'aws.ebs_size';
    const AWS_EBS_SNAPID = 'aws.ebs_snapid';
    const AWS_EBS_MOUNT = 'aws.ebs_mount';
    const AWS_EBS_MOUNTPOINT = 'aws.ebs_mountpoint';
    const AWS_AKI_ID = 'aws.aki_id';
    const AWS_ARI_ID = 'aws.ari_id';
    const AWS_ENABLE_CW_MONITORING = 'aws.enable_cw_monitoring';
    const AWS_SECURITY_GROUPS_LIST = 'aws.security_groups.list';
    const AWS_S3_BUCKET = 'aws.s3_bucket';
    const AWS_CLUSTER_PG = 'aws.cluster_pg';

    const AWS_INSTANCE_NAME_FORMAT = 'aws.instance_name_format';
    const AWS_SHUTDOWN_BEHAVIOR = 'aws.instance_initiated_shutdown_behavior';

    const AWS_SG_LIST = 'aws.additional_security_groups';
    const AWS_TAGS_LIST = 'aws.additional_tags';
    const AWS_SG_LIST_APPEND = 'aws.additional_security_groups.append';

    const AWS_VPC_AVAIL_ZONE = 'aws.vpc_avail_zone';
    const AWS_VPC_INTERNET_ACCESS = 'aws.vpc_internet_access';
    const AWS_VPC_SUBNET_ID = 'aws.vpc_subnet_id';
    const AWS_VPC_ROUTING_TABLE_ID = 'aws.vpc_routing_table_id';
    const AWS_VPC_ASSOCIATE_PUBLIC_IP = 'aws.vpc_associate_public_ip';

    /** MySQL options **/
    const MYSQL_PMA_USER = 'mysql.pma.username';
    const MYSQL_PMA_PASS = 'mysql.pma.password';
    const MYSQL_PMA_REQUEST_TIME = 'mysql.pma.request_time';
    const MYSQL_PMA_REQUEST_ERROR = 'mysql.pma.request_error';

    const MYSQL_BUNDLE_WINDOW_START = 'mysql.bundle_window.start';
    const MYSQL_BUNDLE_WINDOW_END = 'mysql.bundle_window.end';

    const MYSQL_BUNDLE_WINDOW_START_HH = 'mysql.pbw1_hh';
    const MYSQL_BUNDLE_WINDOW_START_MM = 'mysql.pbw1_mm';

    const MYSQL_BUNDLE_WINDOW_END_HH = 'mysql.pbw2_hh';
    const MYSQL_BUNDLE_WINDOW_END_MM = 'mysql.pbw2_mm';

    const MYSQL_EBS_SNAPS_ROTATE = 'mysql.ebs.rotate';
    const MYSQL_EBS_SNAPS_ROTATION_ENABLED = 'mysql.ebs.rotate_snaps';

    const MYSQL_BCP_ENABLED = 'mysql.enable_bcp';
    const MYSQL_BCP_EVERY = 'mysql.bcp_every';
    const MYSQL_BUNDLE_ENABLED = 'mysql.enable_bundle';
    const MYSQL_BUNDLE_EVERY = 'mysql.bundle_every';
    const MYSQL_LAST_BCP_TS = 'mysql.dt_last_bcp';
    const MYSQL_LAST_BUNDLE_TS = 'mysql.dt_last_bundle';
    const MYSQL_IS_BCP_RUNNING = 'mysql.isbcprunning';
    const MYSQL_IS_BUNDLE_RUNNING = 'mysql.isbundlerunning';
    const MYSQL_BCP_SERVER_ID = 'mysql.bcp_server_id';
    const MYSQL_BUNDLE_SERVER_ID = 'mysql.bundle_server_id';

    /* MySQL users credentials */
    const MYSQL_ROOT_PASSWORD = 'mysql.root_password';
    const MYSQL_REPL_PASSWORD = 'mysql.repl_password';
    const MYSQL_STAT_PASSWORD = 'mysql.stat_password';

    const MYSQL_LOG_FILE = 'mysql.log_file';
    const MYSQL_LOG_POS = 'mysql.log_pos';

    /*Scalr_Db_Msr*/
    const MYSQL_DATA_STORAGE_ENGINE = 'mysql.data_storage_engine';
    const MYSQL_SLAVE_TO_MASTER = 'mysql.slave_to_master';
    const MYSQL_SCALR_SNAPSHOT_ID = 'mysql.scalr.snapshot_id';
    const MYSQL_SCALR_VOLUME_ID = 'mysql.scalr.volume_id';

    /**
     * @deprecated
     */
    const MYSQL_SNAPSHOT_ID = 'mysql.snapshot_id';

    /**
     * @deprecated
     */
    const MYSQL_MASTER_EBS_VOLUME_ID = 'mysql.master_ebs_volume_id';

    /**
     * @deprecated
     */
    const MYSQL_EBS_VOLUME_SIZE = 'mysql.ebs_volume_size';

    /**
     * @deprecated
     */
    const MYSQL_EBS_TYPE = 'mysql.ebs.type';

    /**
     * @deprecated
     */
    const MYSQL_EBS_IOPS = 'mysql.ebs.iops';

    /////////////////////////////////////////////////

    const SYSTEM_REBOOT_TIMEOUT = 'system.timeouts.reboot';
    const SYSTEM_LAUNCH_TIMEOUT = 'system.timeouts.launch';
    const SYSTEM_NEW_PRESETS_USED = 'system.new_presets_used';

    const INFO_INSTANCE_TYPE_NAME = 'info.instance_type_name';

    // grow storage
    const STORAGE_GROW_OPERATION_ID = 'storage.grow_operation_id';
    const STORAGE_GROW_SERVER_ID = 'storage.grow_server_id';
    const STORAGE_GROW_LAST_ERROR = 'storage.grow_last_error';

    const TYPE_CFG = 1; //Configuration
    const TYPE_LCL = 2; // Lifecycle

    /**
     * Farm role identifier
     *
     * @Id
     * @Column(name="farm_roleid",type="integer")
     * @var int
     */
    public $farmRoleId;

    /**
     * Configuration or Lifecycle setting type
     *
     * @Column(type="integer",nullable=true)
     * @var int
     */
    public $type;

    /**
     * Sets property type
     *
     * @param   int $type
     *
     * @return FarmRoleSetting
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Fetch all or specified settings for certain farm role(s)
     *
     * @param array|int|string $farmRoleIds          Farm role identifier(s)
     * @param array            $settings    optional Property names to fetch
     * @return FarmRoleSetting[] Array of FarmRoleSetting
     * @throws \InvalidArgumentException
     */
    public static function fetch($farmRoleIds, array $settings = [])
    {
        $criteria = [];
        if (is_array($farmRoleIds)) {
            $criteria[] = ["farmRoleId" => ['$in' => $farmRoleIds]];
        } elseif (is_numeric($farmRoleIds)) {
            $criteria[] = ["farmRoleId" => $farmRoleIds];
        }
        if (empty($criteria)) {
            throw new \InvalidArgumentException("You must specify at least one farm role");
        }
        if (! empty($settings)) {
            $criteria[] = ["name" => ['$in' => $settings]];
        }
        return self::find($criteria);
    }
}
