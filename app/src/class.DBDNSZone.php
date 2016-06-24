<?php

use Scalr\Model\Entity;

class DBDNSZone
{
    public
        $id,
        $clientId,
        $envId,
        $farmId,
        $farmRoleId,
        $zoneName,
        $status,
        $soaOwner,
        $soaTtl,
        $soaParent,
        $soaSerial,
        $soaRefresh,
        $soaRetry,
        $soaExpire,
        $soaMinTtl,
        $dateLastModified,
        $axfrAllowedHosts,
        $allowedAccounts,
        $allowManageSystemRecords,
        $privateRootRecords,
        $isOnNsServer,
        $isZoneConfigModified;

    /**
     * @var \ADODB_mysqli
     */
    private $db;

    private $records;
    private $updateRecords = false;

    /**
     * @var \DBFarm
     */
    private $dbFarm;

    private static $FieldPropertyMap = array(
        'id' 			=> 'id',
        'farm_roleid'	=> 'farmRoleId',
        'farm_id'		=> 'farmId',
        'client_id'		=> 'clientId',
        'env_id'		=> 'envId',
        'zone_name'		=> 'zoneName',
        'status' 		=> 'status',
        'soa_owner'		=> 'soaOwner',
        'soa_ttl'		=> 'soaTtl',
        'soa_parent'	=> 'soaParent',
        'soa_serial'	=> 'soaSerial',
        'soa_refresh'	=> 'soaRefresh',
        'soa_retry'		=> 'soaRetry',
        'soa_expire'	=> 'soaExpire',
        'soa_min_ttl'	=> 'soaMinTtl',
        'dtlastmodified'=> 'dateLastModified',
        'axfr_allowed_hosts'	=> 'axfrAllowedHosts',
        'allow_manage_system_records'	=> 'allowManageSystemRecords',
        'isonnsserver'	=> 'isOnNsServer',
        'iszoneconfigmodified'	=> 'isZoneConfigModified',
        'allowed_accounts' => 'allowedAccounts',
        'private_root_records' => 'privateRootRecords'
    );

    function __construct($id = null)
    {
        $this->id = $id;
        $this->db = \Scalr::getDb();
    }

    /**
     * Gets DBFarm object
     *
     * @return \DBFarm
     */
    public function getFarmObject()
    {
        if (!$this->dbFarm && !empty($this->farmId)) {
            $this->dbFarm = \DBFarm::LoadByID($this->farmId);
        }

        return $this->dbFarm;
    }

    /**
     * @return array
     * @param integer $farm_id
     */
    public static function loadByFarmId($farm_id)
    {
        $db = \Scalr::getDb();
        $zones = $db->GetAll("SELECT id FROM dns_zones WHERE farm_id=?", array($farm_id));
        $retval = array();
        foreach ($zones as $zone)
            $retval[] = DBDNSZone::loadById($zone['id']);

        return $retval;
    }

    /**
     *
     * @param integer $id
     * @return DBDNSZone
     */
    public static function loadById($id)
    {
        $db = \Scalr::getDb();

        $zoneinfo = $db->GetRow("SELECT * FROM dns_zones WHERE id=?", array($id));
        if (!$zoneinfo)
            throw new Exception(sprintf(_("DNS zone ID#%s not found in database"), $id));

        $DBDNSZone = new DBDNSZone($id);

        foreach(self::$FieldPropertyMap as $k=>$v)
        {
            if (isset($zoneinfo[$k]))
                $DBDNSZone->{$v} = $zoneinfo[$k];
        }

        return $DBDNSZone;
    }

