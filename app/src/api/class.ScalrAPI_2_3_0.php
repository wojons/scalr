<?php

use Scalr\Acl\Acl;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\Environment;

class ScalrAPI_2_3_0 extends ScalrAPI_2_2_0
{
    public function FireCustomEvent($ServerID, $EventName, array $Params = array())
    {
        $this->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS);

        $dbServer = DBServer::LoadByID($ServerID);
        if ($dbServer->envId != $this->Environment->id)
            throw new Exception(sprintf("Server ID #%s not found", $ServerID));

        if (\Scalr\Model\Entity\EventDefinition::isExist($EventName, $this->user->getAccountId(), $this->Environment->id)) {
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
            if ($DBFarm->EnvID != $this->Environment->id) {
                throw new Exception("N");
            }
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $response->ID = $DBFarm->ID;
        $response->Name = $DBFarm->Name;
        $response->IsLocked = $DBFarm->GetSetting(Entity\FarmSetting::LOCK);
        if ($response->IsLocked == 1) {
            $response->LockComment = $DBFarm->GetSetting(Entity\FarmSetting::LOCK_COMMENT);
            try {
                $response->LockedBy = Scalr_Account_User::init()->loadById($DBFarm->GetSetting(Entity\FarmSetting::LOCK_BY))->fullname;
            } catch (Exception $e) {}
        }

        foreach ($response->FarmRoleSet->Item as &$item) {
            $dbFarmRole = DBFarmRole::LoadByID($item->ID);

            $item->IsScalingEnabled = $dbFarmRole->GetSetting(Entity\FarmRoleSetting::SCALING_ENABLED);
            $item->ScalingAlgorithmSet = new stdClass();
            $item->ScalingAlgorithmSet->Item = array();

            $metrics = $this->DB->GetAll("
                SELECT metric_id, name, dtlastpolled FROM `farm_role_scaling_metrics`
                INNER JOIN scaling_metrics ON scaling_metrics.id = farm_role_scaling_metrics.metric_id WHERE farm_roleid = ?
            ", [
                $item->ID
            ]);

            foreach ($metrics as $metric) {
                $itm = new stdClass();
                $itm->MetricID =  $metric['metric_id'];
                $itm->MetricName =  $metric['name'];
                $itm->DateLastPolled = $metric['dtlastpolled'];

                $item->ScalingAlgorithmSet->Item[] = $itm;
            }
        }

