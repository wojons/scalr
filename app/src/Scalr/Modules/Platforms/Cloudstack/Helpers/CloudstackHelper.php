<?php

namespace Scalr\Modules\Platforms\Cloudstack\Helpers;

use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Cloudstack\CloudstackPlatformModule;
use Scalr\Service\CloudStack\DataType\ListIpAddressesData;
use Scalr\Service\CloudStack\DataType\AssociateIpAddressData;
use Scalr\Service\CloudStack\CloudStack;
use Scalr\Model\Entity;
use \DBFarm;
use \DBFarmRole;

class CloudstackHelper
{
    public static function farmSave(DBFarm $DBFarm, array $roles)
    {
        
    }

    public static function getSharedIP(\DBServer $dbServer)
    {
        $remoteIp = null;
        $platform = PlatformFactory::NewPlatform($dbServer->platform);
        $ips = $platform->GetServerIPAddresses($dbServer);
        if ($ips['remoteIp']) {
            $remoteIp = $ips['remoteIp'];
            $dbServer->GetFarmRoleObject()->SetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_TYPE, 'Direct', Entity\FarmRoleSetting::TYPE_LCL);
        } else {
            if ($dbServer->farmRoleId) {
                $dbFarmRole = $dbServer->GetFarmRoleObject();
                $networkType = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_TYPE);
                if ($networkType == 'Direct') {
                    $remoteIp = $ips['localIp'];
                } else {
                    $useStaticNat = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_USE_STATIC_NAT);
                    $networkId = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_ID);
                    if (!$useStaticNat && $networkId != 'SCALR_MANUAL') {
                        $sharedIp = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_SHARED_IP_ADDRESS);
                        if (!$sharedIp) {
                            $dbServer->GetEnvironmentObject()->cloudCredentials($dbServer->platform)->properties[
                                Entity\CloudCredentialsProperty::CLOUDSTACK_SHARED_IP . ".{$dbServer->GetProperty(\CLOUDSTACK_SERVER_PROPERTIES::CLOUD_LOCATION)}"
                            ];
                        } else {
                            $remoteIp = $sharedIp;
                        }
                    }
                }
            }
        }
        
        return $remoteIp;
    }
    
    /**
     * Associates IP Address to the server
     *
     * @param   \DBServer           $dbServer  \DBServer object
     * @param   string             $ipAddress Public IP address to associate with server.
     * @throws  \Exception
     */
    private static function associateIpAddress(\DBServer $dbServer, $ipAddress, $allocationId = null)
    {
        $platform = PlatformFactory::NewPlatform($dbServer->platform);

        $cs = $dbServer->GetEnvironmentObject()->cloudstack($dbServer->platform);

        $assign_retries = 1;
        $retval = false;

        // Remove OLD static NAT
        if ($dbServer->remoteIp) {
            try {
                $requestObject = new ListIpAddressesData();
                $requestObject->ipaddress = $dbServer->remoteIp;
                $info = $cs->listPublicIpAddresses($requestObject);
                $info = $info[0];
                if ($info->issystem && $info->isstaticnat) {
                    $cs->firewall->disableStaticNat($info->id);
                    \Scalr::getContainer()->logger('Cloudstack_Helper')->warn("[STATIC_NAT] Removed old IP static NAT from IP: {$info->id}");
                }
            } catch (\Exception $e) {
                \Scalr::getContainer()->logger('Cloudstack_Helper')->error("[STATIC_NAT] Unable to disable old static NAT: {$e->getMessage()}");
            }
        }

        $requestObject = new ListIpAddressesData();
        $requestObject->ipaddress = $ipAddress;
        $info = $cs->listPublicIpAddresses($requestObject);
        $info = $info[0];
        $oldVM = $info->virtualmachinedisplayname;
        if ($info->isstaticnat) {
            \Scalr::getContainer()->logger('Cloudstack_Helper')->warn("[STATIC_NAT] IP {$ipAddress} associated with another VM. Disabling Static NAT.");

            try {
                $result = $cs->firewall->disableStaticNat($allocationId);
                for ($i = 0; $i <= 20; $i++) {
                    $res = $cs->queryAsyncJobResult($result->jobid);
                    if ($res->jobstatus != 0) {
                        \Scalr::getContainer()->logger('Cloudstack_Helper')->warn("[STATIC_NAT] After {$i}x2 seconds, IP {$ipAddress} is free and ready to use.");

                        //UPDATING OLD SERVER
                        try {
                            $oldDbServer = \DBServer::LoadByID($oldVM);
                            $ips = $platform->GetServerIPAddresses($oldDbServer);
                            $oldDbServer->remoteIp = $ips['remoteIp'];
                            $oldDbServer->save();
                        } catch (\Exception $e) {}

                        break;
                    }
                    sleep(2);
                }

            } catch (\Exception $e) {
                \Scalr::getContainer()->logger('Cloudstack_Helper')->error("[STATIC_NAT] Unable to disable static NAT: {$e->getMessage()}");
            }
        }

        $assignRetries = 0;
        while (true) {
            try {
                $assignRetries++;
                $cs->firewall->enableStaticNat(array('ipaddressid' => $allocationId, 'virtualmachineid' => $dbServer->GetCloudServerID()));
                $retval = true;
                break;
            } catch (\Exception $e) {
                if (!stristr($e->getMessage(), "already assigned to antoher vm") || $assignRetries == 3) {
                    \Scalr::getContainer()->logger('Cloudstack_Helper')->error("[STATIC_NAT] Unable to enable static NAT ({$assignRetries}): {$e->getMessage()}");
                    throw new \Exception($e->getMessage());
                } else {
                    sleep(1);
                }
            }
            //break;
        }

        return $retval;
    }


    /**
     * Checks Elastic IP availability
     *
     * @param   string             $ipaddress public IP address
     * @param   \Scalr\Service\CloudStack\CloudStack $cs       CloudStack instance
     * @return  boolean Returns true if IP address is available.
     */
    private static function checkStaticNatIp($ipaddress, CloudStack $cs)
    {
        \Scalr::getContainer()->logger('Cloudstack_Helpers')->debug(sprintf(_("Checking IP: %s"), $ipaddress));
        try {
            $requestObject = new ListIpAddressesData();
            $requestObject->ipaddress = $ipaddress;
            $info = $cs->listPublicIpAddresses($requestObject);
            if (count($info) > 0) {
                return true;
            }
            else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function setStaticNatForServer(\DBServer $dbServer)
    {
        $db = \Scalr::getDb();

        try {
            $dbFarm = DBFarm::LoadByID($dbServer->farmId);
            $dbFarmRole = $dbServer->GetFarmRoleObject();
            if (!$dbFarmRole->GetSetting(Entity\FarmRoleSetting::CLOUDSTACK_USE_STATIC_NAT)) {
                return false;
            }
            $cs = $dbFarm->GetEnvironmentObject()->cloudstack($dbFarmRole->Platform);

        } catch (\Exception $e) {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->fatal(
            new \FarmLogMessage($dbServer->farmId, sprintf(
                _("Cannot allocate elastic ip address for instance %s on farm %s (0)"),
                $dbServer->serverId, isset($dbFarm->Name) ? $dbFarm->Name : ''
            )));

            return false;
        }

        $ip = $db->GetRow("
            SELECT * FROM elastic_ips
            WHERE farmid=?
            AND ((farm_roleid=? AND instance_index=?) OR server_id = ?)
            LIMIT 1
        ", array(
            $dbServer->farmId,
            $dbFarmRole->ID,
            $dbServer->index,
            $dbServer->serverId
        ));

        if ($ip['ipaddress']) {
            if (!self::checkStaticNatIp($ip['ipaddress'], $cs)) {
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->warn(new \FarmLogMessage($dbServer->farmId, sprintf(
                    _("Static NAT IP '%s' does not belong to you. Allocating new one."), $ip['ipaddress']
                )));

                $db->Execute("DELETE FROM elastic_ips WHERE ipaddress=?", array($ip['ipaddress']));
                $ip = false;
            }
        }

        if ($ip && $ip['ipaddress'] == $dbServer->remoteIp) {
            return $ip['ipaddress'];
        }

        // If free IP not found we must allocate new IP
        if (!$ip) {
            $alocatedIps = $db->GetOne("SELECT COUNT(*) FROM elastic_ips WHERE farm_roleid = ?", array(
                $dbFarmRole->ID
            ));

            // Check elastic IPs limit. We cannot allocate more than 'Max instances' option for role
            if ($alocatedIps < $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES)) {
                try {
                    $requestObject = new AssociateIpAddressData();
                    $requestObject->zoneid = $dbFarmRole->CloudLocation;
                    $ipResult = $cs->associateIpAddress($requestObject);
                    $ipId = !empty($ipResult->id) ? $ipResult->id : null;
                    if ($ipId) {
                        while (true) {
                            $requestObject = new ListIpAddressesData();
                            $requestObject->id = $ipId;
                            $ipInfo = $cs->listPublicIpAddresses($requestObject);
                            $ipInfo = count($ipInfo) > 0 ? $ipInfo[0] : null;

                            if (!$ipInfo) {
                                throw new \Exception("Cannot allocate IP address: listPublicIpAddresses -> failed");
                            }
                            if ($ipInfo->state == 'Allocated') {
                                break;
                            }
                            else if ($ipInfo->state == 'Allocating') {
                                sleep(1);
                            }
                            else {
                                throw new \Exception("Cannot allocate IP address: ipAddress->state = {$ipInfo->state}");
                            }
                        }
                    }
                    else {
                        throw new \Exception("Cannot allocate IP address: associateIpAddress -> failed");
                    }

                } catch (\Exception $e) {
                    \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->error(new \FarmLogMessage($dbServer->farmId, sprintf(
                        _("Cannot allocate new elastic ip for instance '%s': %s"),
                        $dbServer->serverId,
                        $e->getMessage()
                    )));
                    return false;
                }

                // Add allocated IP address to database
                $db->Execute("INSERT INTO elastic_ips SET
                    env_id=?,
                    farmid=?,
                    farm_roleid=?,
                    ipaddress=?,
                    clientid=?,
                    instance_index=?,
                    allocation_id=?,
                    state='0', server_id=''
                ", array(
                    $dbServer->envId,
                    $dbServer->farmId,
                    $dbServer->farmRoleId,
                    $ipInfo->ipaddress,
                    $dbServer->clientId,
                    $dbServer->index,
                    $ipId
                ));

                $ip = array(
                    'ipaddress' => $ipInfo->ipaddress,
                    'allocation_id' => $ipId
                );

                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->info(
                    new \FarmLogMessage($dbServer->farmId, sprintf(_("Allocated new IP: %s"), $ip['ipaddress']))
                );
                // Waiting...
                sleep(5);
            } else
                \Scalr::getContainer()->logger(__CLASS__)->fatal(_("Limit for elastic IPs reached. Check zomby records in database."));
        }

        if ($ip['ipaddress']) {
            self::associateIpAddress($dbServer, $ip['ipaddress'], $ip['allocation_id']);

            // Update leastic IPs table
            $db->Execute("UPDATE elastic_ips SET state='1', server_id=? WHERE ipaddress=?", array(
                $dbServer->serverId,
                $ip['ipaddress']
            ));
            \Scalr::FireEvent($dbServer->farmId, new \IPAddressChangedEvent(
                $dbServer, $ip['ipaddress'], $dbServer->localIp
            ));
        } else {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->fatal(
            new \FarmLogMessage($dbServer->farmId, sprintf(
                _("Cannot allocate elastic ip address for instance %s on farm %s (2)"),
                $dbServer->serverId, $dbFarm->Name
            )));
            return false;
        }

        return $ip['ipaddress'];
    }

    public static function farmValidateRoleSettings($settings, $rolename)
    {

    }

    public static function farmUpdateRoleSettings(DBFarmRole $dbFarmRole, $oldSettings, $newSettings)
    {
        $db = \Scalr::getDb();
        $dbFarm = $dbFarmRole->GetFarmObject();

        if ($newSettings[Entity\FarmRoleSetting::CLOUDSTACK_NETWORK_ID] == 'SCALR_MANUAL') {
            return true;
        }
        $dbFarmRole->SetSetting(Entity\FarmRoleSetting::CLOUDSTACK_STATIC_NAT_MAP, null, Entity\FarmRoleSetting::TYPE_LCL);

        $cs = $dbFarm->GetEnvironmentObject()->cloudstack($dbFarmRole->Platform);

        // Disassociate IP addresses if checkbox was unchecked
        if (!$newSettings[Entity\FarmRoleSetting::CLOUDSTACK_USE_STATIC_NAT] &&
            $oldSettings[Entity\FarmRoleSetting::CLOUDSTACK_USE_STATIC_NAT]) {

            $eips = $db->Execute("
                SELECT * FROM elastic_ips WHERE farm_roleid = ?
            ", array(
                $dbFarmRole->ID
            ));
            while ($eip = $eips->FetchRow()) {
                try {
                    $cs->disassociateIpAddress($eip['allocation_id']);
                } catch (\Exception $e) { }
            }

            $db->Execute("DELETE FROM elastic_ips WHERE farm_roleid = ?", array(
                $dbFarmRole->ID
            ));

        }

        //TODO: Handle situation when tab was not opened, but max instances setting was changed.
        if ($newSettings[Entity\FarmRoleSetting::CLOUDSTACK_STATIC_NAT_MAP] &&
            $newSettings[Entity\FarmRoleSetting::CLOUDSTACK_USE_STATIC_NAT]) {
            $map = explode(";", $newSettings[Entity\FarmRoleSetting::CLOUDSTACK_STATIC_NAT_MAP]);

            foreach ($map as $ipconfig) {
                list ($serverIndex, $ipAddress) = explode("=", $ipconfig);

                if (!$serverIndex) {
                    continue;
                }
                $dbServer = false;
                try {
                    $dbServer = \DBServer::LoadByFarmRoleIDAndIndex($dbFarmRole->ID, $serverIndex);

                    if ($dbServer->remoteIp == $ipAddress) {
                        continue;
                    }
                    // Remove old association
                    $db->Execute("
                        DELETE FROM elastic_ips WHERE farm_roleid = ? AND instance_index=?
                    ", array(
                        $dbFarmRole->ID,
                        $serverIndex
                    ));
                } catch (\Exception $e) {}

                // Allocate new IP if needed
                if ($ipAddress == "" || $ipAddress == '0.0.0.0') {
                    if ($dbServer) {
                        $ipAddress = self::setStaticNatForServer($dbServer);
                    } else {
                        continue;
                    }
                } else {
                    //Remove old IP association
                    $db->Execute("
                        DELETE FROM elastic_ips WHERE ipaddress=?
                    ", array(
                        $ipAddress
                    ));

                    $requestObject = new ListIpAddressesData();
                    $requestObject->ipaddress = $ipAddress;
                    $info = $cs->listPublicIpAddresses($requestObject);
                    $info = count($info > 0) ? $info[0] : null;

                    // Associate IP with server in our db
                    $db->Execute("INSERT INTO elastic_ips SET
                        env_id=?,
                        farmid=?,
                        farm_roleid=?,
                        ipaddress=?,
                        state='0',
                        instance_id='',
                        clientid=?,
                        instance_index=?,
                        allocation_id=?
                    ", array(
                        $dbFarm->EnvID,
                        $dbFarmRole->FarmID,
                        $dbFarmRole->ID,
                        $ipAddress,
                        $dbFarm->ClientID,
                        $serverIndex,
                        $info->id
                    ));
                }

                $ipInfo = $db->GetRow("SELECT allocation_id FROM elastic_ips WHERE ipaddress = ? LIMIT 1", array($ipAddress));

                // Associate IP on AWS with running server
                if ($dbServer) {
                    try {
                        $db->Execute("UPDATE elastic_ips SET state='1', server_id = ? WHERE ipaddress = ?", array(
                            $dbServer->serverId,
                            $ipAddress
                        ));

                        $update = false;

                        if ($dbServer->remoteIp != $ipAddress) {
                            if ($dbServer && $dbServer->status == \SERVER_STATUS::RUNNING) {
                                $fireEvent = self::associateIpAddress($dbServer, $ipAddress, $ipInfo['allocation_id']);
                            }
                        }

                        if ($fireEvent) {
                            $event = new \IPAddressChangedEvent($dbServer, $ipAddress, $dbServer->localIp);
                            \Scalr::FireEvent($dbServer->farmId, $event);
                        }
                    } catch (\Exception $e) {}
                }
            }
        }
    }
}

