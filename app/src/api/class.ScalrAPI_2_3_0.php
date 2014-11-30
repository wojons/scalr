<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Exception\AnalyticsException;

class ScalrAPI_2_3_0 extends ScalrAPI_2_2_0
{
    public function FireCustomEvent($ServerID, $EventName, array $Params = array())
    {
        $this->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS);

        $isEventExist = $this->DB->GetOne("
            SELECT id FROM event_definitions
            WHERE name = ? AND env_id = ?
            LIMIT 1
        ", array(
            $EventName,
            $this->Environment->id
        ));

        $dbServer = DBServer::LoadByID($ServerID);
        if ($dbServer->envId != $this->Environment->id)
            throw new Exception(sprintf("Server ID #%s not found", $ServerID));

        if ($isEventExist) {
            $event = new CustomEvent($dbServer, $EventName, (array)$Params);
        } else
            throw new Exception(sprintf("Event %s is not defined", $EventName));

        Scalr::FireEvent($dbServer->farmId, $event);

        $response = $this->CreateInitialResponse();
        $response->EventID = $event->GetEventID();

        return $response;
    }

    public function FarmGetDetails($FarmID)
    {
        $response = parent::FarmGetDetails($FarmID);

        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id)
                throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $response->ID = $DBFarm->ID;
        $response->Name = $DBFarm->Name;
        $response->IsLocked = $DBFarm->GetSetting(DBFarm::SETTING_LOCK);
        if ($response->IsLocked == 1) {
            $response->LockComment = $DBFarm->GetSetting(DBFarm::SETTING_LOCK_COMMENT);
            try {
                $response->LockedBy = Scalr_Account_User::init()->loadById($DBFarm->GetSetting(DBFarm::SETTING_LOCK_BY))->fullname;
            } catch (Exception $e) {}
        }

        foreach ($response->FarmRoleSet->Item as &$item) {
            $dbFarmRole = DBFarmRole::LoadByID($item->ID);

            $item->IsScalingEnabled = $dbFarmRole->GetSetting(DBFarmRole::SETTING_SCALING_ENABLED);
            $item->{"ScalingAlgorithmSet"} = new stdClass();
            $item->{"ScalingAlgorithmSet"}->Item = array();

            $metrics = $this->DB->GetAll("SELECT metric_id, name, dtlastpolled FROM `farm_role_scaling_metrics`
                    INNER JOIN scaling_metrics ON scaling_metrics.id = farm_role_scaling_metrics.metric_id WHERE farm_roleid = ?", array(
                $item->ID
            ));
            foreach ($metrics as $metric) {
                $itm = new stdClass();
                $itm->MetricID =  $metric['id'];
                $itm->MetricName =  $metric['name'];
                $itm->DateLastPolled = $metric['dtlastpolled'];

                $item->{"ScalingAlgorithmSet"}->Item[] = $itm;
            }
        }

        return $response;
    }

    public function FarmGetDnsEndpoints($FarmID)
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS);

        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $this->user->getPermissions()->validate($DBFarm);

        $response = $this->CreateInitialResponse();

        $haveMysqlRole = (bool)$this->DB->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior IN (?,?,?)) AND farmid=? LIMIT 1",
            array(ROLE_BEHAVIORS::MYSQL, ROLE_BEHAVIORS::MYSQL2, ROLE_BEHAVIORS::PERCONA, $FarmID)
        );
        if ($haveMysqlRole) {
            $response->mysql = new stdClass();
            $response->mysql->master = new stdClass();
            $response->mysql->master->private = "int.master.mysql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->mysql->master->public = "ext.master.mysql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->mysql->slave = new stdClass();
            $response->mysql->slave->private = "int.slave.mysql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->mysql->slave->public = "ext.slave.mysql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
        }

        $havePgRole = (bool)$this->DB->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? LIMIT 1",
            array(ROLE_BEHAVIORS::POSTGRESQL, $FarmID)
        );
        if ($havePgRole) {
            $response->postgresql = new stdClass();
            $response->postgresql->master = new stdClass();
            $response->postgresql->master->private = "int.master.postgresql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->postgresql->master->public = "ext.master.postgresql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->postgresql->slave = new stdClass();
            $response->postgresql->slave->private = "int.slave.postgresql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->postgresql->slave->public = "ext.slave.postgresql.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
        }

        $haveRedisRole = (bool)$this->DB->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? LIMIT 1",
            array(ROLE_BEHAVIORS::REDIS, $FarmID)
        );
        if ($haveRedisRole) {
            $response->redis = new stdClass();
            $response->redis->master = new stdClass();
            $response->redis->master->private = "int.master.redis.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->redis->master->public = "ext.master.redis.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->redis->slave = new stdClass();
            $response->redis->slave->private = "int.slave.redis.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
            $response->redis->slave->public = "ext.slave.redis.{$DBFarm->Hash}." . \Scalr::config('scalr.dns.static.domain_name');
        }

        return $response;
    }

    public function FarmUpdateRole($FarmRoleID, $Alias = null, array $Configuration = array())
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        try {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            $dbFarm = DBFarm::LoadByID($DBFarmRole->FarmID);
            if ($dbFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));
        }

        $this->user->getPermissions()->validate($dbFarm);

        $dbFarm->isLocked(true);

        if ($Alias != null) {
            $Alias = $this->stripValue($Alias);
            if (strlen($Alias) < 4)
                throw new Exception("Role Alias should be longer than 4 characters");

            if (preg_match("/^[^A-Za-z0-9_-]+$/", $Alias))
                throw new Exception("Role Alias should has only 'A-Za-z0-9-_' characters");

            if ($Alias) {
                foreach ($dbFarm->GetFarmRoles() as $farmRole) {
                    if ($farmRole->Alias == $Alias)
                        throw new Exception("Selected alias is already used by another role in selected farm");
                }
            }

            $DBFarmRole->Alias = $Alias;
            $DBFarmRole->Save();
        }

        $this->validateFarmRoleConfiguration($Configuration);

        if ($Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP] == 1 && !$Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT])
            $Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT] = '_default';

        foreach ($Configuration as $k => $v)
            $DBFarmRole->SetSetting($k, $v, DBFarmRole::TYPE_CFG);


        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
    }

    private function validateFarmRoleConfiguration(array $config)
    {
        $allowedConfiguration = array(
            'scaling.enabled',
            'scaling.min_instances',
            'scaling.max_instances',
            'scaling.polling_interval',

            'system.timeouts.launch',
            'system.timeouts.reboot',

            'openstack.flavor-id',
            'openstack.ip-pool',
            'openstack.networks',
            'openstack.security_groups.list',

            'chef.bootstrap',
            'chef.server_id',
            'chef.environment',
            'chef.role_name',
            'chef.attributes',

            'dns.create_records',
            'dns.ext_record_alias',
            'dns.int_record_alias',

            'aws.availability_zone',
            'aws.instance_type',
            'aws.security_groups.list',
            'aws.iam_instance_profile_arn'
        );

        foreach ($config as $key => $value) {
            if (!in_array($key, $allowedConfiguration))
                throw new Exception(sprintf(
                    "Unknown configuration option '%s'",
                    $this->stripValue($key)
                ));
        }

        return true;
    }

    public function FarmAddRole($Alias, $FarmID, $RoleID, $Platform, $CloudLocation, array $Configuration = array())
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        try {
            $dbFarm = DBFarm::LoadByID($FarmID);
            if ($dbFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }
        $this->user->getPermissions()->validate($dbFarm);

        $dbFarm->isLocked(true);

        $dbRole = DBRole::loadById($RoleID);
        if ($dbRole->envId != 0)
            $this->user->getPermissions()->validate($dbRole);

        foreach ($dbRole->getBehaviors() as $behavior) {
            if ($behavior != ROLE_BEHAVIORS::BASE && $behavior != ROLE_BEHAVIORS::CHEF)
                throw new Exception("Only base roles supported to be added to farm via API");
        }

        $config = array(
            'scaling.enabled' => 0,
            'scaling.min_instances' => 1,
            'scaling.max_instances' => 1,
            'scaling.polling_interval' => 2,

            'system.timeouts.launch' => 9600,
            'system.timeouts.reboot' => 9600
        );
        if (PlatformFactory::isOpenstack($Platform)) {
            //TODO:
        }
        if ($Platform == SERVER_PLATFORMS::EC2) {
            $config['aws.security_groups.list'] = json_encode(array('default', \Scalr::config('scalr.aws.security_group_name')));
        }

        if ($Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP] == 1 && !$Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT])
            $config[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT] = '_default';

        $config = array_merge($config, $Configuration);
        $this->validateFarmRoleConfiguration($config);

        $Alias = $this->stripValue($Alias);
        if (strlen($Alias) < 4)
            throw new Exception("Role Alias should be longer than 4 characters");

        if (preg_match("/^[^A-Za-z0-9_-]+$/", $Alias))
            throw new Exception("Role Alias should has only 'A-Za-z0-9-_' characters");

        if (!PlatformFactory::isOpenstack($Platform) && $Platform != SERVER_PLATFORMS::EC2)
            throw new Exception("Only EC2 and Openstack roles are supported by this method");

        if (!$this->Environment->isPlatformEnabled($Platform))
            throw new Exception("'{$Platform}' cloud is not configured in your environment");

        $locations = $dbRole->getCloudLocations($Platform);
        if (!in_array($CloudLocation, $locations))
            throw new Exception(sprintf("Role '%s' doesn't have image configured for cloud location '%s'", $dbRole->name, $CloudLocation));

        if ($Alias) {
            foreach ($dbFarm->GetFarmRoles() as $farmRole) {
                if ($farmRole->Alias == $Alias)
                    throw new Exception("Selected alias is already used by another role in selected farm");
            }
        }

        $dbFarmRole = $dbFarm->AddRole($dbRole, $Platform, $CloudLocation, 1);
        $dbFarmRole->Alias = $Alias ? $Alias : $dbRole->name;

        foreach ($config as $k => $v)
            $dbFarmRole->SetSetting($k, $v, DBFarmRole::TYPE_CFG);

        foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
            $behavior->onFarmSave($dbFarm, $dbFarmRole);

        $dbFarmRole->Save();

        $response = $this->CreateInitialResponse();
        $response->FarmRoleID = $dbFarmRole->ID;

        return $response;
    }

    public function FarmRemoveRole($FarmID, $FarmRoleID)
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }
        $this->user->getPermissions()->validate($DBFarm);

        $DBFarm->isLocked(true);

        try {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            if ($DBFarm->ID != $DBFarmRole->FarmID) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));
        }

        $this->user->getPermissions()->validate($DBFarm);

        $DBFarmRole->Delete();

        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
    }

    public function FarmRemove($FarmID)
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        try {
            $dbFarm = DBFarm::LoadByID($FarmID);
            if ($dbFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }
        $this->user->getPermissions()->validate($dbFarm);

        $dbFarm->isLocked(true);

        if ($dbFarm->Status != FARM_STATUS::TERMINATED)
            throw new Exception(_("Cannot delete a running farm. Please terminate a farm before deleting it."));

        $servers = $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id=? AND status!=?", array($dbFarm->ID, SERVER_STATUS::TERMINATED));
        if ($servers != 0)
            throw new Exception(sprintf(_("Cannot delete a running farm. %s server are still running on this farm."), $servers));

        $this->DB->BeginTrans();

        try
        {
            foreach ($this->DB->GetAll("SELECT * FROM farm_roles WHERE farmid = ?", array($dbFarm->ID)) as $value) {
                $this->DB->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type IN(?,?)", array(
                    $value['id'],
                    Scalr_SchedulerTask::TARGET_ROLE,
                    Scalr_SchedulerTask::TARGET_INSTANCE
                ));

                $this->DB->Execute("DELETE FROM farm_role_scripts WHERE farm_roleid=?", array($value['id']));
            }

            $this->DB->Execute("DELETE FROM scheduler WHERE target_id = ? AND target_type = ?", array(
                $dbFarm->ID,
                Scalr_SchedulerTask::TARGET_FARM
            ));

            //We should not remove farm_settings because it is used in stats!

            $this->DB->Execute("DELETE FROM farms WHERE id=?", array($dbFarm->ID));
            $this->DB->Execute("DELETE FROM farm_roles WHERE farmid=?", array($dbFarm->ID));
            $this->DB->Execute("DELETE FROM logentries WHERE farmid=?", array($dbFarm->ID));
            $this->DB->Execute("DELETE FROM elastic_ips WHERE farmid=?", array($dbFarm->ID));
            $this->DB->Execute("DELETE FROM events WHERE farmid=?", array($dbFarm->ID));
            $this->DB->Execute("DELETE FROM ec2_ebs WHERE farm_id=?", array($dbFarm->ID));

            $this->DB->Execute("DELETE FROM farm_role_options WHERE farmid=?", array($dbFarm->ID));
            $this->DB->Execute("DELETE FROM farm_lease_requests WHERE farm_id=?", array($dbFarm->ID));


            //TODO: Remove servers
            $servers = $this->DB->Execute("SELECT server_id FROM servers WHERE farm_id=?", array($dbFarm->ID));
            while ($server = $servers->FetchRow()) {
                $dbServer = DBServer::LoadByID($server['server_id']);
                $dbServer->Remove();
            }

            $this->DB->Execute("UPDATE dns_zones SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
            $this->DB->Execute("UPDATE apache_vhosts SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
        } catch(Exception $e) {
            $this->DB->RollbackTrans();
            throw new Exception(_("Cannot delete farm at the moment ({$e->getMessage()}). Please try again later."));
        }

        $this->DB->CommitTrans();

        $this->DB->Execute("DELETE FROM scripting_log WHERE farmid=?", array($dbFarm->ID));

        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
    }

    public function FarmCreate($Name, $Description = "", $ProjectID = "")
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_MANAGE);

        $ProjectID = strtolower($ProjectID);

        $response = $this->CreateInitialResponse();

        $Name = $this->stripValue($Name);
        $Description = $this->stripValue($Description);

        if (!$Name || strlen($Name) < 5)
            throw new Exception('Name should be at least 5 characters');

        $dbFarm = new DBFarm();
        $dbFarm->ClientID = $this->user->getAccountId();
        $dbFarm->EnvID = $this->Environment->id;
        $dbFarm->Status = FARM_STATUS::TERMINATED;

        $dbFarm->createdByUserId = $this->user->getId();
        $dbFarm->createdByUserEmail = $this->user->getEmail();
        $dbFarm->changedByUserId = $this->user->getId();
        $dbFarm->changedTime = microtime();

        $dbFarm->Name = $Name;
        $dbFarm->RolesLaunchOrder = 0;
        $dbFarm->Comments = $Description;

        $dbFarm->save();

        //Associates cost analytics project with the farm.
        $dbFarm->setProject(!empty($ProjectID) ? $ProjectID : null);

        $governance = new Scalr_Governance($this->Environment->id);

        if ($governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE)) {
            $dbFarm->SetSetting(DBFarm::SETTING_LEASE_STATUS, 'Active'); // for created farm
        }

        $response->FarmID = $dbFarm->ID;

        return $response;
    }

    public function FarmClone($FarmID)
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS, Acl::PERM_FARMS_CLONE);

        $response = $this->CreateInitialResponse();
        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $this->user->getPermissions()->validate($DBFarm);

        $farm = $DBFarm->cloneFarm(null, $this->user, $this->Environment->id);
        $response->FarmID = $farm->ID;

        return $response;
    }

    public function ScriptingLogsList($FarmID, $ServerID = null, $EventID = null, $StartFrom = 0, $RecordsLimit = 20)
    {
        $this->restrictAccess(Acl::RESOURCE_LOGS_SCRIPTING_LOGS);

        //Note! We do not check not-owned-farms permission here. It's approved by Igor.
        $farminfo = $this->DB->GetRow("SELECT clientid FROM farms WHERE id=? AND env_id=?",
            array($FarmID, $this->Environment->id)
        );

        if (!$farminfo)
            throw new Exception(sprintf("Farm not found", $FarmID));

        $sql = "SELECT * FROM scripting_log WHERE farmid='{$FarmID}'";
        if ($ServerID)
            $sql .= " AND server_id=".$this->DB->qstr($ServerID);

        if ($EventID)
            $sql .= " AND event_id=".$this->DB->qstr($EventID);

        $total = $this->DB->GetOne(preg_replace('/\*/', 'COUNT(*)', $sql, 1));

        $sql .= " ORDER BY id DESC";

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
        while ($row = $rows->FetchRow())
        {
            $itm = new stdClass();
            $itm->ServerID = $row['server_id'];
            $itm->Message = $row['message'];
            $itm->Timestamp = strtotime($row['dtadded']);
            $itm->ScriptName = $row['script_name'];
            $itm->ExecTime = $row['exec_time'];
            $itm->ExecExitCode = $row['exec_exitcode'];

            if (stristr($row['event'], 'CustomEvent'))
                $itm->Event = "Manual";
            elseif (stristr($row['event'], 'APIEvent'))
                $itm->Event = "API";
            else
                $itm->Event = $row['event'];

            $response->LogSet->Item[] = $itm;
        }

        return $response;
    }

    public function FarmRoleParametersList($FarmRoleID)
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS_ROLES);

        try {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            $DBFarm = DBFarm::LoadByID($DBFarmRole->FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));
        }

        $this->user->getPermissions()->validate($DBFarm);

        $response = $this->CreateInitialResponse();
        $response->ParamSet = new stdClass();
        $response->ParamSet->Item = array();

        $rParams = $DBFarmRole->GetRoleObject()->getParameters();
        foreach ($rParams as $p) {
            $val = $this->DB->GetOne("SELECT value FROM farm_role_options WHERE farm_roleid = ? AND hash = ? LIMIT 1", array($DBFarmRole->ID, $p['hash']));
            if (!$val)
                $val = $p['defval'];

            $itm = new stdClass();
            $itm->{"Name"} = $p['hash'];
            $itm->{"Value"} = $val;
            $itm->{"FullName"} = $p['name'];

            $response->ParamSet->Item[] = $itm;
        }

        return $response;
    }

    public function FarmRoleUpdateParameterValue($FarmRoleID, $ParamName, $ParamValue)
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS_ROLES, Acl::PERM_FARMS_ROLES_MANAGE);

        throw new Exception("FarmRole parameters are deprected. Please use GlobalVariables instead.");

        /*
        try {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            $DBFarm = DBFarm::LoadByID($DBFarmRole->FarmID);
            if ($DBFarm->EnvID != $this->Environment->id)
                throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));
        }

        $this->user->getPermissions()->validate($DBFarm);

        $updated = false;
        $rParams = $DBFarmRole->GetRoleObject()->getParameters();
        foreach ($rParams as $p) {
            if ($p['hash'] == $ParamName) {
                $id = $this->DB->GetOne("SELECT id FROM farm_role_options WHERE farm_roleid = ? AND hash = ? LIMIT 1", array($DBFarmRole->ID, $p['hash']));
                if ($id) {
                    $updated = true;
                    $this->DB->Execute("UPDATE farm_role_options SET
                        value       = ? WHERE id = ?
                    ", array($ParamValue, $id));
                } else {
                    $updated = true;
                    $this->DB->Execute("INSERT INTO farm_role_options SET
                        farmid      = ?,
                        farm_roleid = ?,
                        name        = ?,
                        value       = ?,
                        hash        = ?
                        ON DUPLICATE KEY UPDATE name = ?
                    ", array($DBFarm->ID, $DBFarmRole->ID, $p['name'], $ParamValue, $p['hash'], $p['name']));
                }
            }
        }

        if (!$updated)
            throw new Exception(sprintf("Parameter '%s' not defined for specified role", array($ParamName)));

        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
        */
    }

    public function EnvironmentsList()
    {
        $response = $this->CreateInitialResponse();
        $response->EnvironmentSet = new stdClass();
        $response->EnvironmentSet->Item = array();

        $envs = $this->user->getEnvironments();
        foreach ($envs as $env) {
            $itm = new stdClass();
            $itm->{"ID"} = $env['id'];
            $itm->{"Name"} = $env['name'];

            $response->EnvironmentSet->Item[] = $itm;
        }

        return $response;
    }

    public function ServerGetExtendedInformation($ServerID)
    {
        $this->restrictAccess(Acl::RESOURCE_FARMS_SERVERS);

        $DBServer = DBServer::LoadByID($ServerID);

        if ($DBServer->envId != $this->Environment->id)
            throw new Exception(sprintf("Server ID #%s not found", $ServerID));

        $this->user->getPermissions()->validate($DBServer);

        $response = $this->CreateInitialResponse();

        $info = PlatformFactory::NewPlatform($DBServer->platform)->GetServerExtendedInformation($DBServer);

        $response->FarmInfo = new stdClass();
        $response->FarmInfo->ID = $DBServer->GetFarmObject()->ID;
        $response->FarmInfo->Name = $DBServer->GetFarmObject()->Name;

        $response->FarmRoleInfo = new stdClass();
        $response->FarmRoleInfo->ID = $DBServer->farmRoleId;
        $response->FarmRoleInfo->RoleID = $DBServer->roleId;
        $response->FarmRoleInfo->Alias = $DBServer->GetFarmRoleObject()->Alias;
        $response->FarmRoleInfo->CloudLocation = $DBServer->GetFarmRoleObject()->CloudLocation;

        $response->ServerInfo = new stdClass();
        $scalrProps = array(
            'ServerID' => $DBServer->serverId,
            'Platform' => $DBServer->platform,
            'RemoteIP' => ($DBServer->remoteIp) ? $DBServer->remoteIp : '' ,
            'LocalIP' => ($DBServer->localIp) ? $DBServer->localIp : '' ,
            'Status' => $DBServer->status,
            'Index' => $DBServer->index,
            'AddedAt' => $DBServer->dateAdded,
            'CloudLocation' => $DBServer->cloudLocation,
            'CloudLocationZone' => $DBServer->cloudLocationZone,
            'Type' => $DBServer->GetFlavor()
        );
        foreach ($scalrProps as $k=>$v) {
            $response->ServerInfo->{$k} = $v;
        }

        $response->PlatformProperties = new stdClass();
        if (is_array($info) && count($info)) {
            foreach ($info as $name => $value) {
                $name = str_replace(".", "_", $name);
                $name = preg_replace("/[^A-Za-z0-9_-]+/", "", $name);

                if ($name == 'MonitoringCloudWatch')
                    continue;

                $response->PlatformProperties->{$name} = $value;
            }
        }

        $response->ScalrProperties = new stdClass();
        if (count($DBServer->GetAllProperties())) {
            $it = array();
            foreach ($DBServer->GetAllProperties() as $name => $value) {
                $name = preg_replace("/[^A-Za-z0-9-]+/", "", $name);
                $response->ScalrProperties->{$name} = $value;
            }
        }

        return $response;
    }

    public function GlobalVariableSet($ParamName, $ParamValue, $FarmRoleID = 0, $FarmID = 0, $ServerID = '', $RoleID = 0)
    {
        if (empty($FarmID) && empty($FarmRoleID) && empty($ServerID)) {
            $this->restrictAccess(Acl::RESOURCE_ENVADMINISTRATION_GLOBAL_VARIABLES);
        }

        try {
            if ($ServerID != '') {
                $DBServer = DBServer::LoadByID($ServerID);
                $DBFarmRole = $DBServer->GetFarmRoleObject();
                $DBFarm = $DBFarmRole->GetFarmObject();
                $scope = Scalr_Scripting_GlobalVariables::SCOPE_SERVER;
                $FarmRoleID = $DBFarmRole->ID;

            } else if ($FarmRoleID != 0) {
                $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
                $DBFarm = DBFarm::LoadByID($DBFarmRole->FarmID);
                $scope = Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE;
                $ServerID = '';
            } elseif ($FarmID != 0) {
                $DBFarm = DBFarm::LoadByID($FarmID);
                $scope = Scalr_Scripting_GlobalVariables::SCOPE_FARM;
                $FarmRoleID = 0;
                $ServerID = '';
            } elseif ($RoleID != 0) {
                $DBRole = DBRole::loadById($RoleID);
                $scope = Scalr_Scripting_GlobalVariables::SCOPE_ROLE;
                $FarmRoleID = 0;
                $FarmID = 0;
                $ServerID = '';
            } else
                throw new Exception ("FarmID or FarmRoleID should be specified");

            if ($DBFarm) {
                if ($DBFarm->EnvID != $this->Environment->id)
                    throw new Exception("N");

                $this->user->getPermissions()->validate($DBFarm);
            } elseif ($DBRole) {
                if ($DBRole->envId != $this->Environment->id)
                    throw new Exception("N");

                $this->user->getPermissions()->validate($DBRole);
            }

        } catch (Exception $e) {
            throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));
        }

        $ParamName = $this->stripValue($ParamName);
        if (!$ParamName)
            throw new Exception("Param name is required");

        $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, $scope);
        $globalVariables->setValues(
            array(array(
                'name' 	=> $ParamName,
                'value'	=> $ParamValue
            )),
            $RoleID,
            $DBFarm->ID,
            $FarmRoleID,
            $ServerID
        );

        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
    }

    public function GlobalVariablesList($ServerID = null, $FarmID = null, $FarmRoleID = null, $RoleID = null)
    {
        if (empty($FarmID) && empty($FarmRoleID) && empty($RoleID) && empty($ServerID)) {
            $this->restrictAccess(Acl::RESOURCE_ENVADMINISTRATION_GLOBAL_VARIABLES);
        }

        $response = $this->CreateInitialResponse();
        $response->VariableSet = new stdClass();
        $response->VariableSet->Item = array();

        if ($ServerID) {
            $DBServer = DBServer::LoadByID($ServerID);
            if ($DBServer->envId != $this->Environment->id)
                throw new Exception(sprintf("Server ID #%s not found", $ServerID));

            $this->user->getPermissions()->validate($DBServer);

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);
            $vars = $globalVariables->listVariables($DBServer->roleId, $DBServer->farmId, $DBServer->farmRoleId, $ServerID);
        } elseif ($FarmID) {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id)
                throw new Exception(sprintf("Farm ID #%s not found", $FarmID));

            $this->user->getPermissions()->validate($DBFarm);

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, Scalr_Scripting_GlobalVariables::SCOPE_FARM);
            $vars = $globalVariables->listVariables(null, $DBFarm->ID, null);
        } elseif ($RoleID) {
            $DBRole = DBRole::LoadByID($RoleID);
            if ($DBRole->envId != $this->Environment->id)
                throw new Exception(sprintf("Role ID #%s not found", $RoleID));

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, Scalr_Scripting_GlobalVariables::SCOPE_ROLE);
            $vars = $globalVariables->listVariables($RoleID, null, null);
        } elseif ($FarmRoleID) {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            if ($DBFarmRole->GetFarmObject()->EnvID != $this->Environment->id)
                throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));

            $this->user->getPermissions()->validate($DBFarmRole);

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, Scalr_Scripting_GlobalVariables::SCOPE_FARMROLE);
            $vars = $globalVariables->listVariables($DBFarmRole->RoleID, $DBFarmRole->FarmID, $DBFarmRole->ID);
        } else {
            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, Scalr_Scripting_GlobalVariables::SCOPE_ENVIRONMENT);
            $vars = $globalVariables->listVariables();
        }

        foreach ($vars as $v) {
            $itm = new stdClass();
            $itm->{"Name"} = $v['name'];
            $itm->{"Value"} = $v['value'];
            $itm->{"Private"} = $v['private'];

            $response->VariableSet->Item[] = $itm;
        }

        return $response;
    }

    public function DmSourcesList()
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_SOURCES);

        $response = $this->CreateInitialResponse();
        $response->SourceSet = new stdClass();
        $response->SourceSet->Item = array();

        $rows = $this->DB->Execute("SELECT * FROM dm_sources WHERE env_id=?", array($this->Environment->id));
        while ($row = $rows->FetchRow()) {
            $itm = new stdClass();
            $itm->{"ID"} = $row['id'];
            $itm->{"Type"} = $row['type'];
            $itm->{"URL"} = $row['url'];
            $itm->{"AuthType"} = $row['auth_type'];

            $response->SourceSet->Item[] = $itm;
        }

        return $response;
    }

    public function DmSourceCreate($Type, $URL, $AuthLogin=null, $AuthPassword=null)
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_SOURCES);

        $source = Scalr_Model::init(Scalr_Model::DM_SOURCE);

        $authInfo = new stdClass();
        if ($Type == Scalr_Dm_Source::TYPE_SVN)
        {
            $authInfo->login = $AuthLogin;
            $authInfo->password	= $AuthPassword;
            $authType = Scalr_Dm_Source::AUTHTYPE_PASSWORD;
        }

        if (Scalr_Dm_Source::getIdByUrlAndAuth($URL, $authInfo))
            throw new Exception("Source already exists in database");

        $source->envId = $this->Environment->id;

        $source->url = $URL;
        $source->type = $Type;
        $source->authType = $authType;
        $source->setAuthInfo($authInfo);

        $source->save();

        $response = $this->CreateInitialResponse();
        $response->SourceID = $source->id;

        return $response;
    }

    public function DmApplicationCreate($Name, $SourceID, $PreDeployScript=null, $PostDeployScript=null)
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_APPLICATIONS);

        $application = Scalr_Model::init(Scalr_Model::DM_APPLICATION);
        $application->envId = $this->Environment->id;

        if (Scalr_Dm_Application::getIdByNameAndSource($Name, $SourceID))
            throw new Exception("Application already exists in database");

        $application->name = $Name;
        $application->sourceId = $SourceID;

        $application->setPreDeployScript($PreDeployScript);
        $application->setPostDeployScript($PostDeployScript);

        $application->save();

        $response = $this->CreateInitialResponse();
        $response->ApplicationID = $application->id;

        return $response;
    }

    public function DmApplicationsList()
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_APPLICATIONS);

        $response = $this->CreateInitialResponse();
        $response->ApplicationSet = new stdClass();
        $response->ApplicationSet->Item = array();

        $rows = $this->DB->Execute("SELECT * FROM dm_applications WHERE env_id=?", array($this->Environment->id));
        while ($row = $rows->FetchRow()) {
            $itm = new stdClass();
            $itm->{"ID"} = $row['id'];
            $itm->{"SourceID"} = $row['dm_source_id'];
            $itm->{"Name"} = $row['name'];

            $response->ApplicationSet->Item[] = $itm;
        }

        return $response;
    }

    public function DmDeploymentTasksList($FarmRoleID = null, $ApplicationID = null, $ServerID = null)
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_TASKS);

        $sql = "SELECT id FROM dm_deployment_tasks WHERE status !='".Scalr_Dm_DeploymentTask::STATUS_ARCHIVED."' AND env_id = '{$this->Environment->id}'";
        if ($FarmRoleID)
            $sql .= ' AND farm_role_id = ' . $this->DB->qstr($FarmRoleID);

        if ($ApplicationID)
            $sql .= ' AND dm_application_id = ' . $this->DB->qstr($ApplicationID);

        if ($ServerID)
            $sql .= ' AND server_id = ' . $this->DB->qstr($ServerID);

        $response = $this->CreateInitialResponse();
        $response->DeploymentTasksSet = new stdClass();
        $response->DeploymentTasksSet->Item = array();

        $rows = $this->DB->Execute($sql);
        while ($task = $rows->FetchRow()) {
            $deploymentTask = Scalr_Model::init(Scalr_Model::DM_DEPLOYMENT_TASK)->loadById($task['id']);

            $itm = new stdClass();
            $itm->ServerID = $deploymentTask->serverId;
            $itm->DeploymentTaskID = $deploymentTask->id;
            $itm->FarmRoleID = $deploymentTask->farmRoleId;
            $itm->RemotePath = $deploymentTask->remotePath;
            $itm->Status = $deploymentTask->status;

            $response->DeploymentTasksSet->Item[] = $itm;
        }

        return $response;
    }

    public function DmDeploymentTaskGetLog($DeploymentTaskID, $StartFrom = 0, $RecordsLimit = 20)
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_TASKS);

        $deploymentTask = Scalr_Model::init(Scalr_Model::DM_DEPLOYMENT_TASK)->loadById($DeploymentTaskID);
        if ($deploymentTask->envId != $this->Environment->id)
            throw new Exception(sprintf("Deployment task #%s not found", $DeploymentTaskID));

        $response = $this->CreateInitialResponse();

        $sql = "SELECT * FROM dm_deployment_task_logs WHERE dm_deployment_task_id = " . $this->DB->qstr($DeploymentTaskID);

        $total = $this->DB->GetOne(preg_replace('/\*/', 'COUNT(*)', $sql, 1));

        $sql .= " ORDER BY id DESC";

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
            $itm->Message = $row['message'];
            $itm->Timestamp = strtotime($row['dtadded']);

            $response->LogSet->Item[] = $itm;
        }

        return $response;
    }

    public function DmDeploymentTaskGetStatus($DeploymentTaskID)
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_TASKS);

        $deploymentTask = Scalr_Model::init(Scalr_Model::DM_DEPLOYMENT_TASK)->loadById($DeploymentTaskID);
        if ($deploymentTask->envId != $this->Environment->id)
            throw new Exception(sprintf("Deployment task #%s not found", $DeploymentTaskID));

        $response = $this->CreateInitialResponse();
        $response->DeploymentTaskStatus = $deploymentTask->status;
        if ($deploymentTask->status == Scalr_Dm_DeploymentTask::STATUS_FAILED)
            $response->FailureReason = $deploymentTask->lastError;

        return $response;
    }

    public function DmApplicationDeploy($ApplicationID, $FarmRoleID, $RemotePath)
    {
        $this->restrictAccess(Acl::RESOURCE_DEPLOYMENTS_APPLICATIONS);

        $application = Scalr_Model::init(Scalr_Model::DM_APPLICATION)->loadById($ApplicationID);
        if ($application->envId != $this->Environment->id)
            throw new Exception("Aplication not found in database");

        $dbFarmRole = DBFarmRole::LoadByID($FarmRoleID);

        if ($dbFarmRole->GetFarmObject()->EnvID != $this->Environment->id)
            throw new Exception("Farm Role not found in database");

        $this->user->getPermissions()->validate($dbFarmRole);

        $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));

        if (count($servers) == 0)
            throw new Exception("There is no running servers on selected farm/role");

        $response = $this->CreateInitialResponse();
        $response->DeploymentTasksSet = new stdClass();
        $response->DeploymentTasksSet->Item = array();

        foreach ($servers as $dbServer) {
            $taskId = Scalr_Dm_DeploymentTask::getId($ApplicationID, $dbServer->serverId, $RemotePath);
            $deploymentTask = Scalr_Model::init(Scalr_Model::DM_DEPLOYMENT_TASK);

            if (!$taskId) {
                try {
                    if (!$dbServer->IsSupported("0.7.38"))
                        throw new Exception("Scalr agent installed on this server doesn't support deployments. Please update it to the latest version");

                    $deploymentTask->create(
                        $FarmRoleID,
                        $ApplicationID,
                        $dbServer->serverId,
                        Scalr_Dm_DeploymentTask::TYPE_API,
                        $RemotePath,
                        $this->Environment->id
                    );
                } catch (Exception $e) {
                    $itm = new stdClass();
                    $itm->ServerID = $dbServer->serverId;
                    $itm->ErrorMessage = $e->getMessage();

                    $response->DeploymentTasksSet->Item[] = $itm;

                    continue;
                }
            } else {
                $deploymentTask->loadById($taskId);
                $deploymentTask->status = Scalr_Dm_DeploymentTask::STATUS_PENDING;
                $deploymentTask->log("Re-deploying application. Status: pending");
                $deploymentTask->save();
            }

            $itm = new stdClass();
            $itm->ServerID = $dbServer->serverId;
            $itm->DeploymentTaskID = $deploymentTask->id;
            $itm->FarmRoleID = $deploymentTask->farmRoleId;
            $itm->RemotePath = $deploymentTask->remotePath;
            $itm->Status = $deploymentTask->status;

            $response->DeploymentTasksSet->Item[] = $itm;
        }

        return $response;
    }
}
