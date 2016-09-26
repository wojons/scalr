<?php

final class EVENT_TYPE
{
    const HOST_UP 	= "HostUp";
    const HOST_DOWN	= "HostDown";
    const HOST_INIT 	= "HostInit";

    const REBUNDLE_COMPLETE	= "RebundleComplete";
    const REBUNDLE_FAILED	= "RebundleFailed";

    const REBOOT_BEGIN	= "RebootBegin";
    const REBOOT_COMPLETE	= "RebootComplete";
    
    const RESUME_COMPLETE	= "ResumeComplete";

    const FARM_TERMINATED = "FarmTerminated";
    const FARM_LAUNCHED = "FarmLaunched";

    const INSTANCE_IP_ADDRESS_CHANGED = "IPAddressChanged";

    const CHECK_FAILED = "CheckFailed";
    const CHECK_RECOVERED = "CheckRecovered";

    const NEW_MYSQL_MASTER = "NewMysqlMasterUp";
    const MYSQL_BACKUP_COMPLETE = "MysqlBackupComplete";
    const MYSQL_BACKUP_FAIL = "MysqlBackupFail";

    const EBS_VOLUME_MOUNTED = "EBSVolumeMounted";
    const BEFORE_INSTANCE_LAUNCH = "BeforeInstanceLaunch";
    const BEFORE_HOST_TERMINATE = "BeforeHostTerminate";
    const BEFORE_HOST_UP = "BeforeHostUp";

    const EBS_VOLUME_ATTACHED = "EBSVolumeAttached";

    public static function GetEventDescription($event_type)
    {
        $descriptions = array(
            self::HOST_UP 			=> _("Instance started and configured."),
            self::BEFORE_HOST_UP 	=> _("Time for user-defined actions before instance will be added to DNS, LoadBalancer, etc."),
            self::HOST_DOWN 		=> _("Instance terminated."),
            self::REBUNDLE_COMPLETE => _("\"Synchronize to all\" or custom role creation competed successfully."),
            self::REBUNDLE_FAILED 	=> _("\"Synchronize to all\" or custom role creation failed."),
            self::REBOOT_BEGIN 		=> _("Instance being rebooted."),
            self::REBOOT_COMPLETE 	=> _("Instance came up after reboot."),
            self::RESUME_COMPLETE 	=> _("Instance successfully resumed after suspension."),
            self::FARM_LAUNCHED 	=> _("Farm has been launched."),
            self::FARM_TERMINATED 	=> _("Farm has been terminated."),
            self::HOST_INIT			=> _("Instance booted up, Scalr environment not configured and services not initialized yet."),
            self::NEW_MYSQL_MASTER	=> _("One of MySQL instances promoted as master on boot up, or one of mySQL slaves promoted as master."), // due to master failure.",
            self::MYSQL_BACKUP_COMPLETE 		=> _("MySQL backup completed successfully."),
            self::MYSQL_BACKUP_FAIL 			=> _("MySQL backup failed."),
            self::INSTANCE_IP_ADDRESS_CHANGED 	=> _("Public IP address of the instance was changed upon reboot or within Elastic IP assignments."),
            self::EBS_VOLUME_MOUNTED			=> _("Single EBS volume or array of EBS volumes attached and mounted to instance."),
            self::BEFORE_INSTANCE_LAUNCH		=> _("New instance will be launched in a few minutes"),
            self::BEFORE_HOST_TERMINATE			=> _("Instance will be terminated in 3 minutes"),
            self::EBS_VOLUME_ATTACHED			=> _("EBS volume attached to instance."),
            self::CHECK_FAILED => _("Check failed"),
            self::CHECK_RECOVERED => _("Check recovered")
        );

        return $descriptions[$event_type];
    }

    public static function getScriptingEvents()
    {
        return array(
            EVENT_TYPE::BEFORE_INSTANCE_LAUNCH => EVENT_TYPE::GetEventDescription(EVENT_TYPE::BEFORE_INSTANCE_LAUNCH),
            EVENT_TYPE::HOST_INIT => EVENT_TYPE::GetEventDescription(EVENT_TYPE::HOST_INIT),
            EVENT_TYPE::INSTANCE_IP_ADDRESS_CHANGED => EVENT_TYPE::GetEventDescription(EVENT_TYPE::INSTANCE_IP_ADDRESS_CHANGED),
            EVENT_TYPE::EBS_VOLUME_ATTACHED => EVENT_TYPE::GetEventDescription(EVENT_TYPE::EBS_VOLUME_ATTACHED),
            EVENT_TYPE::EBS_VOLUME_MOUNTED => EVENT_TYPE::GetEventDescription(EVENT_TYPE::EBS_VOLUME_MOUNTED),
            EVENT_TYPE::BEFORE_HOST_UP => EVENT_TYPE::GetEventDescription(EVENT_TYPE::BEFORE_HOST_UP),
            EVENT_TYPE::HOST_UP => EVENT_TYPE::GetEventDescription(EVENT_TYPE::HOST_UP),
            EVENT_TYPE::BEFORE_HOST_TERMINATE => EVENT_TYPE::GetEventDescription(EVENT_TYPE::BEFORE_HOST_TERMINATE),
            EVENT_TYPE::HOST_DOWN => EVENT_TYPE::GetEventDescription(EVENT_TYPE::HOST_DOWN),
            EVENT_TYPE::REBOOT_COMPLETE => EVENT_TYPE::GetEventDescription(EVENT_TYPE::REBOOT_COMPLETE),
            EVENT_TYPE::RESUME_COMPLETE => EVENT_TYPE::GetEventDescription(EVENT_TYPE::RESUME_COMPLETE),
            EVENT_TYPE::CHECK_FAILED => EVENT_TYPE::GetEventDescription(EVENT_TYPE::CHECK_FAILED),
            EVENT_TYPE::CHECK_RECOVERED => EVENT_TYPE::GetEventDescription(EVENT_TYPE::CHECK_RECOVERED)
        );
    }

    public static function getScriptingEventsWithScope()
    {
        $events = [];
        foreach (self::getScriptingEvents() as $k => $v) {
            $events[$k] = [
                'name'        => $k,
                'description' => $v,
                'scope'       => 'scalr'
            ];
        }
        return $events;
    }

    /**
     * List Events that restricted for the Chef orchestration actions
     *
     * @return array
     */
    public static function getChefRestrictedEvents()
    {
        return [
            static::BEFORE_INSTANCE_LAUNCH,
            static::HOST_INIT,
            static::HOST_DOWN
        ];
    }

}
