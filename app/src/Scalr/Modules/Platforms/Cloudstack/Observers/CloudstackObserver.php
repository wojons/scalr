<?php
namespace Scalr\Modules\Platforms\Cloudstack\Observers;

use Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper;
use Scalr\Service\CloudStack\DataType\ListIpAddressesData;
use Scalr\Model\Entity;
use Scalr\Observer\AbstractEventObserver;
use FarmTerminatedEvent;
use DBFarm;
use DBFarmRole;
use HostUpEvent;
use BeforeHostTerminateEvent;
use HostDownEvent;
use HostInitEvent;
use SERVER_PROPERTIES;
use Exception;

class CloudstackObserver extends AbstractEventObserver
{
    public $ObserverName = 'Cloudstack';

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Release used elastic IPs if farm terminated
     *
     * @param FarmTerminatedEvent $event
     */
    public function OnFarmTerminated(FarmTerminatedEvent $event)
    {
        $this->Logger->info(sprintf(_("Keep elastic IPs: %s"), $event->KeepElasticIPs));
        if ($event->KeepElasticIPs == 1) {
            return;
        }
        $DBFarm = DBFarm::LoadByID($this->FarmID);
        $ips = $this->DB->GetAll("SELECT * FROM elastic_ips WHERE farmid=?", array(
            $this->FarmID
        ));
        if (count($ips) > 0) {
            foreach ($ips as $ip) {
                try {
                    $DBFarmRole = DBFarmRole::LoadByID($ip['farm_roleid']);
                    if (in_array($DBFarmRole->Platform, array(\SERVER_PLATFORMS::CLOUDSTACK, \SERVER_PLATFORMS::IDCF))) {
                        $cs = $DBFarm->GetEnvironmentObject()->cloudstack($DBFarmRole->Platform);
                        $cs->disassociateIpAddress($ip['allocation_id']);
                        $this->DB->Execute("DELETE FROM elastic_ips WHERE ipaddress=?", array(
                            $ip['ipaddress']
                        ));
                    }
                } catch (Exception $e) {
                    if (!stristr($e->getMessage(), "does not belong to you")) {
                        $this->Logger->error(sprintf(
                            _("Cannot release elastic IP %s from farm %s: %s"),
                            $ip['ipaddress'], $DBFarm->Name, $e->getMessage()
                        ));
                        continue;
                    }
                }
            }
        }
    }

    /**
     * Allocate and Assign Elastic IP to instance if role use it.
     *
     * @param HostUpEvent $event
     */
    public function OnHostUp(HostUpEvent $event)
    {
        if (!in_array($event->DBServer->platform, array(\SERVER_PLATFORMS::CLOUDSTACK, \SERVER_PLATFORMS::IDCF)))
            return;

        //CloudstackHelper::setStaticNatForServer($event->DBServer);
    }

    public function OnBeforeHostTerminate(BeforeHostTerminateEvent $event)
    {
        if (!in_array($event->DBServer->platform, array(\SERVER_PLATFORMS::CLOUDSTACK, \SERVER_PLATFORMS::IDCF)))
            return;

    }

    /**
     * Release IP address when instance terminated
     *
     * @param HostDownEvent $event
     */
    public function OnHostDown(HostDownEvent $event)
    {
        if (!in_array($event->DBServer->platform, array(\SERVER_PLATFORMS::CLOUDSTACK, \SERVER_PLATFORMS::IDCF))) {
            return;
        }
        if ($event->DBServer->IsRebooting()) {
            return;
        }
        try {
            $DBFarm = DBFarm::LoadByID($this->FarmID);

            //disable static nat
            if ($event->DBServer->remoteIp) {
                $cs = $DBFarm->GetEnvironmentObject()->cloudstack($event->DBServer->platform);

                $requestObject = new ListIpAddressesData();
                $requestObject->ipaddress = $event->DBServer->remoteIp;
                $ipInfo = $cs->listPublicIpAddresses($requestObject);
                $info = !empty($ipInfo[0]) ? $ipInfo[0] : null;
                if (!empty($info->isstaticnat)) {
                    if ($info->virtualmachineid == $event->DBServer->GetCloudServerID()) {
                        $this->Logger->warn(new \FarmLogMessage($this->FarmID,
                            sprintf(_("Calling disableStaticNat for IP: %s"), $event->DBServer->remoteIp)
                        ));

                        $cs->firewall->disableStaticNat($info->id);
                    }
                }
            }

            $this->DB->Execute("UPDATE elastic_ips SET state='0', server_id='' WHERE server_id=?", array(
                $event->DBServer->serverId
            ));
        } catch (Exception $e) {
            \Scalr::getContainer()->logger("Cloudstack::OnHostDown")->fatal($e->getMessage());
        }
    }

