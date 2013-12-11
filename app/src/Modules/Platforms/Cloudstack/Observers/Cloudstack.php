<?php
    class Modules_Platforms_Cloudstack_Observers_Cloudstack extends EventObserver
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
            if ($event->KeepElasticIPs == 1) return;
            $DBFarm = DBFarm::LoadByID($this->FarmID);
            $ips = $this->DB->GetAll("SELECT * FROM elastic_ips WHERE farmid=?", array(
                $this->FarmID
            ));
            if (count($ips) > 0) {
                foreach ($ips as $ip) {
                    try {
                        $DBFarmRole = DBFarmRole::LoadByID($ip['farm_roleid']);
                        if (in_array($DBFarmRole->Platform, array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF))) {
                            $cs = $this->getCloudStackClient($DBFarm->GetEnvironmentObject(), $DBFarmRole->CloudLocation, $DBFarmRole->Platform);
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
            if (!in_array($event->DBServer->platform, array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::UCLOUD)))
                return;

            if ($event->DBServer->replaceServerID) return;

            Modules_Platforms_Cloudstack_Helpers_Cloudstack::setStaticNatForServer($event->DBServer);
        }

        public function OnBeforeHostTerminate(BeforeHostTerminateEvent $event)
        {
            if (!in_array($event->DBServer->platform, array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::UCLOUD)))
                return;

            /* MOVED to scalarizr
            try {
                $DBFarm = DBFarm::LoadByID($this->FarmID);

                //disable static nat
                if ($event->DBServer->remoteIp) {
                    $cs = $this->getCloudStackClient(
                        $DBFarm->GetEnvironmentObject(),
                        $event->DBServer->GetCloudLocation(),
                        $event->DBServer->platform
                    );

                    $ipInfo = $cs->listPublicIpAddresses(null, null, null, null, null, $event->DBServer->remoteIp);
                    $info = $ipInfo->publicipaddress[0];

                    if ($info->isstaticnat) {

                        $this->Logger->warn(new FarmLogMessage($this->FarmID,
                            sprintf(_("Calling disableStaticNat for IP: %s"), $event->DBServer->remoteIp)
                        ));

                        $cs->disableStaticNat($info->id);
                    }
                }
            } catch (Exception $e) {
            	Logger::getLogger("Cloudstack::OnBeforeHostTerminate")->fatal($e->getMessage());
            }
            */
        }

        /**
         * Release IP address when instance terminated
         *
         * @param HostDownEvent $event
         */
        public function OnHostDown(HostDownEvent $event)
        {
            if (!in_array($event->DBServer->platform, array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::UCLOUD)))
                return;

            if ($event->DBServer->IsRebooting()) return;

            try {
                $DBFarm = DBFarm::LoadByID($this->FarmID);

                //disable static nat
                if ($event->DBServer->remoteIp) {
                    $cs = $this->getCloudStackClient(
                        $DBFarm->GetEnvironmentObject(),
                        $event->DBServer->GetCloudLocation(),
                        $event->DBServer->platform
                    );

                    $ipInfo = $cs->listPublicIpAddresses(null, null, null, null, null, $event->DBServer->remoteIp);
                    $info = $ipInfo->publicipaddress[0];
                    if ($info->isstaticnat) {
                        if ($info->virtualmachineid == $event->DBServer->GetCloudServerID()) {
                            $this->Logger->warn(new FarmLogMessage($this->FarmID,
                                sprintf(_("Calling disableStaticNat for IP: %s"), $event->DBServer->remoteIp)
                            ));

                            $cs->disableStaticNat($info->id);
                        }
                    }
                }

                if ($event->replacementDBServer) {
                    $ip = $this->DB->GetRow("SELECT * FROM elastic_ips WHERE server_id=? LIMIT 1", array(
                        $event->DBServer->serverId
                    ));
                    if ($ip) {
                        $cs = $this->getCloudStackClient(
                            $DBFarm->GetEnvironmentObject(),
                            $event->replacementDBServer->GetCloudLocation(),
                            $event->replacementDBServer->platform
                        );

                        try {
                            $cs->disableStaticNat($ip['allocation_id']);
                        } catch (Exception $e) {}

                        try {
                            $cs->enableStaticNat(
                                $ip['allocation_id'],
                                $event->replacementDBServer->GetCloudServerID()
                            );

                            $this->DB->Execute("UPDATE elastic_ips SET state='1', server_id=? WHERE ipaddress=?", array(
                                $event->replacementDBServer->serverId,
                                $ip['ipaddress']
                            ));
                            Scalr::FireEvent($this->FarmID, new IPAddressChangedEvent(
                                $event->replacementDBServer, $ip['ipaddress'], $event->replacementDBServer->localIp
                            ));
                        } catch (Exception $e) {
                            if (!stristr($e->getMessage(), "does not belong to you")) {
                                throw new Exception($e->getMessage());
                            }
                        }
                    }
                } else {
                    $this->DB->Execute("UPDATE elastic_ips SET state='0', server_id='' WHERE server_id=?", array(
                        $event->DBServer->serverId
                    ));
                }
            } catch (Exception $e) {
                Logger::getLogger("Cloudstack::OnHostDown")->fatal($e->getMessage());
            }
        }

        /**
         *
         * @return Scalr_Service_Cloud_Cloudstack_Client
         */
        private function getCloudStackClient($environment, $cloudLoction=null, $platformName)
        {
            $platform = PlatformFactory::NewPlatform($platformName);

            return Scalr_Service_Cloud_Cloudstack::newCloudstack(
                $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_URL, $environment),
                $platform->getConfigVariable(Modules_Platforms_Cloudstack::API_KEY, $environment),
                $platform->getConfigVariable(Modules_Platforms_Cloudstack::SECRET_KEY, $environment),
                $platformName
            );
        }

        public function OnHostInit(HostInitEvent $event)
        {
            if (!in_array($event->DBServer->platform, array(SERVER_PLATFORMS::CLOUDSTACK, SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::UCLOUD)))
                return;

            if ($event->DBServer->farmRoleId) {
                $dbFarmRole = $event->DBServer->GetFarmRoleObject();
                $networkType = $dbFarmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_NETWORK_TYPE);
                $networkId = $dbFarmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_NETWORK_ID);
                if ($networkType == 'Direct' || !$networkId)
                    return true;

                $sharedIpId = $dbFarmRole->GetSetting(DBFarmRole::SETTING_CLOUDSTACK_SHARED_IP_ID);

                if ($dbFarmRole->GetSetting(DBFarmRole::SETIING_CLOUDSTACK_USE_STATIC_NAT))
                    return true;
            }

            $platform = PlatformFactory::NewPlatform($event->DBServer->platform);

            try {
                $environment = $event->DBServer->GetEnvironmentObject();
                $cloudLocation = $event->DBServer->GetCloudLocation();

                if (!$sharedIpId)
                    $sharedIpId = $platform->getConfigVariable(Modules_Platforms_Cloudstack::SHARED_IP_ID.".{$cloudLocation}", $environment, false);

                $cs = $this->getCloudStackClient(
                    $environment,
                    $cloudLocation,
                    $event->DBServer->platform
                );

                // Create port forwarding rules for scalarizr
                $port = $platform->getConfigVariable(Modules_Platforms_Cloudstack::SZR_PORT_COUNTER.".{$cloudLocation}.{$sharedIpId}", $environment, false);
                if (!$port) {
                    $port1 = 30000;
                    $port2 = 30001;
                    $port3 = 30002;
                    $port4 = 30003;
                }
                else {
                    $port1 = $port+1;
                    $port2 = $port1+1;
                    $port3 = $port2+1;
                    $port4 = $port3+1;
                }

                $result2 = $cs->createPortForwardingRule($sharedIpId, 8014, "udp", $port1, $event->DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

                $result1 = $cs->createPortForwardingRule($sharedIpId, 8013, "tcp", $port1, $event->DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

                $result3 = $cs->createPortForwardingRule($sharedIpId, 8010, "tcp", $port3, $event->DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));
                $result4 = $cs->createPortForwardingRule($sharedIpId, 8008, "tcp", $port2, $event->DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

                $result5 = $cs->createPortForwardingRule($sharedIpId, 22, "tcp", $port4, $event->DBServer->GetProperty(CLOUDSTACK_SERVER_PROPERTIES::SERVER_ID));

                $event->DBServer->SetProperties(array(
                    SERVER_PROPERTIES::SZR_CTRL_PORT => $port1,
                    SERVER_PROPERTIES::SZR_SNMP_PORT => $port1,

                    SERVER_PROPERTIES::SZR_API_PORT => $port3,
                    SERVER_PROPERTIES::SZR_UPDC_PORT => $port2,
                    SERVER_PROPERTIES::CUSTOM_SSH_PORT => $port4
                ));

                $platform->setConfigVariable(array(Modules_Platforms_Cloudstack::SZR_PORT_COUNTER.".{$cloudLocation}.{$sharedIpId}" => $port4), $environment, false);
            } catch (Exception $e) {
                $this->Logger->fatal(new FarmLogMessage($this->FarmID,
                    sprintf(_("Cloudstack handler failed: %s."), $e->getMessage())
                ));
            }
        }
    }
?>