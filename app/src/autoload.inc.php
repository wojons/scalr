<?php

function __autoload($class_name)
{
    $paths = array(
        'Scalr' => SRCPATH . '/Scalr.php',
        /****************************** Basic Objects ***********************/
        'Client' => SRCPATH . '/class.Client.php',
        'DBFarm' => SRCPATH . '/class.DBFarm.php',
        'DBEBSVolume' => SRCPATH . '/class.DBEBSVolume.php',
        'DBEBSArray' => SRCPATH . '/class.DBEBSArray.php',
        'XMLMessageSerializer' => SRCPATH . '/class.XMLMessageSerializer.php',
        'IMessageSerializer' => SRCPATH . '/interface.IMessageSerializer.php',
        'DBFarmRole' => SRCPATH . '/class.DBFarmRole.php',
        'DBServer' => SRCPATH . '/class.DBServer.php',
        'ServerCreateInfo' => SRCPATH . '/class.ServerCreateInfo.php',
        'ServerSnapshotCreateInfo' => SRCPATH . '/class.ServerSnapshotCreateInfo.php',
        'BundleTask' => SRCPATH . '/class.BundleTask.php',
        'DBRole' => SRCPATH . '/class.DBRole.php',
        'DBDNSZone' => SRCPATH . '/class.DBDNSZone.php',
        /********************** Service Configuration Modules ********************/
        'ServiceConfigurationFactory' => SRCPATH . '/Modules/class.ServiceConfigurationFactory.php',
        /***************************** API ***********************************/
        'ScalrAPICoreFactory' => SRCPATH . '/api/class.ScalrAPICoreFactory.php',
        'ScalrAPICore' => SRCPATH . '/api/class.ScalrAPICore.php',
        'ScalrAPI_2_0_0' => SRCPATH . '/api/class.ScalrAPI_2_0_0.php',
        'ScalrAPI_2_1_0' => SRCPATH . '/api/class.ScalrAPI_2_1_0.php',
        'ScalrAPI_2_2_0' => SRCPATH . '/api/class.ScalrAPI_2_2_0.php',
        'ScalrAPI_2_3_0' => SRCPATH . '/api/class.ScalrAPI_2_3_0.php',
        /****************************** Messaging  ***************************/
        'ScalrMessagingService' => SRCPATH . '/class.ScalrMessagingService.php',
        /******************* Environment objects ****************************/
        'ScalrEnvironmentFactory' => SRCPATH . '/class.ScalrEnvironmentFactory.php',
        'ScalrEnvironment' => SRCPATH . '/class.ScalrEnvironment.php',
        'ScalrRESTService' => SRCPATH . '/class.ScalrRESTService.php',
        'ScalarizrCallbackService' => SRCPATH . '/class.ScalarizrCallbackService.php',
        /****************************** Events ******************************/
        'Event' => SRCPATH . '/events/abstract.Event.php',
        'CustomEvent' => SRCPATH . '/events/class.CustomEvent.php',
        'CheckFailedEvent' => SRCPATH . '/events/class.CheckFailedEvent.php',
        'CheckRecoveredEvent' => SRCPATH . '/events/class.CheckRecoveredEvent.php',
        'FarmLaunchedEvent' => SRCPATH . '/events/class.FarmLaunchedEvent.php',
        'FarmTerminatedEvent' => SRCPATH . '/events/class.FarmTerminatedEvent.php',
        'HostCrashEvent' => SRCPATH . '/events/class.HostCrashEvent.php',
        'HostDownEvent' => SRCPATH . '/events/class.HostDownEvent.php',
        'HostInitEvent' => SRCPATH . '/events/class.HostInitEvent.php',
        'HostUpEvent' => SRCPATH . '/events/class.HostUpEvent.php',
        'HostInitFailedEvent' => SRCPATH . '/events/class.HostInitFailedEvent.php',
        'IPAddressChangedEvent' => SRCPATH . '/events/class.IPAddressChangedEvent.php',
        'MysqlBackupCompleteEvent' => SRCPATH . '/events/class.MysqlBackupCompleteEvent.php',
        'MysqlBackupFailEvent' => SRCPATH . '/events/class.MysqlBackupFailEvent.php',
        'MySQLReplicationFailEvent' => SRCPATH . '/events/class.MySQLReplicationFailEvent.php',
        'MySQLReplicationRecoveredEvent' => SRCPATH . '/events/class.MySQLReplicationRecoveredEvent.php',
        'NewMysqlMasterUpEvent' => SRCPATH . '/events/class.NewMysqlMasterUpEvent.php',
        'NewDbMsrMasterUpEvent' => SRCPATH . '/events/class.NewDbMsrMasterUpEvent.php',
        'RebootBeginEvent' => SRCPATH . '/events/class.RebootBeginEvent.php',
        'RebootCompleteEvent' => SRCPATH . '/events/class.RebootCompleteEvent.php',
        'RebundleCompleteEvent' => SRCPATH . '/events/class.RebundleCompleteEvent.php',
        'RebundleFailedEvent' => SRCPATH . '/events/class.RebundleFailedEvent.php',
        'EBSVolumeMountedEvent' => SRCPATH . '/events/class.EBSVolumeMountedEvent.php',
        'BeforeInstanceLaunchEvent' => SRCPATH . '/events/class.BeforeInstanceLaunchEvent.php',
        'InstanceLaunchFailedEvent' => SRCPATH . '/events/class.InstanceLaunchFailedEvent.php',
        'BeforeHostTerminateEvent' => SRCPATH . '/events/class.BeforeHostTerminateEvent.php',
        'DNSZoneUpdatedEvent' => SRCPATH . '/events/class.DNSZoneUpdatedEvent.php',
        'EBSVolumeAttachedEvent' => SRCPATH . '/events/class.EBSVolumeAttachedEvent.php',
        'BeforeHostUpEvent' => SRCPATH . '/events/class.BeforeHostUpEvent.php',
        'ServiceConfigurationPresetChangedEvent' => SRCPATH . '/events/class.ServiceConfigurationPresetChangedEvent.php',
        /****************************** ENUMS ******************************/
        'APPCONTEXT' => SRCPATH . "/types/enum.APPCONTEXT.php",
        'FORM_FIELD_TYPE' => SRCPATH . "/types/enum.FORM_FIELD_TYPE.php",
        'SUBSCRIPTION_STATUS' => SRCPATH . "/types/enum.SUBSCRIPTION_STATUS.php",
        'EVENT_TYPE' => SRCPATH . "/types/enum.EVENT_TYPE.php",
        'RRD_STORAGE_TYPE' => SRCPATH . "/types/enum.RRD_STORAGE_TYPE.php",
        'GRAPH_TYPE' => SRCPATH . "/types/enum.GRAPH_TYPE.php",
        'MYSQL_BACKUP_TYPE' => SRCPATH . "/types/enum.MYSQL_BACKUP_TYPE.php",
        'FARM_STATUS' => SRCPATH . "/types/enum.FARM_STATUS.php",
        'INSTANCE_COST' => SRCPATH . "/types/enum.INSTANCE_COST.php",
        'QUEUE_NAME' => SRCPATH . "/types/enum.QUEUE_NAME.php",
        'ROLE_BEHAVIORS' => SRCPATH . "/types/enum.ROLE_BEHAVIORS.php",
        'ROLE_TYPE' => SRCPATH . "/types/enum.ROLE_TYPE.php",
        'ROLE_TAGS' => SRCPATH . "/types/enum.ROLE_TAGS.php",
        'AWS_SCALR_EIP_STATE' => SRCPATH . "/types/enum.AWS_SCALR_EIP_STATE.php",
        'AMAZON_EBS_STATE' => SRCPATH . "/types/enum.AMAZON_EBS_STATE.php",
        'SCRIPT_ORIGIN_TYPE' => SRCPATH . "/types/enum.SCRIPT_ORIGIN_TYPE.php",
        'COMMENTS_OBJECT_TYPE' => SRCPATH . "/types/enum.COMMENTS_OBJECT_TYPE.php",
        'EBS_ARRAY_STATUS' => SRCPATH . "/types/enum.EBS_ARRAY_STATUS.php",
        'EBS_ARRAY_SNAP_STATUS' => SRCPATH . "/types/enum.EBS_ARRAY_SNAP_STATUS.php",
        'MYSQL_STORAGE_ENGINE' => SRCPATH . "/types/enum.MYSQL_STORAGE_ENGINE.php",
        'CLIENT_SETTINGS' => SRCPATH . "/types/enum.CLIENT_SETTINGS.php",
        'BASIC_MESSAGE_NAMES' => SRCPATH . "/types/enum.BASIC_MESSAGE_NAMES.php",
        'AUTOSNAPSHOT_TYPE' => SRCPATH . "/types/enum.AUTOSNAPSHOT_TYPE.php",
        'SCHEDULE_TASK_TYPE' => SRCPATH . "/types/enum.SCHEDULE_TASK_TYPE.php",
        'TASK_STATUS' => SRCPATH . "/types/enum.TASK_STATUS.php",
        'LOG_CATEGORY' => SRCPATH . "/types/enum.LOG_CATEGORY.php",
        'MONITORING_TYPE' => SRCPATH . "/types/enum.MONITORING_TYPE.php",
        'DNS_ZONE_STATUS' => SRCPATH . "/types/enum.DNS_ZONE_STATUS.php",
        'SERVER_TYPE' => SRCPATH . "/types/enum.SERVER_TYPE.php",
        'SERVER_STATUS' => SRCPATH . "/types/enum.SERVER_STATUS.php",
        'SERVER_PROPERTIES' => SRCPATH . "/types/enum.SERVER_PROPERTIES.php",
        'SERVER_PLATFORMS' => SRCPATH . "/types/enum.SERVER_PLATFORMS.php",
        'EC2_SERVER_PROPERTIES' => SRCPATH . "/types/enum.EC2_SERVER_PROPERTIES.php",
        'RDS_SERVER_PROPERTIES' => SRCPATH . "/types/enum.RDS_SERVER_PROPERTIES.php",
        'VPS_SERVER_PROPERTIES' => SRCPATH . "/types/enum.VPS_SERVER_PROPERTIES.php",
        'GCE_SERVER_PROPERTIES' => SRCPATH . "/types/enum.GCE_SERVER_PROPERTIES.php",
        'EUCA_SERVER_PROPERTIES' => SRCPATH . "/types/enum.EUCA_SERVER_PROPERTIES.php",
        'RACKSPACE_SERVER_PROPERTIES' => SRCPATH . "/types/enum.RACKSPACE_SERVER_PROPERTIES.php",
        'OPENSTACK_SERVER_PROPERTIES' => SRCPATH . "/types/enum.OPENSTACK_SERVER_PROPERTIES.php",
        'CLOUDSTACK_SERVER_PROPERTIES' => SRCPATH . "/types/enum.CLOUDSTACK_SERVER_PROPERTIES.php",
        'SZR_KEY_TYPE' => SRCPATH . "/types/enum.SZR_KEY_TYPE.php",
        'SERVER_REPLACEMENT_TYPE' => SRCPATH . "/types/enum.SERVER_REPLACEMENT_TYPE.php",
        'SERVER_SNAPSHOT_CREATION_TYPE' => SRCPATH . "/types/enum.SERVER_SNAPSHOT_CREATION_TYPE.php",
        'SERVER_SNAPSHOT_CREATION_STATUS' => SRCPATH . "/types/enum.SERVER_SNAPSHOT_CREATION_STATUS.php",
        'MESSAGE_STATUS' => SRCPATH . "/types/enum.MESSAGE_STATUS.php",
        'EC2_EBS_ATTACH_STATUS' => SRCPATH . "/types/enum.EC2_EBS_ATTACH_STATUS.php",
        'EC2_EBS_MOUNT_STATUS' => SRCPATH . "/types/enum.EC2_EBS_MOUNT_STATUS.php",
        /****************************** Observers ***************************/
        'IEventObserver' => APPPATH . '/observers/interface.IEventObserver.php',
        'EventObserver' => APPPATH . '/observers/abstract.EventObserver.php',
        'DNSEventObserver' => APPPATH . '/observers/class.DNSEventObserver.php',
        'DBEventObserver' => APPPATH . '/observers/class.DBEventObserver.php',
        'MessagingEventObserver' => APPPATH . '/observers/class.MessagingEventObserver.php',
        'ScalarizrEventObserver' => APPPATH . '/observers/class.ScalarizrEventObserver.php',
        'BehaviorEventObserver' => APPPATH . '/observers/class.BehaviorEventObserver.php',
        /******************** Logger (mostly deprecated classes) *******************************/
        'FarmLogMessage' => SRCPATH . '/class.FarmLogMessage.php',
        'ScriptingLogMessage' => SRCPATH . '/class.ScriptingLogMessage.php',
        'LoggerAppenderScalr' => SRCPATH . '/class.LoggerAppenderScalr.php',
        'LoggerFilterCategoryMatch' => SRCPATH . '/class.LoggerFilterCategoryMatch.php',
    );

    if (key_exists($class_name, $paths)) {
        require $paths[$class_name];
        return;
    }

    // Load packaged classes
    if (strpos($class_name, "_") !== false) {
        $filename =  str_replace("_", DIRECTORY_SEPARATOR, $class_name) . ".php";
        require $filename;
    } else if (strpos($class_name, 'Scalr\\') === 0) {
        //Loads Scalr namespaces
        $filename = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $class_name) . ".php";
        require $filename;
    }
}
