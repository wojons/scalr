<?php
namespace Scalr\Observer;

use FarmTerminatedEvent;
use CheckFailedEvent;
use CheckRecoveredEvent;
use MysqlBackupCompleteEvent;
use MysqlBackupFailEvent;
use MySQLReplicationFailEvent;
use MySQLReplicationRecoveredEvent;
use NewMysqlMasterUpEvent;
use HostInitFailedEvent;
use InstanceLaunchFailedEvent;
use HostInitEvent;
use HostUpEvent;
use HostDownEvent;
use RebundleCompleteEvent;
use RebundleFailedEvent;
use RebootBeginEvent;
use ResumeCompleteEvent;
use RebootCompleteEvent;
use FarmLaunchedEvent;
use CustomEvent;
use NewDbMsrMasterUpEvent;
use IPAddressChangedEvent;
use EBSVolumeMountedEvent;
use BeforeInstanceLaunchEvent;
use BeforeHostTerminateEvent;
use DNSZoneUpdatedEvent;
use RoleOptionChangedEvent;
use EBSVolumeAttachedEvent;
use ServiceConfigurationPresetChangedEvent;
use BeforeHostUpEvent;

interface EventObserverInterface
{

    /**
     *
     * @param CheckFailedEvent $event
     */
    public function OnCheckFailed(CheckFailedEvent $event);

    /**
     *
     * @param CheckRecoveredEvent $event
     */
    public function OnCheckRecovered(CheckRecoveredEvent $event);

    /**
     * Triggers when 'hostInit' event is received from the instance
     *
     * @param HostInitEvent $event
     */
    public function OnHostInit(HostInitEvent $event);

    /**
     *
     * @param HostInitFailedEvent $event
     */
    public function OnHostInitFailed(HostInitFailedEvent $event);

    /**
     *
     * @param InstanceLaunchFailedEvent $event
     */
    public function OnInstanceLaunchFailed(InstanceLaunchFailedEvent $event);

    /**
     *
     * @param HostUpEvent $event
     */
    public function OnHostUp(HostUpEvent $event);

    /**
     * Triggers when the instance is going to be down
     *
     * @param HostDownEvent $event
     */
    public function OnHostDown(HostDownEvent $event);

    /**
     * Triggers when 'newAMI' event is received from instance
     *
     * @param RebundleCompleteEvent $event
     */
    public function OnRebundleComplete(RebundleCompleteEvent $event);

    /**
     * Triggers when scalr receives notify about rebundle failure from instance
     *
     * @param RebundleFailedEvent $event
     */
    public function OnRebundleFailed(RebundleFailedEvent $event);

    /**
     * Triggers when instance receives reboot command
     *
     * @param RebootBeginEvent $event
     */
    public function OnRebootBegin(RebootBeginEvent $event);

    /**
     *
     * @param RebootCompleteEvent $event
     */
    public function OnRebootComplete(RebootCompleteEvent $event);

    /**
     * Triggers when the instance was successfully resumed and then goes online
     *
     * @param ResumeCompleteEvent $event
     */
    public function OnResumeComplete(ResumeCompleteEvent $event);

    /**
     * Triggers when the Farm is launched
     *
     * @param FarmLaunchedEvent $event
     */
    public function OnFarmLaunched(FarmLaunchedEvent $event);

    /**
     * Triggers when the Farm is terminated
     *
     * @param FarmTerminatedEvent $event
     */
    public function OnFarmTerminated(FarmTerminatedEvent $event);

    /**
     *
     * @param CustomEvent $event
     */
    public function OnCustomEvent(CustomEvent $event);

    /**
     * Triggers when 'newMysqlMaster' event recieved from instance
     *
     * @param NewMysqlMasterUpEvent $event
     * @deprecated
     */
    public function OnNewMysqlMasterUp(NewMysqlMasterUpEvent $event);

    /**
     *
     * @param NewDbMsrMasterUpEvent $event
     */
    public function OnNewDbMsrMasterUp(NewDbMsrMasterUpEvent $event);

    /**
     *
     * @param MysqlBackupCompleteEvent $event
     */
    public function OnMysqlBackupComplete(MysqlBackupCompleteEvent $event);

    /**
     * Triggers when 'mysqlBckFail' event recieved from instance
     *
     * @param MysqlBackupFailEvent $event
     */
    public function OnMysqlBackupFail(MysqlBackupFailEvent $event);

    /**
     *
     * @param IPAddressChangedEvent $event
     */
    public function OnIPAddressChanged(IPAddressChangedEvent $event);

    /**
     * Triggers when replication was broken on slave
     *
     * @param MySQLReplicationFailEvent $event
     */
    public function OnMySQLReplicationFail(MySQLReplicationFailEvent $event);

    /**
     * Triggers when replication was recovered on slave
     *
     * @param MySQLReplicationRecoveredEvent $event
     */
    public function OnMySQLReplicationRecovered(MySQLReplicationRecoveredEvent $event);

    /**
     *
     * @param DNSZoneUpdatedEvent $event
     */
    public function OnDNSZoneUpdated(DNSZoneUpdatedEvent $event);

    /**
     *
     * @param BeforeHostTerminateEvent $event
     */
    public function OnBeforeHostTerminate(BeforeHostTerminateEvent $event);

    /**
     *
     * @param BeforeInstanceLaunchEvent $event
     */
    public function OnBeforeInstanceLaunch(BeforeInstanceLaunchEvent $event);

    /**
     *
     * @param EBSVolumeMountedEvent $event
     */
    public function OnEBSVolumeMounted(EBSVolumeMountedEvent $event);

    /**
     *
     * @param BeforeHostUpEvent $event
     */
    public function OnBeforeHostUp(BeforeHostUpEvent $event);
}