        return $response;
    }

    public function FarmGetDnsEndpoints($FarmID)
    {
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
        try {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            $dbFarm = DBFarm::LoadByID($DBFarmRole->FarmID);
            if ($dbFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));
        }

        $this->user->getPermissions()->validate($dbFarm);
        $this->restrictFarmAccess($dbFarm, Acl::PERM_FARMS_UPDATE);

        $governance = new Scalr_Governance($this->Environment->id);

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

        if ($DBFarmRole->Platform == SERVER_PLATFORMS::EC2) {
            $vpcId = $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID);
            if ($vpcId) {
                if (!$Configuration['aws.vpc_subnet_id'] && !$DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID))
                    throw new Exception("Farm configured to run inside VPC. 'aws.vpc_subnet_id' is required");

                if (isset($Configuration['aws.vpc_subnet_id']) && $DBFarmRole->GetSetting(Entity\FarmRoleSetting::AWS_VPC_SUBNET_ID) != $Configuration['aws.vpc_subnet_id']) {
                    $vpcRegion = $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_REGION);
                    $vpcGovernance = $governance->getValue('ec2', 'aws.vpc');
                    $vpcGovernanceIds = $governance->getValue('ec2', 'aws.vpc', 'ids');

                    $subnets = json_decode($Configuration['aws.vpc_subnet_id'], true);
                    if (count($subnets) == 0)
                        throw new Exception("Subnets list is empty or json is incorrect");

                    $type = false;

                    foreach ($subnets as $subnetId) {

                        $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
                        $info = $platform->listSubnets($this->Environment, $DBFarmRole->CloudLocation, $vpcId, true, $subnetId);

                        if (substr($info['availability_zone'], 0, -1) != $vpcRegion)
                            throw new Exception(sprintf("Only subnets from %s region are allowed according to VPC settings", $vpcRegion));

                        if ($vpcGovernance == 1) {
                            // Check valid subnets
                            if ($vpcGovernanceIds[$vpcId] && is_array($vpcGovernanceIds[$vpcId]) && !in_array($subnetId, $vpcGovernanceIds[$vpcId]))
                                throw new Exception(sprintf("Only %s subnet(s) allowed by governance settings", implode (', ', $vpcGovernanceIds[$vpcId])));


                            // Check if subnets types
                            if ($vpcGovernanceIds[$vpcId] == "outbound-only") {
                                if ($info['type'] != 'private')
                                    throw new Exception("Only private subnets allowed by governance settings");
                            }

                            if ($vpcGovernanceIds[$vpcId] == "full") {
                                if ($info['type'] != 'public')
                                    throw new Exception("Only public subnets allowed by governance settings");
                            }
                        }

                        if (!$type)
                            $type = $info['type'];
                        else {
                            if ($type != $info['type'])
                                throw new Exception("Mix of public and private subnets are not allowed. Please specify only public or only private subnets.");
                        }
                    }
                }
            }
        }

        if ($Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP] == 1 && !$Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT])
            $Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT] = '_default';

        foreach ($Configuration as $k => $v)
            $DBFarmRole->SetSetting($k, trim($v), Entity\FarmRoleSetting::TYPE_CFG);


        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
    }

    /**
     * Validates configuration.
     *
     * It also updates passed array to ensure backward capability when mapping is changed during development
     *
     * @param    array      $config  The configuration options that looks like [property => value]
     * @return   boolean    Returns true if configuration is valid or false otherwise
     * @throws   Exception
     */
    private function validateFarmRoleConfiguration(array &$config)
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
            'openstack.availability_zone',

            'cloudstack.security_groups.list',
            'cloudstack.service_offering_id',
            'cloudstack.network_id',

            'chef.bootstrap',
            'chef.server_id',
            'chef.environment',
            'chef.role_name',
            'chef.attributes',
            'chef.runlist',
            'chef.node_name_tpl',
            'chef.ssl_verify_mode',
            'chef.cookbook_url',
            'chef.cookbook_url_type',
            'chef.ssh_private_key',
            'chef.relative_path',
            'chef.log_level',

            'dns.create_records',
            'dns.ext_record_alias',
            'dns.int_record_alias',

            'aws.availability_zone',
            'aws.instance_type',
            'aws.security_groups.list',
            'aws.iam_instance_profile_arn',
            'aws.vpc_subnet_id',

            'gce.machine-type',
            'gce.network',
            'gce.on-host-maintenance',

            'instance_type'
        );

        foreach ($config as $key => $value) {
            if (!in_array($key, $allowedConfiguration)) {
                throw new Exception(sprintf(
                    "Unknown configuration option '%s'",
                    $this->stripValue($key)
                ));
            }

            if (in_array($key, ['openstack.flavor-id', 'aws.instance_type', 'gce.machine-type', 'cloudstack.service_offering_id'])) {
                $instanceType = $value;
            }

            /*
            if ($key == 'gce.on-host-maintenance' && !in_array($value, array('MIGRATE, TERMINATE')))
                throw new Exception("Allowed values for 'gce.on-host-maintenance' are MIGRATE or TERMINATE");
            */
        }

        if (isset($instanceType)) {
            $config[Entity\FarmRoleSetting::INSTANCE_TYPE] = $instanceType;
        }

        unset($config['openstack.flavor-id'], $config['aws.instance_type'], $config['gce.machine-type'], $config['cloudstack.service_offering_id']);

        return true;
    }

    public function FarmAddRole($Alias, $FarmID, $RoleID, $Platform, $CloudLocation, array $Configuration = array())
    {
        try {
            $dbFarm = DBFarm::LoadByID($FarmID);
            if ($dbFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }
        $this->user->getPermissions()->validate($dbFarm);
        $this->restrictFarmAccess($dbFarm, Acl::PERM_FARMS_UPDATE);

        $dbFarm->isLocked(true);

        $governance = new Scalr_Governance($this->Environment->id);

        $dbRole = DBRole::loadById($RoleID);
        if (!$dbRole->__getNewRoleObject()->hasAccessPermissions(User::findPk($this->user->getId()), Environment::findPk($this->Environment->id))) {
            throw new Exception(sprintf("Role #%s not found", $RoleID));
        }

        if (!empty(($envs = $dbRole->__getNewRoleObject()->getAllowedEnvironments()))) {
            if (!in_array($this->Environment->id, $envs)) {
                throw new Exception(sprintf("Role #%s not found", $RoleID));
            }
        }

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

            $vpcId = $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_ID);
            if ($vpcId) {
                if (!$Configuration['aws.vpc_subnet_id'])
                    throw new Exception("Farm configured to run inside VPC. 'aws.vpc_subnet_id' is required");

                $vpcRegion = $dbFarm->GetSetting(Entity\FarmSetting::EC2_VPC_REGION);
                if ($CloudLocation != $vpcRegion)
                    throw new Exception(sprintf("Farm configured to run inside VPC in %s region. Only roles in this region are allowed.", $vpcRegion));

                $vpcGovernance = $governance->getValue('ec2', 'aws.vpc');
                $vpcGovernanceIds = $governance->getValue('ec2', 'aws.vpc', 'ids');

                $subnets = json_decode($Configuration['aws.vpc_subnet_id'], true);
                if (count($subnets) == 0)
                    throw new Exception("Subnets list is empty or json is incorrect");

                $type = false;

                foreach ($subnets as $subnetId) {

                    $platform = PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2);
                    $info = $platform->listSubnets($this->Environment, $CloudLocation, $vpcId, true, $subnetId);

                    if (substr($info['availability_zone'], 0, -1) != $vpcRegion)
                        throw new Exception(sprintf("Only subnets from %s region are allowed according to VPC settings", $vpcRegion));

                    if ($vpcGovernance == 1) {
                        // Check valid subnets
                        if ($vpcGovernanceIds[$vpcId] && is_array($vpcGovernanceIds[$vpcId]) && !in_array($subnetId, $vpcGovernanceIds[$vpcId]))
                            throw new Exception(sprintf("Only %s subnet(s) allowed by governance settings", implode (', ', $vpcGovernanceIds[$vpcId])));


                        // Check if subnets types
                        if ($vpcGovernanceIds[$vpcId] == "outbound-only") {
                            if ($info['type'] != 'private')
                                throw new Exception("Only private subnets allowed by governance settings");
                        }

                        if ($vpcGovernanceIds[$vpcId] == "full") {
                            if ($info['type'] != 'public')
                                throw new Exception("Only public subnets allowed by governance settings");
                        }
                    }

                    if (!$type)
                        $type = $info['type'];
                    else {
                        if ($type != $info['type'])
                            throw new Exception("Mix of public and private subnets are not allowed. Please specify only public or only private subnets.");
                    }
                }
            }
        }
        if (PlatformFactory::isCloudstack($Platform)) {
            $config['cloudstack.security_groups.list'] = json_encode(array('default', \Scalr::config('scalr.aws.security_group_name')));
        }
        if ($Platform == SERVER_PLATFORMS::GCE) {
            $config['gce.network'] = 'default';
            $config['gce.on-host-maintenance'] = 'MIGRATE';
        }

        if ($Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP] == 1 && !$Configuration[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT])
            $config[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT] = '_default';

        $config = array_merge($config, $Configuration);
        $this->validateFarmRoleConfiguration($config);

        if ($Platform == SERVER_PLATFORMS::GCE) {
            $config['gce.cloud-location'] = $CloudLocation;
            $config['gce.region'] = substr($CloudLocation, 0, -1);
        }

        $Alias = $this->stripValue($Alias);
        if (strlen($Alias) < 4)
            throw new Exception("Role Alias should be longer than 4 characters");

        if (!preg_match("/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/si", $Alias))
            throw new Exception("Alias should start and end with letter or number and contain only letters, numbers and dashes.");

        if (!$this->Environment->isPlatformEnabled($Platform))
            throw new Exception("'{$Platform}' cloud is not configured in your environment");

        $images = $dbRole->__getNewRoleObject()->fetchImagesArray();
        $locations = isset($images[$Platform]) ? array_keys($images[$Platform]) : [];
        if (!in_array($CloudLocation, $locations) && $Platform != SERVER_PLATFORMS::GCE)
            throw new Exception(sprintf("Role '%s' doesn't have an image configured for cloud location '%s'", $dbRole->name, $CloudLocation));

        if ($Alias) {
            foreach ($dbFarm->GetFarmRoles() as $farmRole) {
                if ($farmRole->Alias == $Alias)
                    throw new Exception("Selected alias is already used by another role in selected farm");
            }
        }

        $dbFarmRole = $dbFarm->AddRole($dbRole, $Platform, $CloudLocation, 1);
        $dbFarmRole->Alias = $Alias ? $Alias : $dbRole->name;

        foreach ($config as $k => $v)
            $dbFarmRole->SetSetting($k, trim($v), Entity\FarmRoleSetting::TYPE_CFG);

        foreach (Scalr_Role_Behavior::getListForFarmRole($dbFarmRole) as $behavior)
            $behavior->onFarmSave($dbFarm, $dbFarmRole);

        $dbFarmRole->Save();

        $response = $this->CreateInitialResponse();
        $response->FarmRoleID = $dbFarmRole->ID;

        return $response;
    }

    public function FarmRemoveRole($FarmID, $FarmRoleID)
    {
        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }
        $this->user->getPermissions()->validate($DBFarm);
        $this->restrictFarmAccess($DBFarm, Acl::PERM_FARMS_UPDATE);

        $DBFarm->isLocked(true);

        try {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            if ($DBFarm->ID != $DBFarmRole->FarmID) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));
        }

        $this->user->getPermissions()->validate($DBFarm);

        $farmRole = new Entity\FarmRole();
        $farmRole->id = $FarmRoleID;
        $farmRole->delete();

        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
    }

    public function FarmRemove($FarmID)
    {
        try {
            $dbFarm = DBFarm::LoadByID($FarmID);

            if ($dbFarm->EnvID != $this->Environment->id) {
                throw new Exception("N");
            }
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $this->user->getPermissions()->validate($dbFarm);
        $this->restrictFarmAccess($dbFarm, Acl::PERM_FARMS_DELETE);

        $dbFarm->isLocked(true);

        if ($dbFarm->Status != FARM_STATUS::TERMINATED) {
            throw new Exception(_("Cannot delete a running farm. Please terminate a farm before deleting it."));
        }

        $servers = $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE farm_id=? AND status!=?", array($dbFarm->ID, SERVER_STATUS::TERMINATED));

        if ($servers != 0) {
            throw new Exception(sprintf(_("Cannot delete a running farm. %s server are still running on this farm."), $servers));
        }

        $this->DB->BeginTrans();

        try {
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
            $this->DB->Execute("DELETE FROM farm_lease_requests WHERE farm_id=?", array($dbFarm->ID));

            //TODO: Remove servers
            $servers = $this->DB->Execute("SELECT server_id FROM servers WHERE farm_id=?", array($dbFarm->ID));

            while ($server = $servers->FetchRow()) {
                $dbServer = DBServer::LoadByID($server['server_id']);
                $dbServer->Remove();
            }

            $this->DB->Execute("UPDATE dns_zones SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
            $this->DB->Execute("UPDATE apache_vhosts SET farm_id='0', farm_roleid='0' WHERE farm_id=?", array($dbFarm->ID));
        } catch (Exception $e) {
            $this->DB->RollbackTrans();
            throw new Exception(_("Cannot delete farm at the moment ({$e->getMessage()}). Please try again later."));
        }

        $this->DB->CommitTrans();

        $this->DB->Execute("DELETE FROM orchestration_log WHERE farmid=?", [$dbFarm->ID]);

        $response = $this->CreateInitialResponse();
        $response->Result = true;

        return $response;
    }

    public function FarmCreate($Name, $Description = "", $ProjectID = "", array $Configuration = array())
    {
        $this->restrictAccess(Acl::RESOURCE_OWN_FARMS, Acl::PERM_FARMS_CREATE);

        $governance = new Scalr_Governance($this->Environment->id);

        $ProjectID = strtolower($ProjectID);

        $response = $this->CreateInitialResponse();

        $Name = $this->stripValue($Name);
        $Description = $this->stripValue($Description);

        if (!$Name || strlen($Name) < 5)
            throw new Exception('Name should be at least 5 characters');

        if ($Configuration['vpc_region'] && !$Configuration['vpc_id'])
            throw new Exception("VPC ID is required if VPC region was specified");

        if (!$Configuration['vpc_region'] && $Configuration['vpc_id'])
            throw new Exception("VPC Region is required if VPC ID was specified");

        // VPC Governance validation
        $vpcGovernance = $governance->getValue('ec2', 'aws.vpc');
        if ($vpcGovernance) {
            $vpcGovernanceRegions = $governance->getValue('ec2', 'aws.vpc', 'regions');

            if (!$Configuration['vpc_region'])
                throw new Exception("VPC configuration is required according to governance settings");

            if (!in_array($Configuration['vpc_region'], array_keys($vpcGovernanceRegions)))
                throw new Exception(sprintf("Only %s region(s) allowed according to governance settings", implode (', ', array_keys($vpcGovernanceRegions))));

            if (!in_array($Configuration['vpc_id'], $vpcGovernanceRegions[$Configuration['vpc_region']]['ids']))
                throw new Exception(sprintf("Only %s VPC(s) allowed according to governance settings", implode (', ', $vpcGovernanceRegions[$Configuration['vpc_region']]['ids'])));
        }

        $dbFarm = new DBFarm();
        $dbFarm->ClientID = $this->user->getAccountId();
        $dbFarm->EnvID = $this->Environment->id;
        $dbFarm->Status = FARM_STATUS::TERMINATED;

        $dbFarm->changedByUserId = $this->user->getId();
        $dbFarm->ownerId = $this->user->getId();
        $dbFarm->changedTime = microtime();

        $dbFarm->Name = $Name;
        $dbFarm->RolesLaunchOrder = 0;
        $dbFarm->Comments = $Description;

        $dbFarm->save();

        $dbFarm->SetSetting(Entity\FarmSetting::CREATED_BY_ID, $this->user->getId());
        $dbFarm->SetSetting(Entity\FarmSetting::CREATED_BY_EMAIL, $this->user->getEmail());

        //Associates cost analytics project with the farm.
        $dbFarm->setProject(!empty($ProjectID) ? $ProjectID : null);

        if ($governance->isEnabled(Scalr_Governance::CATEGORY_GENERAL, Scalr_Governance::GENERAL_LEASE)) {
            $dbFarm->SetSetting(Entity\FarmSetting::LEASE_STATUS, 'Active'); // for created farm
        }

        if (!$Configuration['timezone'])
            $Configuration['timezone'] = date_default_timezone_get();

        $dbFarm->SetSetting(Entity\FarmSetting::TIMEZONE, $Configuration['timezone']);

        if ($Configuration['vpc_region']) {
            $dbFarm->SetSetting(Entity\FarmSetting::EC2_VPC_ID, $Configuration['vpc_id']);
            $dbFarm->SetSetting(Entity\FarmSetting::EC2_VPC_REGION, $Configuration['vpc_region']);
        }

        $response->FarmID = $dbFarm->ID;

        return $response;
    }

    public function FarmClone($FarmID)
    {
        $response = $this->CreateInitialResponse();
        try {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id) throw new Exception("N");
        } catch (Exception $e) {
            throw new Exception(sprintf("Farm #%s not found", $FarmID));
        }

        $this->user->getPermissions()->validate($DBFarm);
        $this->restrictFarmAccess($DBFarm, Acl::PERM_FARMS_CLONE);

        $farm = $DBFarm->cloneFarm(null, $this->user, $this->Environment->id);
        $response->FarmID = $farm->ID;

        return $response;
    }

    public function ScriptingLogsList($FarmID, $ServerID = null, $EventID = null, $StartFrom = 0, $RecordsLimit = 20)
    {
        $this->restrictAccess(Acl::RESOURCE_LOGS_ORCHESTRATION_LOGS);

        //Note! We do not check not-owned-farms permission here. It's approved by Igor.
        $farminfo = $this->DB->GetRow("SELECT clientid FROM farms WHERE id=? AND env_id=?",
            array($FarmID, $this->Environment->id)
        );

        if (!$farminfo)
            throw new Exception(sprintf("Farm not found", $FarmID));

        $sql = "SELECT * FROM orchestration_log WHERE farmid='{$FarmID}'";
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
        $response->LogSet->Item = [];

        $rows = $this->DB->Execute($sql);

        $eventCahce = [];

        while ($row = $rows->FetchRow())
        {
            $itm = new stdClass();
            $itm->ServerID = $row['server_id'];
            $itm->Message = $row['message'];
            $itm->Timestamp = strtotime($row['dtadded']);
            $itm->ScriptName = $row['script_name'];
            $itm->ExecTime = $row['exec_time'];
            $itm->ExecExitCode = $row['exec_exitcode'];

            if (!empty($row['event_id'])) {
                if (empty($eventCahce[$row['event_id']])) {
                    $eventCahce[$row['event_id']] = $this->DB->GetOne("SELECT `type` FROM events WHERE event_id=?", [$row['event_id']]);
                }

                if (stristr($eventCahce[$row['event_id']], 'CustomEvent')) {
                    $itm->Event = "Manual";
                } elseif (stristr($eventCahce[$row['event_id']], 'APIEvent')) {
                    $itm->Event = "API";
                } else {
                    $itm->Event = $eventCahce[$row['event_id']];
                }
            }

            $response->LogSet->Item[] = $itm;
        }

        return $response;
    }

    public function FarmRoleParametersList($FarmRoleID)
    {
        $this->restrictAccess(Acl::RESOURCE_ROLES_ENVIRONMENT);

        $response = $this->CreateInitialResponse();

        //consider exception

        return $response;
    }

    public function FarmRoleUpdateParameterValue($FarmRoleID, $ParamName, $ParamValue)
    {
        throw new Exception("FarmRole parameters are deprecated. Please use GlobalVariables instead.");
    }

    public function EnvironmentsList()
    {
        $response = $this->CreateInitialResponse();
        $response->EnvironmentSet = new stdClass();
        $response->EnvironmentSet->Item = array();

        $envs = $this->user->getEnvironments();
        foreach ($envs as $env) {
            $itm = new stdClass();
            $itm->ID = $env['id'];
            $itm->Name = $env['name'];

            $response->EnvironmentSet->Item[] = $itm;
        }

        return $response;
    }

    public function ServerGetExtendedInformation($ServerID)
    {
        $DBServer = DBServer::LoadByID($ServerID);

        if ($DBServer->envId != $this->Environment->id)
            throw new Exception(sprintf("Server ID #%s not found", $ServerID));

        $this->user->getPermissions()->validate($DBServer);

        $response = $this->CreateInitialResponse();

        $response->FarmInfo = new stdClass();
        $response->FarmInfo->ID = $DBServer->GetFarmObject()->ID;
        $response->FarmInfo->Name = $DBServer->GetFarmObject()->Name;

        $response->FarmRoleInfo = new stdClass();
        $response->FarmRoleInfo->ID = $DBServer->farmRoleId;
        $response->FarmRoleInfo->RoleID = $DBServer->GetFarmRoleObject()->RoleID;
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
            'Type' => $DBServer->getType(),
            'IsInitFailed' => $DBServer->GetProperty(SERVER_PROPERTIES::SZR_IS_INIT_FAILED),
            'IsRebooting' => $DBServer->GetProperty(SERVER_PROPERTIES::REBOOTING)
        );
        foreach ($scalrProps as $k=>$v) {
            $response->ServerInfo->{$k} = $v;
        }

        $response->PlatformProperties = new stdClass();

        try {
            $info = PlatformFactory::NewPlatform($DBServer->platform)->GetServerExtendedInformation($DBServer, false);
            if (is_array($info) && count($info)) {
                foreach ($info as $name => $value) {
                    $name = str_replace(".", "_", $name);
                    $name = preg_replace("/[^A-Za-z0-9_-]+/", "", $name);

                    if ($name == 'MonitoringCloudWatch')
                        continue;

                    $response->PlatformProperties->{$name} = $value;
                }
            }
        } catch (Exception $e) {
            // just ignoring
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
            $this->restrictAccess(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT);
        }

        try {
            if ($ServerID != '') {
                $DBServer = DBServer::LoadByID($ServerID);
                $DBFarmRole = $DBServer->GetFarmRoleObject();
                $DBFarm = $DBFarmRole->GetFarmObject();
                $scope = ScopeInterface::SCOPE_SERVER;
                $FarmRoleID = $DBFarmRole->ID;

            } else if ($FarmRoleID != 0) {
                $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
                $DBFarm = DBFarm::LoadByID($DBFarmRole->FarmID);
                $scope = ScopeInterface::SCOPE_FARMROLE;
                $ServerID = '';
            } elseif ($FarmID != 0) {
                $DBFarm = DBFarm::LoadByID($FarmID);
                $scope = ScopeInterface::SCOPE_FARM;
                $FarmRoleID = 0;
                $ServerID = '';
            } elseif ($RoleID != 0) {
                $DBRole = DBRole::loadById($RoleID);
                $scope = ScopeInterface::SCOPE_ROLE;
                $FarmRoleID = 0;
                $FarmID = 0;
                $ServerID = '';
            } else {
                throw new Exception("You must specify al least one of the following arguments: FarmID, FarmRoleID, RoleID or ServerID.");
            }

            if ($DBFarm) {
                if ($DBFarm->EnvID != $this->Environment->id) {
                    throw new Exception(sprintf("Farm ID #%s is not found.", $FarmID));
                }
                $this->user->getPermissions()->validate($DBFarm);
            } elseif ($DBRole) {
                if ($DBRole->envId != $this->Environment->id) {
                    throw new Exception(sprintf("Role ID #%s is not found.", $RoleID));
                }
                $this->user->getPermissions()->validate($DBRole);
            }

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        $ParamName = $this->stripValue($ParamName);
        if (!$ParamName)
            throw new Exception("Param name is required");

        $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, $scope);
        $globalVariables->doNotValidateNameCaseSensitivity = true;
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
            $this->restrictAccess(Acl::RESOURCE_GLOBAL_VARIABLES_ENVIRONMENT);
        }

        $response = $this->CreateInitialResponse();
        $response->VariableSet = new stdClass();
        $response->VariableSet->Item = array();

        if ($ServerID) {
            $DBServer = DBServer::LoadByID($ServerID);
            if ($DBServer->envId != $this->Environment->id)
                throw new Exception(sprintf("Server ID #%s not found", $ServerID));

            $this->user->getPermissions()->validate($DBServer);

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, ScopeInterface::SCOPE_SERVER);
            $vars = $globalVariables->listVariables($DBServer->GetFarmRoleObject()->RoleID, $DBServer->farmId, $DBServer->farmRoleId, $ServerID);
        } elseif ($FarmID) {
            $DBFarm = DBFarm::LoadByID($FarmID);
            if ($DBFarm->EnvID != $this->Environment->id)
                throw new Exception(sprintf("Farm ID #%s not found", $FarmID));

            $this->user->getPermissions()->validate($DBFarm);

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, ScopeInterface::SCOPE_FARM);
            $vars = $globalVariables->listVariables(null, $DBFarm->ID, null);
        } elseif ($RoleID) {
            $DBRole = DBRole::LoadByID($RoleID);
            if ($DBRole->envId != $this->Environment->id)
                throw new Exception(sprintf("Role ID #%s not found", $RoleID));

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, ScopeInterface::SCOPE_ROLE);
            $vars = $globalVariables->listVariables($RoleID, null, null);
        } elseif ($FarmRoleID) {
            $DBFarmRole = DBFarmRole::LoadByID($FarmRoleID);
            if ($DBFarmRole->GetFarmObject()->EnvID != $this->Environment->id)
                throw new Exception(sprintf("FarmRole ID #%s not found", $FarmRoleID));

            $this->user->getPermissions()->validate($DBFarmRole);

            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, ScopeInterface::SCOPE_FARMROLE);
            $vars = $globalVariables->listVariables($DBFarmRole->RoleID, $DBFarmRole->FarmID, $DBFarmRole->ID);
        } else {
            $globalVariables = new Scalr_Scripting_GlobalVariables($this->Environment->clientId, $this->Environment->id, ScopeInterface::SCOPE_ENVIRONMENT);
            $vars = $globalVariables->listVariables();
        }

        foreach ($vars as $v) {
            $itm = new stdClass();
            $itm->Name = $v['name'];
            $itm->Value = $v['value'];
            $itm->Private = $v['private'];

            $response->VariableSet->Item[] = $itm;
        }

        return $response;
    }

    public function DmSourcesList()
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }

    public function DmSourceCreate($Type, $URL, $AuthLogin=null, $AuthPassword=null)
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }

    public function DmApplicationCreate($Name, $SourceID, $PreDeployScript=null, $PostDeployScript=null)
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }

    public function DmApplicationsList()
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }

    public function DmDeploymentTasksList($FarmRoleID = null, $ApplicationID = null, $ServerID = null)
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }

    public function DmDeploymentTaskGetLog($DeploymentTaskID, $StartFrom = 0, $RecordsLimit = 20)
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }

    public function DmDeploymentTaskGetStatus($DeploymentTaskID)
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }

    public function DmApplicationDeploy($ApplicationID, $FarmRoleID, $RemotePath)
    {
        throw new Exception("Deployments feature was deprecated and no longer available.");
    }
}
