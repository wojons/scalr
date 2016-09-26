<?php
namespace Scalr\Observer;

use Exception;
use RebootCompleteEvent;
use NewMysqlMasterUpEvent;
use NewDbMsrMasterUpEvent;
use IPAddressChangedEvent;
use FarmLaunchedEvent;
use DBFarm;
use DBDNSZone;
use DNS_ZONE_STATUS;
use FarmTerminatedEvent;
use ROLE_BEHAVIORS;
use SERVER_STATUS;
use DBServer;
use Scalr_Role_Behavior_MongoDB;
use ResumeCompleteEvent;
use HostUpEvent;
use BeforeHostTerminateEvent;
use HostDownEvent;

class DNSEventObserver extends AbstractEventObserver
{
    public $ObserverName = 'DNS';

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnRebootComplete()
     */
    public function OnRebootComplete(RebootCompleteEvent $event)
    {
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnNewMysqlMasterUp()
     */
    public function OnNewMysqlMasterUp(NewMysqlMasterUpEvent $event)
    {
        $this->updateZoneServerRecords($event->DBServer->serverId, $event->DBServer->farmId, true);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnNewDbMsrMasterUp()
     */
    public function OnNewDbMsrMasterUp(NewDbMsrMasterUpEvent $event)
    {
        $this->updateZoneServerRecords($event->DBServer->serverId, $event->DBServer->farmId, true);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnIPAddressChanged()
     */
    public function OnIPAddressChanged(IPAddressChangedEvent $event)
    {
        $this->updateZoneServerRecords($event->DBServer->serverId, $event->DBServer->farmId);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnFarmLaunched()
     */
    public function OnFarmLaunched(FarmLaunchedEvent $event)
    {
        //SYSTEM DNS RECORD
        if (\Scalr::config('scalr.dns.static.enabled')) {
            try {
                $hash = DBFarm::LoadByID($event->GetFarmID())->Hash;
                $pdnsDb = \Scalr::getContainer()->dnsdb;
                $pdnsDb->Execute("INSERT INTO `domains` SET `name`=?, `type`=?, `scalr_farm_id`=?", array("{$hash}." . \Scalr::config('scalr.dns.static.domain_name'),'NATIVE', $event->GetFarmID()));
            } catch (Exception $e) {
                \Scalr::logException($e);
            }
        }

        $zones = DBDNSZone::loadByFarmId($event->GetFarmID());

        if (count($zones) == 0) {
            return;
        }

        foreach ($zones as $zone) {
            if ($zone->status == DNS_ZONE_STATUS::INACTIVE) {
                $zone->status = DNS_ZONE_STATUS::PENDING_CREATE;
                $zone->isZoneConfigModified = 1;
                $zone->save();
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnFarmTerminated()
     */
    public function OnFarmTerminated(FarmTerminatedEvent $event)
    {
        //SYSTEM DNS ZONES
        if (\Scalr::config('scalr.dns.static.enabled')) {
            $pdnsDb = \Scalr::getContainer()->dnsdb;
            $pdnsDb->Execute("DELETE FROM `domains` WHERE scalr_farm_id = ?", array($event->GetFarmID()));
        }

        if (!$event->RemoveZoneFromDNS) {
            return;
        }

        $zones = DBDNSZone::loadByFarmId($event->GetFarmID());

        if (count($zones) == 0) {
            return;
        }

        foreach ($zones as $zone) {
            if ($zone->status != DNS_ZONE_STATUS::PENDING_DELETE) {
                $zone->status = DNS_ZONE_STATUS::INACTIVE;
                $zone->save();
            }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnResumeComplete()
     */
    public function OnResumeComplete(ResumeCompleteEvent $event)
    {
        $update_all = $event->DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL) ? true : false;

        $this->updateZoneServerRecords($event->DBServer->serverId, $event->DBServer->farmId, $update_all);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostUp()
     */
    public function OnHostUp(HostUpEvent $event)
    {
        $update_all = $event->DBServer->GetFarmRoleObject()->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL) ? true : false;

        $this->updateZoneServerRecords($event->DBServer->serverId, $event->DBServer->farmId, $update_all);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnBeforeHostTerminate()
     */
    public function OnBeforeHostTerminate(BeforeHostTerminateEvent $event)
    {
        $update_all = false;

        try {
            $update_all = $event->DBServer
                ->GetFarmRoleObject()
                ->GetRoleObject()
                ->hasBehavior(ROLE_BEHAVIORS::MYSQL) ? true : false;
        } catch (Exception $e) {
        }

        $this->updateZoneServerRecords($event->DBServer->serverId, $event->DBServer->farmId, $update_all);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Observer\AbstractEventObserver::OnHostDown()
     */
    public function OnHostDown(HostDownEvent $event)
    {
        $update_all = false;
        try {
            $update_all = $event->DBServer->GetFarmRoleObject()
                ->GetRoleObject()
                ->hasBehavior(ROLE_BEHAVIORS::MYSQL) ? true : false;
        } catch (Exception $e) {
        }

        $this->updateZoneServerRecords($event->DBServer->serverId, $event->DBServer->farmId, $update_all, true);
    }

    /**
     * Updates Zone Records
     *
     * @param   string   $serverId              The identifier of the Server
     * @param   int      $farmId                The identifier of the Farm
     * @param   bool     $resetAllSystemRecords optional
     * @param   bool     $skipStatusCheck       optional
     * @throws  Exception
     */
    private function updateZoneServerRecords($serverId, $farmId, $resetAllSystemRecords = false, $skipStatusCheck = false)
    {
        $zones = DBDNSZone::loadByFarmId($farmId);
        foreach ($zones as $DBDNSZone) {
            if (!$skipStatusCheck && ($DBDNSZone->status == DNS_ZONE_STATUS::PENDING_DELETE || $DBDNSZone->status == DNS_ZONE_STATUS::INACTIVE)) {
                continue;
            }

            if (!$resetAllSystemRecords) {
                $DBDNSZone->updateSystemRecords($serverId);
                $DBDNSZone->save();
            } else {
                $DBDNSZone->save(true);
            }
        }

        //UPDATE SYSTEM records
        try {
            $this->updateSystemZone($serverId, $farmId, $resetAllSystemRecords, $skipStatusCheck);
        } catch (Exception $e) {
            \Scalr::getContainer()->logger('SysDNS')->fatal("Cannot save system DNS zone: {$e->getMessage()}");
        }
    }

    /**
     * Updates System Zone
     *
     * @param   string   $serverId              The identifier of the Server
     * @param   int      $farmId                The identifier of the Farm
     * @param   bool     $resetAllSystemRecords optional
     * @param   bool     $skipStatusCheck       optional
     * @throws  Exception
     */
    private function updateSystemZone($serverId, $farmId, $resetAllSystemRecords = false, $skipStatusCheck = false)
    {
        //UPDATE RECORDS ONLY FOR SERVER
        if (!\Scalr::config('scalr.dns.static.enabled')) {
            return true;
        }

        $pdnsDb = \Scalr::getContainer()->dnsdb;

        $deleteMySQL = false;
        $deleteDbMsr = false;

        try {
            try {
                $server = DBServer::LoadByID($serverId);
                $dbRole = $server->GetFarmRoleObject()->GetRoleObject();
            } catch (Exception $e) {
            }

            $domain = $pdnsDb->GetRow("SELECT id, name FROM domains WHERE scalr_farm_id = ? LIMIT 1", array($server->farmId));

            $domainId = $domain['id'];
            $domainName = $domain['name'];

            if (!$domainId) {
                return;
            }

            $records = [];

            // Set index records
            if ($server && $server->status == SERVER_STATUS::RUNNING) {
                $records[] = array("int.{$server->index}.{$server->farmRoleId}", $server->localIp, $server->serverId);
                $records[] = array("ext.{$server->index}.{$server->farmRoleId}", $server->remoteIp, $server->serverId);

                if (\Scalr::config('scalr.dns.static.extended')) {
                    $records[] = array("int.{$server->farmRoleId}", $server->localIp, $server->serverId);
                    $records[] = array("ext.{$server->farmRoleId}", $server->remoteIp, $server->serverId);
                }

                if ($server->GetProperty(Scalr_Role_Behavior_MongoDB::SERVER_IS_ROUTER)) {
                    $records[] = array("int.mongo", $server->localIp, $server->serverId, 'mongodb');
                    $records[] = array("ext.mongo", $server->remoteIp, $server->serverId, 'mongodb');
                }

                if ($dbRole->hasBehavior(ROLE_BEHAVIORS::NGINX)) {
                    //$records[] = array("int.api.cloudfoundry", $server->localIp, $server->serverId, 'cloudfoundry');
                    //$records[] = array("ext.api.cloudfoundry", $server->remoteIp, $server->serverId, 'cloudfoundry');

                    $records[] = array("*.int.cloudfoundry", $server->localIp, $server->serverId, 'cloudfoundry');
                    $records[] = array("*.ext.cloudfoundry", $server->remoteIp, $server->serverId, 'cloudfoundry');
                }
            }

            if ($dbRole) {
                $isMysql = $dbRole->hasBehavior(ROLE_BEHAVIORS::MYSQL);

                if ($isMysql) {
                    // Clear records
                    $deleteMySQL = true;
                    $mysqlMasterServer = null;
                    $mysqlSlaves = 0;

                    $servers = $this->DB->Execute("
                        SELECT server_id, local_ip, remote_ip
                        FROM servers
                        WHERE `farm_roleid` = ? and `status`=?
                    ", [$server->farmRoleId, SERVER_STATUS::RUNNING]);

                    while ($s = $servers->FetchRow()) {
                        if ($this->DB->GetOne("SELECT value FROM server_properties WHERE server_id = ? AND name = ? LIMIT 1", array($s['server_id'], \SERVER_PROPERTIES::DB_MYSQL_MASTER)) == 1) {
                            $records[] = array("int.master.mysql", $s['local_ip'], $s['server_id'], 'mysql');
                            $records[] = array("ext.master.mysql", $s['remote_ip'], $s['server_id'], 'mysql');
                            $mysqlMasterServer = $s;
                        } else {
                            $records[] = array("int.slave.mysql", $s['local_ip'], $s['server_id'], 'mysql');
                            $records[] = array("ext.slave.mysql", $s['remote_ip'], $s['server_id'], 'mysql');
                            $mysqlSlaves++;
                        }

                        $records[] = array("int.mysql", $s['local_ip'], $s['server_id'], 'mysql');
                        $records[] = array("ext.mysql", $s['remote_ip'], $s['server_id'], 'mysql');
                    }

                    if ($mysqlSlaves == 0 && $mysqlMasterServer) {
                        $records[] = array("int.slave.mysql", $mysqlMasterServer['local_ip'], $mysqlMasterServer['server_id'], 'mysql');
                        $records[] = array("ext.slave.mysql", $mysqlMasterServer['remote_ip'], $mysqlMasterServer['server_id'], 'mysql');
                    }
                }

                $dbmsr = $dbRole->getDbMsrBehavior();

                if ($dbmsr) {
                    $recordPrefix = $dbmsr;

                    // Clear records
                    $deleteDbMsr = true;
                    $mysqlMasterServer = null;
                    $mysqlSlaves = 0;

                    $servers = $this->DB->Execute("
                        SELECT server_id, local_ip, remote_ip
                        FROM servers
                        WHERE `farm_roleid` = ?
                        AND `status`=?
                    ", [$server->farmRoleId, SERVER_STATUS::RUNNING]);

                    while ($s = $servers->FetchRow()) {
                        if ($this->DB->GetOne("
                                SELECT value FROM server_properties
                                WHERE server_id = ? AND name = ? LIMIT 1
                            ", [$s['server_id'], \Scalr_Db_Msr::REPLICATION_MASTER]) == 1
                        ) {
                            $records[] = array("int.master.{$recordPrefix}", $s['local_ip'], $s['server_id'], $dbmsr);
                            $records[] = array("ext.master.{$recordPrefix}", $s['remote_ip'], $s['server_id'], $dbmsr);

                            $mysqlMasterServer = $s;
                        } else {
                            $records[] = array("int.slave.{$recordPrefix}", $s['local_ip'], $s['server_id'], $dbmsr);
                            $records[] = array("ext.slave.{$recordPrefix}", $s['remote_ip'], $s['server_id'], $dbmsr);

                            $mysqlSlaves++;
                        }

                        $records[] = array("int.{$recordPrefix}", $s['local_ip'], $s['server_id'], $dbmsr);
                        $records[] = array("ext.{$recordPrefix}", $s['remote_ip'], $s['server_id'], $dbmsr);
                    }

                    if ($mysqlSlaves == 0 && $mysqlMasterServer) {
                        $records[] = array("int.slave.{$recordPrefix}", $mysqlMasterServer['local_ip'], $mysqlMasterServer['server_id'], $dbmsr);
                        $records[] = array("ext.slave.{$recordPrefix}", $mysqlMasterServer['remote_ip'], $mysqlMasterServer['server_id'], $dbmsr);
                    }
                }
            }

            /*
            foreach ($cnameRecords as $cr) {
                $this->DB->Execute("INSERT INTO powerdns.records SET
                    `domain_id`=?, `name`=?, `type`=?, `content`=?, `ttl`=?, `server_id`=?, `service`=?
                ",
                array($domainId, "$cr[0].{$domainName}", "CNAME", "{$cr[1]}", 20, $cr[2], $cr[3]));
            }
            */

            $pdnsDb->Execute("DELETE FROM records WHERE server_id = ?", array($serverId));

            if ($deleteMySQL) {
                $pdnsDb->Execute("DELETE FROM records WHERE `service` = ? AND domain_id = ?", array('mysql', $domainId));
            }

            if ($deleteDbMsr) {
                $pdnsDb->Execute("DELETE FROM records WHERE `service` = ? AND domain_id = ?", array($dbmsr, $domainId));
            }

            if (count($records) > 0) {
                foreach ($records as $r) {
                    $pdnsDb->Execute("
                        INSERT INTO records
                        SET `domain_id`=?,
                            `name`=?,
                            `type`=?,
                            `content`=?,
                            `ttl`=?,
                            `server_id`=?,
                            `service`=?
                    ", [
                        $domainId,
                        "$r[0].{$domainName}",
                        "A",
                        "{$r[1]}",
                        20,
                        $r[2],
                        (count($r) == 4) ? $r[3] : null
                    ]);
                }
            }
        } catch (Exception $e) {
            throw $e;
        }
    }
}
