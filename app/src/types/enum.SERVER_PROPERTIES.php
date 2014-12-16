<?php

class SERVER_PROPERTIES
{
    /* SYSTEM PROPERTIES */
    const SYSTEM_IGNORE_INBOUND_MESSAGES = 'system.ignore_inbound_messages';
    const SYSTEM_USER_DATA_METHOD = 'system.user_data_method';

    /* SCALARIZR PROPERTIES */
    const SZR_KEY			= 'scalarizr.key';
    // permanent, one-time
    const SZR_KEY_TYPE		= 'scalarizr.key_type';
    const SZR_MESSAGE_FORMAT = 'scalarizr.message_format';

    const SZR_ONETIME_KEY_EXPIRED = 'scalarizr.onetime_key_expired';

    // 0.5 or 0.2-139
    const SZR_VESION		= 'scalarizr.version';
    const SZR_UPD_CLIENT_VERSION = 'scalarizr.update_client.version';

    // New Importing process
    const SZR_IMPORTING_VERSION = 'scalarizr.import.version';
    const SZR_IMPORTING_STEP    = 'scalarizr.import.step';
    const SZR_IMPORTING_OUT_CONNECTION       = 'scalarizr.import.outbound_connection';
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
    const LAUNCH_ERROR = 'system.launch.error';
    const LAUNCH_REASON = 'system.launch.reason';
    const LAUNCH_REASON_ID = 'system.launch.reason_id';
    const SUB_STATUS 		= 'system.sub-status';

    const SZR_IMPORTING_MYSQL_SERVER_TYPE = 'scalarizr.import.mysql_server_type';

    const SZR_SNMP_PORT = 'scalarizr.snmp_port';
    const SZR_CTRL_PORT = 'scalarizr.ctrl_port';
    const SZR_API_PORT = 'scalarizr.api_port';
    const SZR_UPDC_PORT = 'scalarizr.updc_port';
    const CUSTOM_SSH_PORT = 'scalarizr.ssh_port';

    /* DATABASE PROPERTIES */
    const DB_MYSQL_MASTER	= 'db.mysql.master';
    const DB_MYSQL_REPLICATION_STATUS = 'db.mysql.replication_status';

    /* DNS PROPERTIES */
    const EXCLUDE_FROM_DNS	= 'dns.exclude_instance';

    /* System PROPERTIES */
    const ARCHITECTURE = "system.architecture";
    const REBOOTING = "system.rebooting";
    const RESUMING = "system.resuming";
    const MISSING = "system.missing";
    const CRASHED = "system.crashed";
    const INITIALIZED_TIME = "system.date.initialized";
    const TERMINATION_REQUEST_UNIXTIME = "system.termination.request.unixtime";

    /* Healthcheck PROPERTIES */
    const HEALTHCHECK_FAILED = "system.healthcheck.failed";
    const HEALTHCHECK_TIME = "system.healthcheck.time";

    /* Statistics */
    const STATISTICS_BW_IN 	= "statistics.bw.in";
    const STATISTICS_BW_OUT	= "statistics.bw.out";
    const STATISTICS_LAST_CHECK_TS	= "statistics.lastcheck_ts";

    //!IMPORTANT Farm derived properties
    const FARM_CREATED_BY_ID = 'farm.created_by_id';
    const FARM_CREATED_BY_EMAIL = 'farm.created_by_email';
    //!IMPORTANT These are necessary for cost analytics
    const FARM_PROJECT_ID = 'farm.project_id';
    const FARM_ROLE_ID = 'farm_role.id';
    const ROLE_ID = 'role.id';

    //!IMPORTANT Environment derived properties
    const ENV_CC_ID = 'env.cc_id';

    //!IMPORTANT OS type is used in CA
    const OS_TYPE = 'os_type';

    //!IMPORTANT Audit properties
    const LAUNCHED_BY_ID = 'audit.launched_by_id';
    const LAUNCHED_BY_EMAIL = 'audit.launched_by_email';
    const TERMINATED_BY_ID = 'audit.terminated_by_id';
    const TERMINATED_BY_EMAIL = 'audit.terminated_by_email';

    const SCALR_INBOUND_REQ_RATE = 'scalr.inbound.req.rate';

    const INFO_INSTANCE_TYPE_NAME = 'info.instance_type_name';
}

