<?php
namespace Scalr\Observer;

use FarmTerminatedEvent;
use CheckFailedEvent;
use CheckRecoveredEvent;
use MysqlBackupCompleteEvent;
use MysqlBackupFailEvent;
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
use EBSVolumeAttachedEvent;
use BeforeHostUpEvent;

abstract class AbstractEventObserver implements EventObserverInterface
{

    /**
     * Farm ID
     *
     * @var integer
     */
    protected $FarmID;

    /**
     * \Scalr\Logger instance
     *
     * @var Logger
     */
    protected $Logger;

    /**
     * ADODB instance
     *
     * @var \ADODB_mysqli
     */
    protected $DB;

    /**
     * DI Container
     *
     * @var \Scalr\DependencyInjection\Container
     */
    protected $container;

    /**
     * Is scalr agent (scalarizr) require for the observer
     * @var boolean
     */
    public $isScalarizrRequired = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->container = \Scalr::getContainer();
        $this->DB = \Scalr::getDb();
        $this->Logger = $this->container->logger(__CLASS__);
    }

    /**
     * Gets DI Container
     *
     * @return \Scalr\DependencyInjection\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set FARM ID
     *
     * @param integer $farmid
     */
    public function SetFarmID($farmid)
    {
        $this->FarmID = $farmid;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnCheckFailed()
     */
    public function OnCheckFailed(CheckFailedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnHostInitFailed()
     */
    public function OnHostInitFailed(HostInitFailedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnInstanceLaunchFailed()
     */
    public function OnInstanceLaunchFailed(InstanceLaunchFailedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnCheckRecovered()
     */
    public function OnCheckRecovered(CheckRecoveredEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnHostInit()
     */
    public function OnHostInit(HostInitEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnHostUp()
     */
    public function OnHostUp(HostUpEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnHostDown()
     */
    public function OnHostDown(HostDownEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnRebundleComplete()
     */
    public function OnRebundleComplete(RebundleCompleteEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnRebundleFailed()
     */
    public function OnRebundleFailed(RebundleFailedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnRebootBegin()
     */
    public function OnRebootBegin(RebootBeginEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnResumeComplete()
     */
    public function OnResumeComplete(ResumeCompleteEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnRebootComplete()
     */
    public function OnRebootComplete(RebootCompleteEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnFarmLaunched()
     */
    public function OnFarmLaunched(FarmLaunchedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnFarmTerminated()
     */
    public function OnFarmTerminated(FarmTerminatedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnCustomEvent()
     */
    public function OnCustomEvent(CustomEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnNewMysqlMasterUp()
     */
    public function OnNewMysqlMasterUp(NewMysqlMasterUpEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnNewDbMsrMasterUp()
     */
    public function OnNewDbMsrMasterUp(NewDbMsrMasterUpEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnMysqlBackupComplete()
     */
    public function OnMysqlBackupComplete(MysqlBackupCompleteEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnMysqlBackupFail()
     */
    public function OnMysqlBackupFail(MysqlBackupFailEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnIPAddressChanged()
     */
    public function OnIPAddressChanged(IPAddressChangedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnEBSVolumeMounted()
     */
    public function OnEBSVolumeMounted(EBSVolumeMountedEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnBeforeInstanceLaunch()
     */
    public function OnBeforeInstanceLaunch(BeforeInstanceLaunchEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnBeforeHostTerminate()
     */
    public function OnBeforeHostTerminate(BeforeHostTerminateEvent $event)
    {
    }

    /**
     * @param \EBSVolumeAttachedEvent $event
     */
    public function OnEBSVolumeAttached(EBSVolumeAttachedEvent $event)
    {
    }


    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\EventObserverInterface::OnBeforeHostUp()
     */
    public function OnBeforeHostUp(BeforeHostUpEvent $event)
    {
    }
}