    public function OnHostInit(HostInitEvent $event)
    {
        if (!in_array($event->DBServer->platform, array(\SERVER_PLATFORMS::CLOUDSTACK, \SERVER_PLATFORMS::IDCF)))
            return;

        if ($event->DBServer->farmRoleId) {
            $dbFarmRole = $event->DBServer->GetFarmRoleObject();

            if ($dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_USE_STATIC_NAT)) {
                CloudstackHelper::setStaticNatForServer($event->DBServer);
                return true;
            }

            $networkType = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_TYPE);
            $networkId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_ID);
            if ($networkType == 'Direct' || !$networkId)
                return true;

            if ($networkId == 'SCALR_MANUAL') {
                $map = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_STATIC_NAT_PRIVATE_MAP);
                $map = explode(";", $map);
                foreach ($map as $ipMapping) {
                    $ipInfo = explode("=", $ipMapping);
                    if ($ipInfo[0] == $event->DBServer->localIp) {
                        $event->DBServer->remoteIp = $ipInfo[1];
                        $event->DBServer->Save();
                    }
                }
            }

            $sharedIpId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_SHARED_IP_ID);
        }

        try {
            $environment = $event->DBServer->GetEnvironmentObject();
            $cloudLocation = $event->DBServer->GetCloudLocation();

            if (empty($sharedIpId)) {
                $sharedIpId = $environment->cloudCredentials($event->DBServer->platform)->properties[Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP_ID . ".{$cloudLocation}"];
            }

            if (!$sharedIpId) {
                return;
            }

            $cs = $environment->cloudstack($event->DBServer->platform);

            // Create port forwarding rules for scalarizr
            $port = $environment->cloudCredentials($event->DBServer->platform)->properties[Entity\CloudCredentialsProperty::CLOUDSTACK_SZR_PORT_COUNTER . ".{$cloudLocation}.{$sharedIpId}"];
            if (!$port) {
                $port1 = 30000;
                $port2 = 30001;
                $port3 = 30002;
                $port4 = 30003;
            } else {
                $port1 = $port+1;
                $port2 = $port1+1;
                $port3 = $port2+1;
                $port4 = $port3+1;
            }

            $cs->firewall->createPortForwardingRule(array(
                'ipaddressid' => $sharedIpId,
                'privateport' => 8014,
                'protocol'    => "udp",
                'publicport'  => $port1,
                'virtualmachineid'  => $event->DBServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)
            ));

            $cs->firewall->createPortForwardingRule(array(
                'ipaddressid' => $sharedIpId,
                'privateport' => 8013,
                'protocol'    => "tcp",
                'publicport'  => $port1,
                'virtualmachineid'  => $event->DBServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)
            ));

            $cs->firewall->createPortForwardingRule(array(
                'ipaddressid' => $sharedIpId,
                'privateport' => 8010,
                'protocol'    => "tcp",
                'publicport'  => $port3,
                'virtualmachineid'  => $event->DBServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)
            ));

            $cs->firewall->createPortForwardingRule(array(
                'ipaddressid' => $sharedIpId,
                'privateport' => 8008,
                'protocol'    => "tcp",
                'publicport'  => $port2,
                'virtualmachineid'  => $event->DBServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)
            ));

            $cs->firewall->createPortForwardingRule(array(
                'ipaddressid' => $sharedIpId,
                'privateport' => 22,
                'protocol'    => "tcp",
                'publicport'  => $port4,
                'virtualmachineid'  => $event->DBServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID)
            ));

            $event->DBServer->SetProperties(array(
                SERVER_PROPERTIES::SZR_CTRL_PORT => $port1,
                SERVER_PROPERTIES::SZR_SNMP_PORT => $port1,

                SERVER_PROPERTIES::SZR_API_PORT => $port3,
                SERVER_PROPERTIES::SZR_UPDC_PORT => $port2,
                SERVER_PROPERTIES::CUSTOM_SSH_PORT => $port4
            ));

            $environment->cloudCredentials($event->DBServer->platform)->properties->saveSettings([
                Entity\CloudCredentialsProperty::CLOUDSTACK_SZR_PORT_COUNTER . ".{$cloudLocation}.{$sharedIpId}" => $port4
            ]);
        } catch (Exception $e) {
            $this->Logger->fatal(new \FarmLogMessage($this->FarmID,
                sprintf(_("Cloudstack handler failed: %s."), $e->getMessage())
            ));
        }
    }
}