    /**
     *
     * @param unknown_type $zoneName
     * @param unknown_type $soaRefresh
     * @param unknown_type $soaExpire
     * @return DBDNSZone
     */
    public static function create($zoneName, $soaRefresh, $soaExpire, $soaOwner, $soaRetry = 7200)
    {
        $zone = new DBDNSZone();
        $zone->zoneName = $zoneName;
        $zone->soaRefresh = $soaRefresh;
        $zone->soaExpire = $soaExpire;
        $zone->status = DNS_ZONE_STATUS::PENDING_CREATE;

        $nameservers = Scalr::config('scalr.dns.global.nameservers');

        $zone->soaOwner = $soaOwner;
        $zone->soaTtl = 14400;
        $zone->soaParent = $nameservers[0];
        $zone->soaSerial = date("Ymd")."01";
        $zone->soaRetry = $soaRetry ? $soaRetry : 7200;
        $zone->soaMinTtl = 300;

        return $zone;
    }

    public function getContents($config_contents = false)
    {
        $this->loadRecords();

        $this->soaSerial = Scalr_Net_Dns_SOARecord::raiseSerial($this->soaSerial);
        $this->save();

        $soaRecord = new Scalr_Net_Dns_SOARecord(
            $this->zoneName,
            $this->soaParent,
            $this->soaOwner,
            $this->soaTtl,
            $this->soaSerial,
            $this->soaRefresh,
            $this->soaRetry,
            $this->soaExpire,
            $this->soaMinTtl
        );

        $zone = new Scalr_Net_Dns_Zone();
        $zone->addRecord($soaRecord);

        if (!$config_contents) {
            $rCache = [];
            foreach ($this->records as $record) {
                if (empty($rCache[$record['type']])) {
                    $r = new ReflectionClass("Scalr_Net_Dns_{$record['type']}Record");

                    $params = [];
                    foreach ($r->getConstructor()->getParameters() as $p) {
                        $params[] = $p->name;
                    }

                    $rCache[$record['type']] = array(
                        'reflect' => $r,
                        'params'  => $params
                    );
                }

                $args = [];
                foreach ($rCache[$record['type']]['params'] as $p) {
                    $args[$p] = isset($record[$p]) ? $record[$p] : null;
                }

                try {
                    $r = $rCache[$record['type']]['reflect']->newInstanceArgs($args);
                    $zone->addRecord($r);
                } catch(Exception $e){
                }
            }
        }

        return $zone->generate($this->axfrAllowedHosts, $config_contents);
    }

    private function getBehaviorsRecords(DBServer $dbServer)
    {
        $records = array();
        if ($dbServer->farmRoleId != 0) {
            foreach (Scalr_Role_Behavior::getListForFarmRole($dbServer->GetFarmRoleObject()) as $behavior) {
                $records = array_merge($records, (array)$behavior->getDnsRecords($dbServer));
            }
        }

        return $records;
    }

    private function getDbRecords(DBServer $dbServer)
    {
        $dbType = $dbServer->GetFarmRoleObject()->GetRoleObject()->getDbMsrBehavior();
        if (!$dbType)
            return array();

        if ($dbType == ROLE_BEHAVIORS::MYSQL2 || $dbType == ROLE_BEHAVIORS::PERCONA || $dbType == ROLE_BEHAVIORS::MARIADB)
            $dbType = 'mysql';

        $records = array();

        array_push($records, array(
            "name" 		=> "int-{$dbType}",
            "value"		=> $dbServer->localIp,
            "type"		=> "A",
            "ttl"		=> 90,
            "server_id"	=> $dbServer->serverId,
            "issystem"	=> '1'
        ));

        array_push($records, array(
            "name" 		=> "ext-{$dbType}",
            "value"		=> $dbServer->remoteIp,
            "type"		=> "A",
            "ttl"		=> 90,
            "server_id"	=> $dbServer->serverId,
            "issystem"	=> '1'
        ));

        if ($dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER) == 1)
        {
            array_push($records, array(
                "name" 		=> "int-{$dbType}-master",
                "value"		=> $dbServer->localIp,
                "type"		=> "A",
                "ttl"		=> 90,
                "server_id"	=> $dbServer->serverId,
                "issystem"	=> '1'
            ));

            array_push($records, array(
                "name" 		=> "ext-{$dbType}-master",
                "value"		=> $dbServer->remoteIp,
                "type"		=> "A",
                "ttl"		=> 90,
                "server_id"	=> $dbServer->serverId,
                "issystem"	=> '1'
            ));
        }

