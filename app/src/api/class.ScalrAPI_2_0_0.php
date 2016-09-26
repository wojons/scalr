<?php

use Scalr\Acl\Acl;
use Scalr\LogCollector\AuditLogger;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;
use Scalr\Model\Entity;
use Scalr\Model\Entity\Role;

class ScalrAPI_2_0_0 extends ScalrAPICore
{

    private $validObjectTypes = array('role','server','farm');

    private $validWatcherNames = array('CPU','MEM','LA','NET');

    private $validGraphTypes = array('daily','weekly','monthly','yearly');

    public function DNSZonesList()
    {
        $this->restrictAccess(Acl::RESOURCE_DNS_ZONES);

        if (!Scalr::config('scalr.dns.global.enabled'))
            throw new Exception("DNS functionality is not enabled. Please contact your Scalr administrator.");

        $response = $this->CreateInitialResponse();
        $response->DNSZoneSet = new stdClass();
        $response->DNSZoneSet->Item = array();

        $rows = $this->DB->Execute("SELECT * FROM dns_zones WHERE env_id=?", array($this->Environment->id));
        while ($row = $rows->FetchRow()) {
            $itm = new stdClass();
            $itm->ZoneName = $row['zone_name'];
            $itm->FarmID = $row['farm_id'];
            $itm->FarmRoleID = $row['farm_roleid'];
            $itm->Status = $row['status'];
            $itm->LastModifiedAt = $row['dtlastmodified'];
            $itm->IPSet = new stdClass();
            $itm->IPSet->Item = array();
            if ($row['status'] == DNS_ZONE_STATUS::ACTIVE) {
                $ips = $this->DB->GetAll("SELECT value FROM dns_zone_records WHERE zone_id=? AND `type`=? AND `name` IN ('', '@', '{$row['zone_name']}.')",
                    array($row['id'], 'A')
                );
                foreach ($ips as $ip) {
                    $itm_ip = new stdClass();
                    $itm_ip->IPAddress = $ip['value'];
                    $itm->IPSet->Item[] = $itm_ip;
                }
            }

            $response->DNSZoneSet->Item[] = $itm;
        }

        return $response;
    }

    public function DNSZoneRecordAdd($ZoneName, $Type, $TTL, $Name, $Value, $Priority = 0, $Weight = 0, $Port = 0)
    {
        $this->restrictAccess(Acl::RESOURCE_DNS_ZONES, Acl::PERM_DNS_ZONES_MANAGE);

        if (!Scalr::config('scalr.dns.global.enabled'))
            throw new Exception("DNS functionality is not enabled. Please contact your Scalr administrator.");

        $zoneinfo = $this->DB->GetRow("SELECT id, env_id FROM dns_zones WHERE zone_name=? LIMIT 1", array($ZoneName));

        if (!$zoneinfo || $zoneinfo['env_id'] != $this->Environment->id)
            throw new Exception (sprintf("Zone '%s' not found in database", $ZoneName));

        if (!in_array($Type, array("A", "MX", "CNAME", "NS", "TXT", "SRV")))
            throw new Exception (sprintf("Unknown record type '%s'", $Type));

        $record = array(
            'name'		=> $Name,
            'value'		=> $Value,
            'type'		=> $Type,
            'ttl'		=> $TTL,
            'priority'	=> $Priority,
            'weight'	=> $Weight,
            'port'		=> $Port
        );

        $recordsValidation = Scalr_Net_Dns_Zone::validateRecords(array(
            $record
        ));

        if ($recordsValidation === true) {
            $DBDNSZone = DBDNSZone::loadById($zoneinfo['id']);

            $records = $DBDNSZone->getRecords(false);
            array_push($records, $record);

            $DBDNSZone->setRecords($records);

            $DBDNSZone->save(false);
        } else {
            throw new Exception($recordsValidation[0]);
        }

        $response = $this->CreateInitialResponse();
        $response->Result = 1;

        return $response;
    }

    public function DNSZoneRecordRemove($ZoneName, $RecordID)
    {
        $this->restrictAccess(Acl::RESOURCE_DNS_ZONES, Acl::PERM_DNS_ZONES_MANAGE);

        if (!Scalr::config('scalr.dns.global.enabled'))
            throw new Exception("DNS functionality is not enabled. Please contact your Scalr administrator.");

        $zoneinfo = $this->DB->GetRow("SELECT id, env_id, allow_manage_system_records FROM dns_zones WHERE zone_name=? LIMIT 1", array($ZoneName));
        if (!$zoneinfo || $zoneinfo['env_id'] != $this->Environment->id)
            throw new Exception (sprintf("Zone '%s' not found in database", $ZoneName));

        $record_info = $this->DB->GetRow("SELECT * FROM dns_zone_records WHERE zone_id=? AND id=?", array($zoneinfo['id'], $RecordID));
        if (!$record_info)
            throw new Exception (sprintf("Record ID '%s' for zone '%s' not found in database", $RecordID, $ZoneName));

        if ($record_info['issystem'] == 1 && !$zoneinfo['allow_manage_system_records'])
            throw new Exception (sprintf("Record ID '%s' is system record and cannot be removed"));

        $response = $this->CreateInitialResponse();

        $this->DB->Execute("DELETE FROM dns_zone_records WHERE id=?", array($RecordID));

        $response->Result = 1;

        return $response;
    }

