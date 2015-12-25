<?php

namespace Scalr\Modules\Platforms\Ec2\Helpers;

use Scalr\Service\Aws\Ec2\DataType\AssociateAddressRequestData;
use Scalr\Model\Entity;
use \DBServer;
use \DBFarm;
use \DBFarmRole;

class EipHelper
{

    public static function farmValidateRoleSettings($settings, $rolename)
    {
    }

    /**
     * Associates IP Address to the server
     *
     * @param   DBServer           $dbServer  DBServer object
     * @param   string             $ipAddress Public IP address to associate with server.
     * @throws  \Exception
     */
    private static function associateIpAddress(DBServer $dbServer, $ipAddress, $allocationId = null)
    {

        $aws = $dbServer->GetEnvironmentObject()->aws($dbServer);

        $assign_retries = 1;
        $retval = false;
        while (true) {
            try {
                // Associate elastic ip address with instance
                $request = new AssociateAddressRequestData(
                    $dbServer->GetProperty(\EC2_SERVER_PROPERTIES::INSTANCE_ID),
                    $ipAddress
                );

                if ($allocationId) {
                    $request->allocationId = $allocationId;
                    $request->publicIp = null;
                    $request->allowReassociation = true;
                }

                $aws->ec2->address->associate($request);
                $retval = true;
                break;
            } catch (\Exception $e) {
                if (!stristr($e->getMessage(), "does not belong to you") || $assign_retries == 3) {
                    throw new \Exception($e->getMessage());
                } else {
                    // Waiting...
                    \Scalr::getContainer()->logger(__CLASS__)->debug(_("Waiting 2 seconds..."));
                    sleep(2);
                    $assign_retries++;
                    continue;
                }
            }
            break;
        }
        return $retval;
    }

    /**
     * Checks Elastic IP availability
     *
     * @param   string             $ipaddress public IP address
     * @param   \Scalr\Service\Aws $aws       AWS instance
     * @return  boolean Returns true if IP address is available.
     */
    private static function checkElasticIp($ipaddress, \Scalr\Service\Aws $aws)
    {
        try {
            $info = $aws->ec2->address->describe($ipaddress);
            if (count($info)) return true;
            else return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param DBServer $dbServer
     * @return boolean|string
     */
    public static function setEipForServer(DBServer $dbServer)
    {
        $db = \Scalr::getDb();

        try {
            $dbFarm = DBFarm::LoadByID($dbServer->farmId);
            $dbFarmRole = $dbServer->GetFarmRoleObject();
            if (!$dbFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_USE_ELASIC_IPS))
                return false;

            $aws = $dbFarm->GetEnvironmentObject()->aws($dbFarmRole->CloudLocation);

            $isVPC = $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID);
        } catch (\Exception $e) {
            \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->fatal(
                new \FarmLogMessage($dbServer->farmId, sprintf(
                    _("Cannot allocate elastic ip address for instance %s on farm %s (0)"),
                    $dbServer->serverId, $dbFarm->Name
            )));
            
            return false;
        }

        $ip = $db->GetRow("
            SELECT * FROM elastic_ips WHERE farmid=? AND ((farm_roleid=? AND instance_index=?) OR server_id = ?) LIMIT 1
        ", array(
            $dbServer->farmId,
            $dbFarmRole->ID,
            $dbServer->index,
            $dbServer->serverId
        ));