        if ($dbServer->GetFarmRoleObject()->GetRunningInstancesCount() == 1 || !$dbServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER))
        {
            array_push($records, array(
                "name" 		=> "int-{$dbType}-slave",
                "value"		=> $dbServer->localIp,
                "type"		=> "A",
                "ttl"		=> 90,
                "server_id"	=> $dbServer->serverId,
                "issystem"	=> '1'
            ));

            array_push($records, array(
                "name" 		=> "ext-{$dbType}-slave",
                "value"		=> $dbServer->remoteIp,
                "type"		=> "A",
                "ttl"		=> 90,
                "server_id"	=> $dbServer->serverId,
                "issystem"	=> '1'
            ));
        }

        return $records;
    }

    private function getServerDNSRecords(DBServer $DBServer)
    {
        $records = array();

        if ($DBServer->status != SERVER_STATUS::RUNNING)
            return $records;

        if ($DBServer->GetProperty(SERVER_PROPERTIES::EXCLUDE_FROM_DNS))
            return $records;

        $DBFarmRole = $DBServer->GetFarmRoleObject();
        if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::EXCLUDE_FROM_DNS))
            return $records;

        if ($DBFarmRole->ID == $this->farmRoleId) {
            $ip = $this->privateRootRecords == 1 ? $DBServer->localIp : $DBServer->remoteIp;
            if ($ip) {
                array_push($records, array(
                    "name" 		=> "@",
                    "value"		=> $ip,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));
            }
        }

        if (!$DBFarmRole->GetSetting(Entity\FarmRoleSetting::DNS_CREATE_RECORDS))
            return $records;

        $int_record_alias = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::DNS_INT_RECORD_ALIAS);
        $int_record = "int-{$DBFarmRole->GetRoleObject()->name}";

        $ext_record_alias = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::DNS_EXT_RECORD_ALIAS);
        $ext_record = "ext-{$DBFarmRole->GetRoleObject()->name}";



        if ($int_record_alias)
            $int_record = $DBServer->applyGlobalVarsToValue($int_record_alias);

        if ($ext_record_alias)
            $ext_record = $DBServer->applyGlobalVarsToValue($ext_record_alias);

        if ($DBServer->localIp) {
            array_push($records, array(
                    "name" 		=> $int_record,
                    "value"		=> $DBServer->localIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
            ));
        }
        
        if ($DBServer->remoteIp) {
            array_push($records, array(
                    "name" 		=> $ext_record,
                    "value"		=> $DBServer->remoteIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
            ));
        }

        $records = array_merge($records, (array)$this->getDbRecords($DBServer));
        $records = array_merge($records, (array)$this->getBehaviorsRecords($DBServer));

        if ($DBFarmRole->GetRoleObject()->hasBehavior(ROLE_BEHAVIORS::MYSQL))
        {
            array_push($records, array(
                "name" 		=> "int-mysql",
                "value"		=> $DBServer->localIp,
                "type"		=> "A",
                "ttl"		=> 90,
                "server_id"	=> $DBServer->serverId,
                "issystem"	=> '1'
            ));

            array_push($records, array(
                "name" 		=> "ext-mysql",
                "value"		=> $DBServer->remoteIp,
                "type"		=> "A",
                "ttl"		=> 90,
                "server_id"	=> $DBServer->serverId,
                "issystem"	=> '1'
            ));

            if ($DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER))
            {
                array_push($records, array(
                    "name" 		=> "int-mysql-master",
                    "value"		=> $DBServer->localIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));

                array_push($records, array(
                    "name" 		=> "ext-mysql-master",
                    "value"		=> $DBServer->remoteIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));

                array_push($records, array(
                    "name" 		=> "{$int_record}-master",
                    "value"		=> $DBServer->localIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));

                array_push($records, array(
                    "name" 		=> "{$ext_record}-master",
                    "value"		=> $DBServer->remoteIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));
            }

            if ($DBFarmRole->GetRunningInstancesCount() == 1 || !$DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER))
            {
                array_push($records, array(
                    "name" 		=> "int-mysql-slave",
                    "value"		=> $DBServer->localIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));

                array_push($records, array(
                    "name" 		=> "ext-mysql-slave",
                    "value"		=> $DBServer->remoteIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));

                array_push($records, array(
                    "name" 		=> "{$int_record}-slave",
                    "value"		=> $DBServer->localIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));

                array_push($records, array(
                    "name" 		=> "{$ext_record}-slave",
                    "value"		=> $DBServer->remoteIp,
                    "type"		=> "A",
                    "ttl"		=> 90,
                    "server_id"	=> $DBServer->serverId,
                    "issystem"	=> '1'
                ));
            }
        }

        return $records;
    }

    public function updateSystemRecords($server_id = null)
    {
        if (!$server_id) {
            $this->db->Execute("DELETE FROM dns_zone_records WHERE zone_id=? AND issystem='1' AND server_id != ''", array($this->id));

            if ($this->farmId) {
                $system_records = array();
                try
                {
                    $DBFarm = DBFarm::LoadByID($this->farmId);
                    $DBServers = $DBFarm->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));

                    foreach ($DBServers as $DBServer)
                        $system_records = array_merge($this->getServerDNSRecords($DBServer), $system_records);
                }
                catch(Exception $e)
                {
                    //
                }
            }
        }
        else
        {
            $this->db->Execute("DELETE FROM dns_zone_records WHERE zone_id=? AND issystem='1' AND server_id=?", array($this->id, $server_id));
            $system_records = $this->getServerDNSRecords(DBServer::LoadByID($server_id));
        }


        if ($system_records) {
            foreach ($system_records as $record) {
                //UNIQUE KEY `zoneid` (`zone_id`,`type`(1),`value`,`name`)
                $this->db->Execute("
                    INSERT INTO dns_zone_records
                    SET `zone_id` = ?,
                        `type` = ?,
                        `value` = ?,
                        `name` = ?,
                        `issystem` = '1',
                        `ttl` = ?,
                        `priority` = ?,
                        `weight` = ?,
                        `port` = ?,
                        `server_id` = ?
                    ON DUPLICATE KEY UPDATE
                        `issystem` = '1',
                        `ttl` = ?,
                        `priority` = ?,
                        `weight` = ?,
                        `port` = ?,
                        `server_id` = ?
                ", array(
                    $this->id,
                    $record['type'],
                    $record['value'],
                    $record['name'],

                    (int)$record['ttl'],
                    (int)$record['priority'],
                    (int)$record['weight'],
                    (int)$record['port'],
                    $record['server_id'],

                    (int)$record['ttl'],
                    (int)$record['priority'],
                    (int)$record['weight'],
                    (int)$record['port'],
                    $record['server_id'],
                ));
            }
        }

        if ($this->status == DNS_ZONE_STATUS::ACTIVE)
            $this->status = DNS_ZONE_STATUS::PENDING_UPDATE;
    }

    private function loadRecords()
    {
        $this->records = $this->db->GetAll("SELECT * FROM dns_zone_records WHERE zone_id=?", array($this->id));
    }

    public function getRecords($includeSystem = true)
    {
        if (!$this->records)
            $this->loadRecords();

        if ($includeSystem)
            return $this->records;

        $retval = array();
        foreach ($this->records as $record)
            if (!$record['issystem'])
                $retval[] = $record;

        return $retval;
    }

    public function setRecords($records)
    {
        $this->records = $records;
        $this->updateRecords = true;

        if ($this->status == DNS_ZONE_STATUS::ACTIVE) {
            $this->status = DNS_ZONE_STATUS::PENDING_UPDATE;
        }
    }

    public function remove()
    {
        $this->db->Execute("DELETE FROM dns_zones WHERE id=?", array($this->id));
        $this->db->Execute("DELETE FROM dns_zone_records WHERE zone_id=?", array($this->id));

        try {
            $this->removeFromPowerDns();
        } catch (Exception $e) {}
    }

    private function unBind () {
        $row = array();
        foreach (self::$FieldPropertyMap as $field => $property) {
            $row[$field] = $this->{$property};
        }

        return $row;
    }

    protected function removeFromPowerDns()
    {
        if (!\Scalr::config('scalr.dns.global.enabled')) return true;

        $pdnsDb = \Scalr::getContainer()->dnsdb;

        $pdnsDb->Execute("DELETE FROM domains WHERE name = ? AND scalr_dns_type = 'global'", array($this->zoneName));
    }

    private function IsDomain($var, $name = null, $error = null, $allowed_utf8_chars = "", $disallowed_utf8_chars = "")
    {
        // Remove trailing dot if its there. FQDN may contain dot at the end!
        $var = rtrim($var, ".");

        $retval = (bool)preg_match('/^([a-zA-Z0-9'.$allowed_utf8_chars.']+[a-zA-Z0-9-'.$allowed_utf8_chars.']*\.[a-zA-Z0-9'.$allowed_utf8_chars.']*?)+$/usi', $var);

        if ($disallowed_utf8_chars != '')
            $retval &= !(bool)preg_match("/[{$disallowed_utf8_chars}]+/siu", $var);

        return $retval;
    }

    public function save ($update_system_records = false) {

        $row = $this->unBind();
        unset($row['id']);
        unset($row['dtlastmodified']);

        $this->db->BeginTrans();

        // Prepare SQL statement
        $set = array();
        $bind = array();
        foreach ($row as $field => $value) {
            $set[] = "`$field` = ?";
            $bind[] = $value;
        }
        $set = join(', ', $set);

        try	{

            //Save zone;

            if ($this->id) {

                if ($update_system_records)
                    $this->updateSystemRecords();

                // Perform Update
                $bind[] = $this->id;
                $this->db->Execute("UPDATE dns_zones SET $set, `dtlastmodified` = NOW() WHERE id = ?", $bind);

                //TODO:
                if ($update_system_records) {
                    $this->db->Execute("UPDATE dns_zones SET status=?, `dtlastmodified` = NOW() WHERE id = ?",
                        array($this->status, $this->id)
                    );
                }
            }
            else {
                // Perform Insert
                $this->db->Execute("INSERT INTO dns_zones SET $set", $bind);
                $this->id = $this->db->Insert_ID();

                if ($update_system_records) {
                    $this->updateSystemRecords();
                    $this->db->Execute("UPDATE dns_zones SET status=?, `dtlastmodified` = NOW() WHERE id = ?",
                        array($this->status, $this->id)
                    );
                }
            }

            if ($this->updateRecords) {
                $this->db->Execute("DELETE FROM dns_zone_records WHERE zone_id=? AND issystem='0'", array($this->id));

                foreach ($this->records as $record) {
                    //UNIQUE KEY `zoneid` (`zone_id`,`type`(1),`value`,`name`)
                    $this->db->Execute("
                        INSERT INTO dns_zone_records
                        SET `zone_id` = ?,
                            `type` = ?,
                            `value` = ?,
                            `name` = ?,
                            `issystem` = '0',
                            `ttl` = ?,
                            `priority` = ?,
                            `weight` = ?,
                            `port` = ?
                        ON DUPLICATE KEY UPDATE
                            `issystem` = '0',
                            `ttl` = ?,
                            `priority` = ?,
                            `weight` = ?,
                            `port` = ?
                    ", array(
                        $this->id,
                        $record['type'],
                        $record['value'],
                        $record['name'],

                        (int)$record['ttl'],
                        (int)$record['priority'],
                        (int)$record['weight'],
                        (int)$record['port'],

                        (int)$record['ttl'],
                        (int)$record['priority'],
                        (int)$record['weight'],
                        (int)$record['port'],
                    ));
                }
            }
        } catch (Exception $e) {

            $this->db->RollbackTrans();
            throw new Exception ("Cannot save DBDNS zone. Error: " . $e->getMessage(), $e->getCode());
        }

        $this->db->CommitTrans();
    }
}