    public function DNSZoneRecordsList($ZoneName)
    {
        $this->restrictAccess(Acl::RESOURCE_DNS_ZONES);

        if (!Scalr::config('scalr.dns.global.enabled'))
            throw new Exception("DNS functionality is not enabled. Please contact your Scalr administrator.");

        $zoneinfo = $this->DB->GetRow("SELECT id, env_id FROM dns_zones WHERE zone_name=? LIMIT 1", array($ZoneName));
        if (!$zoneinfo || $zoneinfo['env_id'] != $this->Environment->id)
            throw new Exception (sprintf("Zone '%s' not found in database", $ZoneName));

        $response = $this->CreateInitialResponse();

        $response->ZoneRecordSet = new stdClass();
        $response->ZoneRecordSet->Item = array();

        $records = $this->DB->GetAll("SELECT * FROM dns_zone_records WHERE zone_id=?", array($zoneinfo['id']));
        foreach ($records as $record) {
            $itm = new stdClass();
            $itm->ID = $record['id'];
            $itm->Type = $record['type'];
            $itm->TTL = $record['ttl'];
            $itm->Priority = $record['priority'];
            $itm->Name = $record['name'];
            $itm->Value = $record['value'];
            $itm->Weight = $record['weight'];
            $itm->Port = $record['port'];
            $itm->IsSystem = $record['issystem'];

            $response->ZoneRecordSet->Item[] = $itm;
        }

        return $response;
    }

    public function DNSZoneCreate($DomainName, $FarmID = null, $FarmRoleID = null)
    {
        $this->restrictAccess(Acl::RESOURCE_DNS_ZONES, Acl::PERM_DNS_ZONES_MANAGE);

        if (!Scalr::config('scalr.dns.global.enabled')) {
            throw new Exception("DNS functionality is not enabled. Please contact your Scalr administrator.");
        }

        $records = [];

        $DomainName = trim($DomainName);

        $Validator = new Scalr_Validator();
        if ($Validator->validateDomain($DomainName) !== true)
            throw new Exception(_("Invalid domain name"));

        $domain_chunks = explode(".", $DomainName);
        $chk_dmn = '';

        while (count($domain_chunks) > 0) {
            $chk_dmn = trim(array_pop($domain_chunks).".{$chk_dmn}", ".");
            if ($this->DB->GetOne("SELECT id FROM dns_zones WHERE zone_name=? AND client_id != ? LIMIT 1", array(
                $chk_dmn,
                $this->user->getAccountId()
            ))) {
                if ($chk_dmn == $DomainName)
                    throw new Exception(sprintf(_("%s already exists on scalr nameservers"), $DomainName));
                else
                    throw new Exception(sprintf(_("You cannot use %s domain name because top level domain %s does not belong to you"), $DomainName, $chk_dmn));
            }
        }

        if ($FarmID) {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id)
                throw new Exception(sprintf("Farm #%s not found", $FarmID));

            $this->user->getPermissions()->validate($DBFarm);
        }

        if ($FarmRoleID) {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            if ($DBFarm->ID != $DBFarmRole->FarmID)
                throw new Exception(sprintf("FarmRole #%s not found on Farm #%s", $FarmRoleID, $FarmID));
        }

        $response = $this->CreateInitialResponse();

        $DBDNSZone = DBDNSZone::create(
            $DomainName,
            14400,
            86400,
            str_replace('@', '.', $this->user->getEmail())
        );

        $DBDNSZone->farmRoleId = (int)$FarmRoleID;
        $DBDNSZone->farmId = (int)$FarmID;
        $DBDNSZone->clientId = $this->user->getAccountId();
        $DBDNSZone->envId = $this->Environment->id;

        $def_records = $this->DB->GetAll("SELECT * FROM default_records WHERE clientid=?", array($this->user->getAccountId()));

        foreach ($def_records as $record) {
            $record["name"] = str_replace(array("%hostname%", "%zonename%"), array("{$DomainName}.", "{$DomainName}."), $record["name"]);
            $record["value"] = str_replace(array("%hostname%", "%zonename%"), array("{$DomainName}.", "{$DomainName}."), $record["value"]);
            $records[] = $record;
        }

        $nameservers = Scalr::config('scalr.dns.global.nameservers');

        foreach ($nameservers as $ns) {
            $records[] = array("id" => "c".rand(10000, 999999), "type" => "NS", "ttl" => 14400, "value" => "{$ns}.", "name" => "{$DomainName}.", "issystem" => 0);
        }