        if ($ip['ipaddress']) {
            if (!self::checkElasticIp($ip['ipaddress'], $aws)) {
                \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->warn(new \FarmLogMessage($dbServer->farmId, sprintf(
                    _("Elastic IP '%s' does not belong to you. Allocating new one."), $ip['ipaddress']
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
            if (!$dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED) || $alocatedIps < $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES)) {
                try {
                    $domain = null;
                    if ($isVPC)
                        $domain = 'vpc';

                    $address = $aws->ec2->address->allocate($domain);
                } catch (\Exception $e) {
                    \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->error(new \FarmLogMessage($dbServer->farmId, sprintf(
                        _("Cannot allocate new elastic ip for instance '%s': %s"),
                        $dbServer->serverId,
                        $e->getMessage()
                    )));
                    return false;
                }

                // Add allocated IP address to database
                $db->Execute("
                    INSERT INTO elastic_ips
                    SET env_id=?,
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
                    $address->publicIp,
                    $dbServer->clientId,
                    $dbServer->index,
                    $address->allocationId
                ));

                $ip = array(
                    'ipaddress' => $address->publicIp,
                    'allocation_id' => $address->allocationId
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

            $allocationId = null;
            if ($isVPC) {
                $allocationId = $ip['allocation_id'];
            }

            self::associateIpAddress($dbServer, $ip['ipaddress'], $allocationId);

            // Update leastic IPs table
            $db->Execute("UPDATE elastic_ips SET state='1', server_id=? WHERE ipaddress=?", array(
                $dbServer->serverId,
                $ip['ipaddress']
            ));

            /*
             * DO NOT FIRE THIS EVENT AND LET POLLER TO FIND OUT THAT IP WAS CHANGED AND
             * FIRE EVENT. BY THIS TIME MOST LIKELY IP WILL BE ROUTABLE
            \Scalr::FireEvent($dbServer->farmId, new \IPAddressChangedEvent(
                $dbServer, $ip['ipaddress'], $dbServer->localIp
            ));
            */
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

    public static function farmUpdateRoleSettings(DBFarmRole $DBFarmRole, $oldSettings, $newSettings)
    {
        $db = \Scalr::getDb();
        $DBFarm = $DBFarmRole->GetFarmObject();
        $DBFarmRole->SetSetting(Entity\FarmRoleSetting::AWS_ELASIC_IPS_MAP, null, Entity\FarmRoleSetting::TYPE_LCL);
        $aws = $DBFarm->GetEnvironmentObject()->aws($DBFarmRole->CloudLocation);
        $isVPC = $DBFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID);

        // Disassociate IP addresses if checkbox was unchecked
        if (empty($newSettings[Entity\FarmRoleSetting::AWS_USE_ELASIC_IPS]) &&
            !empty($oldSettings[Entity\FarmRoleSetting::AWS_USE_ELASIC_IPS])) {

            $eips = $db->Execute("
                SELECT * FROM elastic_ips WHERE farm_roleid = ?
            ", array(
                $DBFarmRole->ID
            ));
            while ($eip = $eips->FetchRow()) {
                try {
                    $aws->ec2->address->disassociate($eip['ipaddress']);
                } catch (\Exception $e) { }
            }

            $db->Execute("
                DELETE FROM elastic_ips WHERE farm_roleid = ?
            ", array(
                $DBFarmRole->ID
            ));

        }

        //TODO: Handle situation when tab was not opened, but max instances setting was changed.
        if (!empty($newSettings[Entity\FarmRoleSetting::AWS_ELASIC_IPS_MAP]) &&
            !empty($newSettings[Entity\FarmRoleSetting::AWS_USE_ELASIC_IPS])) {
            $map = explode(";", $newSettings[Entity\FarmRoleSetting::AWS_ELASIC_IPS_MAP]);

            $maxIndex = 0;

            foreach ($map as $ipconfig) {
                list ($serverIndex, $ipAddress) = explode("=", $ipconfig);

                if (!$serverIndex) {
                    continue;
                }

                if ($serverIndex > $maxIndex) {
                    $maxIndex = $serverIndex;
                }

                $allocationId = null;

                try {
                    $dbServer = DBServer::LoadByFarmRoleIDAndIndex($DBFarmRole->ID, $serverIndex);
                } catch (\Exception $e) {}

                // Allocate new IP if needed
                if ((!$ipAddress || $ipAddress == '0.0.0.0') && $dbServer) {
                    $domain = ($isVPC) ? 'vpc' : null;

                    $address = $aws->ec2->address->allocate($domain);
                    $ipAddress = $address->publicIp;
                    $allocationId = $address->allocationId;
                }

                // Remove old association
                $db->Execute("
                    DELETE FROM elastic_ips
                    WHERE farm_roleid = ? AND instance_index=?
                ", array(
                    $DBFarmRole->ID,
                    $serverIndex
                ));

                if ((!$ipAddress || $ipAddress == '0.0.0.0') && !$dbServer) {
                    continue;
                }

                if ($ipAddress) {
                    //Remove old IP association
                    $db->Execute("
                        DELETE FROM elastic_ips
                        WHERE ipaddress=?
                    ", array(
                        $ipAddress
                    ));

                    if (!$allocationId && $isVPC) {
                        $allocationId = $aws->ec2->address->describe($ipAddress)->get(0)->allocationId;
                    }

                    // Associate IP with server in our db
                    $db->Execute("
                        INSERT INTO elastic_ips
                        SET env_id=?,
                            farmid=?,
                            farm_roleid=?,
                            ipaddress=?,
                            state='0',
                            instance_id='',
                            clientid=?,
                            instance_index=?,
                            allocation_id=?
                    ", array(
                        $DBFarm->EnvID,
                        $DBFarmRole->FarmID,
                        $DBFarmRole->ID,
                        $ipAddress,
                        $DBFarm->ClientID,
                        $serverIndex,
                        $allocationId
                    ));

                    // Associate IP on AWS with running server
                    try {
                        $dbServer = DBServer::LoadByFarmRoleIDAndIndex($DBFarmRole->ID, $serverIndex);

                        $db->Execute("
                            UPDATE elastic_ips
                            SET state='1',
                                server_id = ?
                            WHERE ipaddress = ?
                        ", array(
                            $dbServer->serverId,
                            $ipAddress
                        ));

                        $update = false;

                        if ($dbServer->remoteIp != $ipAddress) {
                            if ($dbServer && $dbServer->status == \SERVER_STATUS::RUNNING) {
                                $fireEvent = self::associateIpAddress($dbServer, $ipAddress, ($isVPC) ? $allocationId : null);
                            }
                        }

                        if ($fireEvent) {
                            $event = new \IPAddressChangedEvent($dbServer, $ipAddress, $dbServer->localIp);
                            \Scalr::FireEvent($dbServer->farmId, $event);
                        }
                    } catch (\Exception $e) {}
                } else {
                    \Scalr::getContainer()->logger(\LOG_CATEGORY::FARM)->fatal(sprintf(
                        _("Cannot allocate elastic ip address for instance %s on farm %s (2)"),
                        $dbServer->serverId, $DBFarm->Name
                    ));
                }
            }

            // Remove extra definitions
            $db->Execute("DELETE FROM elastic_ips WHERE instance_index > ? AND farm_roleid = ?", array(
                $maxIndex,
                $DBFarmRole->ID
            ));
        }
    }
}
