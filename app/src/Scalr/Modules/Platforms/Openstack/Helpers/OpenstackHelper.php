<?php

namespace Scalr\Modules\Platforms\Openstack\Helpers;

use Scalr\Model\Entity;
use Scalr\Modules\PlatformFactory;
use FarmLogMessage;
use Exception;

class OpenstackHelper
{
    /**
     * Sets floating IP for openstack server
     *
     * @param   \DBServer    $DBServer  The server object
     */
    public static function setServerFloatingIp(\DBServer $DBServer)
    {
        $ipPool = $DBServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::OPENSTACK_IP_POOL);

        if (!$DBServer->remoteIp && (in_array($DBServer->status, array(\SERVER_STATUS::PENDING, \SERVER_STATUS::INIT, \SERVER_STATUS::SUSPENDED))) && $ipPool) {
            //$ipAddress = \Scalr\Modules\Platforms\Openstack\Helpers\OpenstackHelper::setFloatingIpForServer($DBServer);

            $osClient = $DBServer->GetEnvironmentObject()->openstack(
                $DBServer->platform, $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)
            );

            if ($osClient->hasService('network')) {
                $platform = PlatformFactory::NewPlatform($DBServer->platform);
                $serverIps = $platform->GetServerIPAddresses($DBServer);

                // USE Quantum (Neuron) NETWORK
                $ips = $osClient->network->floatingIps->list();

                //Check free existing IP
                $ipAssigned = false;
                $ipAddress = false;
                $ipInfo = false;
                foreach ($ips as $ip) {
                    if ($ip->floating_network_id != $ipPool) {
                        continue;
                    }
                    
                    if ($ip->fixed_ip_address == $serverIps['localIp'] && $ip->port_id) {
                        $ipAssigned = true;
                        $ipInfo = $ip;
                        break;
                    }

                    if (!$ip->fixed_ip_address && $ip->floating_ip_address && !$ip->port_id) {
                        // Checking that FLoating IP has the same tenant as auth user
                        if ($ip->tenant_id && $ip->tenant_id == $osClient->getConfig()->getAuthToken()->getTenantId()) {
                            $ipInfo = $ip;
                        }
                    }
                }

                if ($ipInfo && !$ipAssigned) {
                    \Scalr::getContainer()->logger("Openstack")->warn(new FarmLogMessage($DBServer, sprintf("Found free floating IP: %s for use",
                        !empty($ipInfo->floating_ip_address) ? $ipInfo->floating_ip_address : null
                    )));
                }

                if (!$ipInfo || !$ipAssigned) {
                    // Get instance port
                    $ports = $osClient->network->ports->list();

                    $serverNetworkPort = [];

                    foreach ($ports as $port) {
                        if ($port->device_id == $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID)) {
                            $serverNetworkPort[] = $port;
                        }
                    }

                    if (empty($serverNetworkPort)) {
                        \Scalr::getContainer()->logger("Openstack")->error(new FarmLogMessage($DBServer, "Unable to identify network port of instance"));
                    } else {
                        $publicNetworkId = $ipPool;

                        while (!empty($serverNetworkPort)) {
                            try {
                                $port = array_shift($serverNetworkPort);

                                if (!$ipInfo) {
                                    $ipInfo = $osClient->network->floatingIps->create($publicNetworkId, $port->id);

                                    \Scalr::getContainer()->logger("Openstack")->warn(new FarmLogMessage($DBServer, sprintf("Allocated new IP %s for port: %s",
                                        !empty($ipInfo->floating_ip_address) ? $ipInfo->floating_ip_address : null,
                                        !empty($port->id) ? $port->id : null
                                    )));
                                } else {
                                    $osClient->network->floatingIps->update($ipInfo->id, $port->id);

                                    \Scalr::getContainer()->logger("Openstack")->warn(new FarmLogMessage($DBServer, sprintf("Existing floating IP %s was used for port: %s",
                                        !empty($ipInfo->floating_ip_address) ? $ipInfo->floating_ip_address : null,
                                        !empty($port->id) ? $port->id : null
                                    )));
                                }

                                $DBServer->SetProperties(array(
                                    \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP    => $ipInfo->floating_ip_address,
                                    \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ipInfo->id,
                                ));

                                $ipAddress = $ipInfo->floating_ip_address;

                                break;
                            } catch (Exception $e) {
                                \Scalr::getContainer()->logger("OpenStackObserver")->error(sprintf(
                                    "Farm: %d, Server: %s - Could not allocate/update floating IP: %s (%s, %s)",
                                    $DBServer->farmId,
                                    $DBServer->serverId,
                                    $e->getMessage(),
                                    json_encode($ipInfo),
                                    $osClient->getConfig()->getAuthToken()->getTenantId()
                                ));
                            }
                        }
                    }
                } else {
                    \Scalr::getContainer()->logger("Openstack")->warn(new FarmLogMessage($DBServer, sprintf("Server '%s' already has IP '%s' assigned",
                        $DBServer->serverId,
                        !empty($ipInfo->floating_ip_address) ? $ipInfo->floating_ip_address : null
                    )));

                    $ipAddress = $ipInfo->floating_ip_address;
                }
            } else {
                //USE NOVA NETWORK
                //Check free existing IP
                $ipAssigned = false;
                $ipAddress = false;

                $ips = $osClient->servers->floatingIps->list($ipPool);
                foreach ($ips as $ip) {
                    if (!$ip->instance_id) {
                        $ipAddress = $ip->ip;
                        $ipAddressId = $ip->id;
                    }


                    if ($ip->instance_id == $DBServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::SERVER_ID)) {
                        $ipAddress = $ip->ip;
                        $ipAssigned = true;
                    }
                }

                //If no free IP allocate new from pool
                if (!$ipAddress) {
                    $ip = $osClient->servers->floatingIps->create($ipPool);
                    $ipAddress = $ip->ip;
                    $ipAddressId = $ip->id;
                }

                if (!$ipAssigned) {
                    //Associate floating IP with Instance
                    $osClient->servers->addFloatingIp($DBServer->GetCloudServerID(), $ipAddress);

                    $DBServer->SetProperties(array(
                        \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => $ipAddress,
                        \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => $ipAddressId
                    ));
                }
            }

            if ($ipAddress) {
                $DBServer->remoteIp = $ipAddress;
                $DBServer->Save();
                $DBServer->SetProperty(\SERVER_PROPERTIES::SYSTEM_IGNORE_INBOUND_MESSAGES, null);
            }
        }
    }

    public static function removeServerFloatingIp(\DBServer $dbServer)
    {
        try {
            if ($dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::FLOATING_IP)) {

                if ($dbServer->farmRoleId) {
                    if ($dbServer->GetFarmRoleObject()->GetSetting(Entity\FarmRoleSetting::OPENSTACK_KEEP_FIP_ON_SUSPEND)) {
                        if (in_array($dbServer->status, array(\SERVER_STATUS::PENDING_SUSPEND,\SERVER_STATUS::SUSPENDED)) ||
                            $dbServer->GetRealStatus()->isSuspended()) {
                            return false;
                        }
                    }
                }

                $environment = $dbServer->GetEnvironmentObject();
                $osClient = $environment->openstack($dbServer->platform, $dbServer->GetCloudLocation());

                $ipId = $dbServer->GetProperty(\OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID);

                if ($osClient->hasService('network')) {
                    $osClient->network->floatingIps->delete($ipId);
                } else {
                    $osClient->servers->deleteFloatingIp($ipId);
                }

                $dbServer->SetProperties(array(
                    \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP => null,
                    \OPENSTACK_SERVER_PROPERTIES::FLOATING_IP_ID => null
                ));
            }
        } catch (Exception $e) {
            \Scalr::getContainer()->logger("OpenStackObserver")->fatal("OpenStackObserver observer failed: " . $e->getMessage());
        }
    }
}