        $DBDNSZone->setRecords($records);

        $DBDNSZone->save(true);

        $response->Result = 1;

        return $response;
    }


    public function FarmTerminate($FarmID, $KeepEBS, $KeepEIP, $KeepDNSZone)
    {
        $response = $this->CreateInitialResponse();

        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $this->user->getPermissions()->validate($DBFarm);
        $this->restrictFarmAccess($DBFarm, Acl::PERM_FARMS_LAUNCH_TERMINATE);

        if ($DBFarm->Status != FARM_STATUS::RUNNING) {
            throw new Exception(sprintf("Farm already terminated", $FarmID));
        }

        Scalr::FireEvent($FarmID, new FarmTerminatedEvent(
            $KeepDNSZone ? 0 : 1,
            $KeepEIP ? 1 : 0,
            true,
            $KeepEBS ? 1 : 0,
            true,
            $this->user->id
        ));

        $response->Result = true;

        return $response;
    }

    public function FarmLaunch($FarmID)
    {
        $response = $this->CreateInitialResponse();

        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) {
                throw new Exception("N");
            }
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $this->user->getPermissions()->validate($DBFarm);
        $this->restrictFarmAccess($DBFarm, Acl::PERM_FARMS_LAUNCH_TERMINATE);
        $DBFarm->isLocked();

        if ($DBFarm->Status == FARM_STATUS::RUNNING) {
            throw new Exception(sprintf("Farm already running", $FarmID));
        }

        Scalr::FireEvent($FarmID, new FarmLaunchedEvent(true, $this->user->id));

        $response->Result = true;

        return $response;
    }

    public function FarmGetStats($FarmID, $Date = null)
    {
        throw new Exception(sprintf("FarmGetStats method is deprecated and no longer supported"));
    }

    public function FarmsList()
    {
        $this->restrictFarmAccess();

        $response = $this->CreateInitialResponse();
        $response->FarmSet = new stdClass();
        $response->FarmSet->Item = array();

        $options = array($this->Environment->id);
        $stmt = "SELECT id, name, status, comments FROM farms f WHERE env_id = ? AND " . $this->getFarmSqlQuery();

        $farms = $this->DB->Execute($stmt, $options);
        while ($farm = $farms->FetchRow()) {
            $itm = new stdClass();
            $itm->ID = $farm['id'];
            $itm->Name = $farm['name'];
            $itm->Status = $farm['status'];
            $itm->Comments = $farm['comments'];

            $response->FarmSet->Item[] = $itm;
        }

        return $response;
    }

    public function FarmGetDetails($FarmID)
    {
        $response = $this->CreateInitialResponse();

        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $this->user->getPermissions()->validate($DBFarm);

        $response->Farm = new stdClass();
        $response->Farm->ID = $DBFarm->ID;
        $response->Farm->Status = $DBFarm->Status;

        $response->FarmRoleSet = new stdClass();
        $response->FarmRoleSet->Item = array();

        foreach ($DBFarm->GetFarmRoles() as $DBFarmRole) {
            $itm = new stdClass();
            $itm->ID = $DBFarmRole->ID;
            $itm->RoleID = $DBFarmRole->RoleID;
            $itm->Name = $DBFarmRole->GetRoleObject()->name;
            $itm->Alias = $DBFarmRole->Alias;
            $itm->Platform = $DBFarmRole->Platform;
            $itm->Category = $DBFarmRole->GetRoleObject()->getCategoryName();
            $itm->ScalingProperties = new stdClass();
            $itm->ScalingProperties->MinInstances = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);
            $itm->ScalingProperties->MaxInstances = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES);

            if ($DBFarmRole->Platform == SERVER_PLATFORMS::EC2) {
                $itm->PlatformProperties = new stdClass();
                $itm->PlatformProperties->InstanceType = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::INSTANCE_TYPE);
                $itm->PlatformProperties->AvailabilityZone = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_AVAIL_ZONE);
            }

            if ($DBFarmRole->Platform == SERVER_PLATFORMS::OPENSTACK) {
                $itm->PlatformProperties = new stdClass();
                $itm->PlatformProperties->FlavorID = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::INSTANCE_TYPE);
                $itm->PlatformProperties->FloatingIPPool = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::OPENSTACK_IP_POOL);
            }

            $itm->ServerSet = new stdClass();
            $itm->ServerSet->Item = array();
            foreach ($DBFarmRole->GetServersByFilter() as $DBServer) {
                $iitm = new stdClass();
                $iitm->ServerID = $DBServer->serverId;
                $iitm->ExternalIP = $DBServer->remoteIp;
                $iitm->InternalIP = $DBServer->localIp;

                $iitm->PublicIP = $DBServer->remoteIp;
                $iitm->PrivateIP = $DBServer->localIp;
                $iitm->CloudLocation = $DBServer->cloudLocation;
                $iitm->CloudLocationZone = $DBServer->cloudLocationZone;

                $iitm->Status = $DBServer->status;

                $iitm->IsInitFailed = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED);
                $iitm->IsRebooting = $DBServer->GetProperty(SERVER_PROPERTIES::REBOOTING);

                $iitm->Index = $DBServer->index;
                $iitm->ScalarizrVersion = $DBServer->GetProperty(SERVER_PROPERTIES::SZR_VESION);
                $iitm->Uptime = round((time()-strtotime($DBServer->dateAdded))/60, 2); //seconds -> minutes

                $iitm->IsDbMaster = ($DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER) == 1 || $DBServer->GetProperty(Scalr_Db_Msr::REPLICATION_MASTER) == 1) ? '1' : '0';

                if ($DBFarmRole->Platform == SERVER_PLATFORMS::EC2) {
                    $iitm->PlatformProperties = new stdClass();
                    $iitm->PlatformProperties->InstanceType = $DBServer->getType();
                    $iitm->PlatformProperties->AvailabilityZone = $DBServer->cloudLocationZone;
                    $iitm->PlatformProperties->AMIID = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::AMIID);
                    $iitm->PlatformProperties->InstanceID = $DBServer->GetProperty(EC2_SERVER_PROPERTIES::INSTANCE_ID);
                }

                if ($DBFarmRole->Platform == SERVER_PLATFORMS::OPENSTACK) {
                    $iitm->PlatformProperties = new stdClass();
                    $iitm->PlatformProperties->FlavorID = $DBServer->getType();
                    $iitm->PlatformProperties->ServerID = $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::SERVER_ID);
                    $iitm->PlatformProperties->ImageID = $DBServer->GetProperty(OPENSTACK_SERVER_PROPERTIES::IMAGE_ID);
                }

                $itm->ServerSet->Item[] = $iitm;
            }

            $response->FarmRoleSet->Item[] = $itm;
        }

        return $response;
    }

    public function EventsList($FarmID, $StartFrom = 0, $RecordsLimit = 20)
    {
        $this->restrictFarmAccess();

        $stmt = "SELECT id FROM farms f WHERE id=? AND env_id=? AND " . $this->getFarmSqlQuery();
        $args = array($FarmID, $this->Environment->id);
        $farminfo = $this->DB->GetRow($stmt, $args);
        if (!$farminfo)
            throw new Exception(sprintf("Farm #%s not found", $FarmID));

        $sql = "SELECT * FROM events WHERE farmid='{$FarmID}'";

        $total = $this->DB->GetOne(preg_replace('/\*/', 'COUNT(*)', $sql, 1));

        $sql .= " ORDER BY id DESC";

        $start = $StartFrom ? (int) $StartFrom : 0;
        $limit = $RecordsLimit ? (int) $RecordsLimit : 20;
        $sql .= " LIMIT {$start}, {$limit}";

        $response = $this->CreateInitialResponse();
        $response->TotalRecords = $total;
        $response->StartFrom = $start;
        $response->RecordsLimit = $limit;
        $response->EventSet = new stdClass();
        $response->EventSet->Item = array();

        $rows = $this->DB->Execute($sql);
        while ($row = $rows->FetchRow()) {
            $itm = new stdClass();
            $itm->ID = $row['event_id'];
            $itm->Type = $row['type'];
            $itm->Timestamp = strtotime($row['dtadded']);
            $itm->Message = $row['short_message'];

            $response->EventSet->Item[] = $itm;
        }

        return $response;
    }

    public function LogsList($FarmID, $ServerID = null, $StartFrom = 0, $RecordsLimit = 20)
    {
        $this->restrictAccess(Acl::RESOURCE_LOGS_SYSTEM_LOGS);

        $stmt = "SELECT clientid FROM farms f WHERE id=? AND env_id=? AND " . $this->getFarmSqlQuery();
        $args = array($FarmID, $this->Environment->id);
        $farminfo = $this->DB->GetRow($stmt, $args);

        if (!$farminfo)
            throw new Exception(sprintf("Farm #%s not found", $FarmID));

        $sql = "SELECT * FROM logentries WHERE farmid='{$FarmID}'";
        if ($ServerID)
            $sql .= " AND serverid=".$this->DB->qstr($ServerID);

        $total = $this->DB->GetOne(preg_replace('/\*/', 'COUNT(*)', $sql, 1));

        $sql .= " ORDER BY time DESC";

        $start = $StartFrom ? (int) $StartFrom : 0;
        $limit = $RecordsLimit ? (int) $RecordsLimit : 20;
        $sql .= " LIMIT {$start}, {$limit}";

        $response = $this->CreateInitialResponse();
        $response->TotalRecords = $total;
        $response->StartFrom = $start;
        $response->RecordsLimit = $limit;
        $response->LogSet = new stdClass();
        $response->LogSet->Item = array();

        $rows = $this->DB->Execute($sql);
        while ($row = $rows->FetchRow()) {
            $itm = new stdClass();
            $itm->ServerID = $row['serverid'];
            $itm->Message = $row['message'];
            $itm->Severity = $row['severity'];
            $itm->Timestamp = $row['time'];
            $itm->Source = $row['source'];
            $itm->Count = $row['cnt'];

            $response->LogSet->Item[] = $itm;
        }

        return $response;
    }

    public function ScriptGetDetails($ScriptID, $ShowContent = false, $Revision = false)
    {
        $this->restrictAccess(Acl::RESOURCE_SCRIPTS_ENVIRONMENT);

        /* @var $script Script */
        $script = Script::findPk($ScriptID);

        if (! $script)
            throw new Exception(sprintf("Script ID: %s not found in our database (1)", $ScriptID));

        if ($script->accountId && $this->user->getAccountId() != $script->accountId)
            throw new Exception(sprintf("Script ID: %s not found in our database (2)", $ScriptID));

        $response = $this->CreateInitialResponse();

        $response->ScriptID = $ScriptID;
        $response->ScriptRevisionSet = new stdClass();
        $response->ScriptRevisionSet->Item = array();
        $response->RevisionsNum = 0;

        foreach (array_reverse($script->getVersions()->getArrayCopy()) as $rev ) {
            /* @var $rev ScriptVersion */
            $response->RevisionsNum++;
            if ($response->RevisionsNum >= 10)
                continue;

            if ($Revision && $rev->version != $Revision)
                continue;

            $itm = new stdClass();
            $itm->Revision = $rev->version;
            $itm->Date = $rev->dtCreated->format('Y-m-d H:i:s');

            if ($ShowContent) {
                $itm->Content = base64_encode($rev->content);
            }

            $itm->ConfigVariables = new stdClass();
            $itm->ConfigVariables->Item = array();

            $text = preg_replace('/(\\\%)/si', '$$scalr$$', $rev->content);
            preg_match_all("/\%([^\%\s]+)\%/si", $text, $matches);
            $vars = $matches[1];
            $data = array();
            foreach ($vars as $var) {
                if (!in_array($var, array_keys(Scalr_Scripting_Manager::getScriptingBuiltinVariables()))) {
                    $ditm = new stdClass;
                    $ditm->Name = $var;
                    $itm->ConfigVariables->Item[] = $ditm;
                }
            }

            $response->ScriptRevisionSet->Item[] = $itm;
        }

        return $response;
    }

    public function ScriptExecute($ScriptID, $Timeout, $Async, $FarmID, $FarmRoleID = null, $ServerID = null, $Revision = null, array $ConfigVariables = null)
    {
        $this->restrictAccess(Acl::RESOURCE_SCRIPTS_ENVIRONMENT, Acl::PERM_SCRIPTS_ENVIRONMENT_EXECUTE);

        $response = $this->CreateInitialResponse();

        $stmt = "SELECT * FROM farms f WHERE id=? AND env_id=? AND " . $this->getFarmSqlQuery();
        $args = array($FarmID, $this->Environment->id);
        $farminfo = $this->DB->GetRow($stmt, $args);

        if (!$farminfo)
            throw new Exception(sprintf("Farm #%s not found", $FarmID));

        if ($FarmRoleID) {
            $dbFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            if ($dbFarmRole->FarmID != $FarmID)
                throw new Exception(sprintf("FarmRole #%s not found on farm #%s", $FarmRoleID, $FarmID));
        }


        if (!$Revision)
            $Revision = 'latest';

        if ($ServerID && !$FarmRoleID) {
            $DBServer = DBServer::LoadByID($ServerID);
            $FarmRoleID = $DBServer->farmRoleId;
        }

        $config = $ConfigVariables;
        $scriptid = (int)$ScriptID;
        if ($ServerID)
            $target = Script::TARGET_INSTANCE;
        else if ($FarmRoleID)
            $target = Script::TARGET_ROLE;
        else
            $target = Script::TARGET_FARM;
        $event_name = 'APIEvent-'.date("YmdHi").'-'.rand(1000,9999);
        $version = $Revision;
        $farmid = (int)$FarmID;
        $timeout = (int)$Timeout;
        $issync = ($Async == 1) ? 0 : 1;

        $scriptSettings = array(
            'version'  => $version,
            'scriptid' => $scriptid,
            'timeout'  => $timeout,
            'issync'   => $issync,
            'params'   => serialize($config),
            'type'     => Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_SCALR
        );

        switch ($target) {
            case Script::TARGET_FARM:
                $servers = $this->DB->GetAll("SELECT server_id FROM servers WHERE is_scalarized = 1 AND status IN (?,?) AND farm_id=?",
                    array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $farmid)
                );

                break;

            case Script::TARGET_ROLE:
                $servers = $this->DB->GetAll("SELECT server_id FROM servers WHERE is_scalarized = 1 AND status IN (?,?) AND farm_roleid=?",
                    array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $FarmRoleID)
                );

                break;

            case Script::TARGET_INSTANCE:
                $servers = $this->DB->GetAll("SELECT server_id FROM servers WHERE is_scalarized = 1 AND status IN (?,?) AND server_id=? AND farm_id=?",
                    array(SERVER_STATUS::INIT, SERVER_STATUS::RUNNING, $DBServer->serverId, $farmid)
                );

                break;
        }

        // send message to start executing task (starts script)
        if (count($servers) > 0) {
            foreach ($servers as $server) {
                $DBServer = DBServer::LoadByID($server['server_id']);

                $msg = new Scalr_Messaging_Msg_ExecScript("Executed via API");
                $msg->eventId = $response->TransactionID;
                $msg->setServerMetaData($DBServer);

                $script = Scalr_Scripting_Manager::prepareScript($scriptSettings, $DBServer);

                if ($script) {
                    $DBServer->executeScript($script, $msg);
                    $this->auditLog('script.execute', $script, $DBServer);
                }
            }
        }

        $response->Result = true;

        return $response;
    }

    public function RolesList($Platform = null, $ImageID = null, $Name = null, $Prefix = null)
    {
        $this->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT);

        $response = $this->CreateInitialResponse();
        $response->RoleSet = new stdClass();
        $response->RoleSet->Item = array();

        $sql = "SELECT roles.*, os.family as os_family, os.version as os_version, os.generation as os_generation
                  FROM roles
                  INNER JOIN os ON os.id = roles.os_id
                  LEFT JOIN role_environments re ON re.role_id = roles.id
                  WHERE (roles.client_id IS NULL OR roles.client_id = '{$this->Environment->clientId}' AND roles.env_id IS NULL AND (re.env_id IS NULL OR re.env_id = '{$this->Environment->id}') OR roles.env_id='{$this->Environment->id}')";

        if ($ImageID)
            $sql .= " AND roles.id IN (SELECT role_id FROM role_images WHERE image_id = {$this->DB->qstr($ImageID)})";

        if ($Name)
            $sql .= " AND roles.name = {$this->DB->qstr($Name)}";

        if ($Prefix)
            $sql .= " AND roles.name LIKE{$this->DB->qstr("%{$Prefix}%")}";

        if ($Platform)
            $sql .= " AND roles.id IN (SELECT role_id FROM role_images WHERE platform = {$this->DB->qstr($Platform)})";

        $rows = $this->DB->Execute($sql);
        while ($row = $rows->FetchRow()) {
            if ($row['client_id'] == 0)
                $row["client_name"] = "Scalr";
            else
                $row["client_name"] = $this->DB->GetOne("SELECT fullname FROM clients WHERE id='{$row['client_id']}' LIMIT 1");

            if (!$row["client_name"])
                $row["client_name"] = "";


            $itm = new stdClass();
            $itm->ID = $row['id'];
            $itm->Name = $row['name'];
            $itm->Owner = $row["client_name"];
            $itm->Architecture = $row['architecture'];
            $itm->OsFamily = $row['os_family'];
            $itm->OsVersion = $row['os_version'];
            $itm->OsGeneration = $row['os_generation'];
            $itm->OsID = $row['os_id'];
            $itm->CreatedAt = $row['dtadded'];
            $itm->CreatedBy = $row['added_by_email'];

            $itm->ImageSet = new stdClass();
            $itm->ImageSet->Item = array();

            $images = $this->DB->Execute("SELECT * FROM role_images WHERE role_id = ?", array($row['id']));
            while ($image = $images->FetchRow()) {
                $iitm = new stdClass();
                $iitm->ID = $image['image_id'];
                $iitm->Platform = $image['platform'];
                $iitm->CloudLocation = $image['cloud_location'];

                $itm->ImageSet->Item[] = $iitm;
            }

            $response->RoleSet->Item[] = $itm;
        }

        return $response;
    }

    public function ScriptsList()
    {
        $this->restrictAccess(Acl::RESOURCE_SCRIPTS_ENVIRONMENT);

        $response = $this->CreateInitialResponse();
        $response->ScriptSet = new stdClass();
        $response->ScriptSet->Item = array();

        foreach (Script::find([
            '$or' => [
                ['accountId' => null],
                ['accountId' => $this->user->getAccountId()]
            ]
        ]) as $script) {
            /* @var $script Script */
            $itm = new stdClass();
            $itm->ID = $script->id;
            $itm->Name = $script->name;
            $itm->Description = $script->description;
            $itm->LatestRevision = $script->getLatestVersion()->version;

            $response->ScriptSet->Item[] = $itm;
        }

        return $response;
    }

    public function Hello()
    {
        $response = $this->CreateInitialResponse();
        $response->Result = 1;
        return $response;
    }

    public function StatisticsGetGraphURL($ObjectType, $ObjectID, $WatcherName, $GraphType)
    {
        if (!in_array($ObjectType, $this->validObjectTypes))
            throw new Exception('Incorrect value of object type. Valid values are: role, server and farm');

        if (!in_array($WatcherName, $this->validWatcherNames))
            throw new Exception('Incorrect value of watcher name. Valid values are: CPU, MEM, LA and NET');

        if (!in_array($GraphType, $this->validGraphTypes))
            throw new Exception('Incorrect value of graph type. Valid values are: daily, weekly, monthly and yearly');

        $metricsList = array(
            'CPU' => 'cpu',
            'LA' => 'la',
            'NET' => 'net',
            'MEM' => 'mem'
        );
        $metric = $metricsList[$WatcherName];
        $data = array(
            'metrics' => $metric,
            'period' => $GraphType
        );

        try {
            switch ($ObjectType) {
                case 'role':
                    $DBFarmRole = DBFarmRole::LoadByID($ObjectID);
                    $DBFarm = $DBFarmRole->GetFarmObject();
                    $this->user->getPermissions()->validate($DBFarm);
                    $data['farmId'] = $DBFarm->ID;
                    $data['farmRoleId'] = $DBFarmRole->ID;
                    break;

                case 'server':
                    $DBServer = DBServer::LoadByID($ObjectID);
                    $DBFarm = $DBServer->GetFarmObject();
                    $this->user->getPermissions()->validate($DBFarm);
                    $data['farmId'] = $DBFarm->ID;
                    $data['farmRoleId'] = $DBServer->farmRoleId;
                    $data['index'] = $DBServer->index;
                    break;

                case 'farm':
                    $DBFarm = DBFarm::LoadByID($ObjectID);
                    $this->user->getPermissions()->validate($DBFarm);
                    $data['farmId'] = $DBFarm->ID;
                    break;
            }
        } catch (Exception $e) {
            throw new Exception("Object #{$ObjectID} not found in database");
        }

        if ($DBFarm->EnvID != $this->Environment->id)
            throw new Exception("Object #{$ObjectID} not found in database");

        $data['hash'] = $DBFarm->Hash;

        $response = $this->CreateInitialResponse();
        $conf = \Scalr::config('scalr.load_statistics.connections.plotter');
        $content = @file_get_contents(
            "{$conf['scheme']}://{$conf['host']}:{$conf['port']}/load_statistics?" . http_build_query($data)
        );
        $r = @json_decode($content, true);
        if ($r['success']) {
            if ($r['metric'][$metric] && $r['metric'][$metric]['success']) {
                $response->GraphURL = $r['metric'][$metric]['img'];
            } else {
                throw new Exception($r['metric'][$metric]['msg']);
            }
        } else {
            if ($r['msg']) {
                throw new Exception($r['msg']);
            } else {
                throw new Exception("Internal API error");
            }
        }

        return $response;
    }


    public function BundleTaskGetStatus($BundleTaskID)
    {
        $this->isAllowed(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_BUNDLETASKS);

        $BundleTask = BundleTask::LoadById($BundleTaskID);
        if ($BundleTask->envId != $this->Environment->id)
            throw new Exception(sprintf("Bundle task #%s not found", $BundleTaskID));

        if (!empty($BundleTask->farmId)) {
            $DBFarm = DBFarm::LoadByID($BundleTask->farmId);
            if ($DBFarm) {
                $this->user->getPermissions()->validate($DBFarm);
            }
        }

        $response = $this->CreateInitialResponse();
        $response->BundleTaskStatus = $BundleTask->status;

        if ($BundleTask->status == SERVER_SNAPSHOT_CREATION_STATUS::FAILED)
            $response->FailureReason = $BundleTask->failureReason;

        return $response;
    }

    public function ServerImageCreate($ServerID, $RoleName)
    {
        $this->restrictAccess(Acl::RESOURCE_IMAGES_ENVIRONMENT, Acl::PERM_IMAGES_ENVIRONMENT_MANAGE);
        $this->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT, Acl::PERM_ROLES_ENVIRONMENT_MANAGE);

        $DBServer = DBServer::LoadByID($ServerID);

        // Validate client and server
        if ($DBServer->envId != $this->Environment->id)
            throw new Exception(sprintf("Server ID #%s not found", $ServerID));

        $this->user->getPermissions()->validate($DBServer);

        //Check for already running bundle on selected instance
        $chk = $this->DB->GetOne("SELECT id FROM bundle_tasks WHERE server_id=? AND status NOT IN ('success', 'failed') LIMIT 1",
            array($ServerID)
        );

        if ($chk) {
            throw new Exception(sprintf(_("Server '%s' is already synchonizing."), $ServerID));
        }

        if (!Role::isValidName($RoleName)) {
            throw new Exception(_("Role name is incorrect"));
        }

        if (Role::isNameUsed($RoleName, $this->user->getAccountId(), $this->Environment->id)) {
            throw new Exception("Specified role name is already used by another role");
        }

        if (($btId = BundleTask::getActiveTaskIdByName($RoleName, $this->user->getAccountId(), $this->Environment->id))) {
            throw new Exception(sprintf("Specified role name is already reserved for BundleTask with ID: %d.", $btId));
        }

        $ServerSnapshotCreateInfo = new ServerSnapshotCreateInfo($DBServer, $RoleName, SERVER_REPLACEMENT_TYPE::NO_REPLACE, BundleTask::BUNDLETASK_OBJECT_ROLE, 'Bundled via API');
        $BundleTask = BundleTask::Create($ServerSnapshotCreateInfo);

        $BundleTask->createdById = $this->user->id;
        $BundleTask->createdByEmail = $this->user->getEmail();

        $BundleTask->save();

        $response = $this->CreateInitialResponse();

        $response->BundleTaskID = $BundleTask->id;

        return $response;
    }

    public function ServerReboot($ServerID)
    {
        $DBServer = DBServer::LoadByID($ServerID);
        if ($DBServer->envId != $this->Environment->id)
            throw new Exception(sprintf("Server ID #%s not found", $ServerID));

        $this->user->getPermissions()->validate($DBServer);

        $response = $this->CreateInitialResponse();

        PlatformFactory::NewPlatform($DBServer->platform)->RebootServer($DBServer, false);

        $response->Result = true;

        return $response;
    }

    public function ServerLaunch($FarmRoleID, $IncreaseMaxInstances = false)
    {
        try {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            $DBFarm = DBFarm::LoadByID($DBFarmRole->FarmID);
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm Role ID #%s not found", $FarmRoleID));
        }

        if ($DBFarm->EnvID != $this->Environment->id)
            throw new Exception(sprintf("Farm Role ID #%s not found", $FarmRoleID));

        if ($DBFarm->Status != FARM_STATUS::RUNNING)
            throw new Exception(sprintf("Farm ID #%s is not running", $DBFarm->ID));

        $this->user->getPermissions()->validate($DBFarm);
        $this->restrictFarmAccess($DBFarm, Acl::PERM_FARMS_SERVERS);

        $isSzr = true;

        $n = $DBFarmRole->GetPendingInstancesCount();
        if ($n >= 5 && !$isSzr)
            throw new Exception("There are {$n} pending instances. You cannot launch new instances while you have 5 pending ones.");

        $response = $this->CreateInitialResponse();

        $max_instances = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES);
        $min_instances = $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES);

        if ($IncreaseMaxInstances) {
            if ($max_instances < $min_instances+1)
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES, $max_instances+1, Entity\FarmRoleSetting::TYPE_CFG);
        }
        if ($DBFarmRole->GetRunningInstancesCount() + $DBFarmRole->GetPendingInstancesCount() >= $max_instances) {
            if ($IncreaseMaxInstances)
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MAX_INSTANCES, $max_instances+1, Entity\FarmRoleSetting::TYPE_CFG);
            else
                throw new Exception("Max instances limit reached. Use 'IncreaseMaxInstances' parameter or increase max isntances settings in UI");
        }

        if ($DBFarmRole->GetRunningInstancesCount() + $DBFarmRole->GetPendingInstancesCount() == $min_instances)
            $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES, $min_instances+1, Entity\FarmRoleSetting::TYPE_CFG);

        $ServerCreateInfo = new ServerCreateInfo($DBFarmRole->Platform, $DBFarmRole);
        try {
            $DBServer = Scalr::LaunchServer($ServerCreateInfo, null, false, DBServer::LAUNCH_REASON_MANUALLY_API, $this->user);

            \Scalr::getContainer()->logger(LOG_CATEGORY::FARM)->info(new FarmLogMessage( $DBServer, sprintf("Starting new instance (API). ServerID = %s.",
                !empty($DBServer->serverId) ? $DBServer->serverId : null
            )));

            $response->ServerID = $DBServer->serverId;
            $response->CloudServerID = $DBServer->GetCloudServerID();

        } catch (Exception $e) {
            \Scalr::getContainer()->logger(LOG_CATEGORY::API)->error($e->getMessage());
            throw $e;
        }

        return $response;
    }

    public function ServerTerminate($ServerID, $DecreaseMinInstancesSetting = false)
    {
        $DBServer = DBServer::LoadByID($ServerID);
        if ($DBServer->envId != $this->Environment->id) {
            throw new Exception(sprintf("Server ID #%s not found", $ServerID));
        }

        $this->user->getPermissions()->validate($DBServer);

        $response = $this->CreateInitialResponse();

        $DBServer->terminate(array(DBServer::TERMINATE_REASON_MANUALLY_API, $this->user->fullname), true, $this->user);

        if ($DecreaseMinInstancesSetting) {
            $DBFarmRole = $DBServer->GetFarmRoleObject();
            if ($DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES) > 1) {
                $DBFarmRole->SetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES,
                    $DBFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_MIN_INSTANCES)-1
                );
            }
        }

        $response->Result = true;

        return $response;
    }
}
