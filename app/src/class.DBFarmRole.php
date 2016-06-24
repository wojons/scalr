<?php

use Scalr\Model\Entity\OrchestrationRule;
use Scalr\Modules\PlatformFactory;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity;
use Scalr\DataType\ScopeInterface;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;

/**
 * Class DBFarmRole
 *
 * @deprecated
 * @see Scalr\Model\Entity\FarmRole
 */
class DBFarmRole
{
    //NOTE: Settings constants moved to Entity\FarmRoleSetting

    public
        $ID,
        $FarmID,
        $LaunchIndex,
        $RoleID,
        $Alias,
        $CloudLocation,
        $Platform;

    private $DB,
            $dbRole,
            $DBFarm,
            $SettingsCache = array();

    private static $FieldPropertyMap = array(
        'id'            => 'ID',
        'farmid'        => 'FarmID',
        'role_id'       => 'RoleID',
        'alias'         => 'Alias',
        'launch_index'  => 'LaunchIndex',
        'platform'      => 'Platform',
        'cloud_location'=> 'CloudLocation'
    );

    /**
     * Constructor
     * @param $instance_id
     * @return void
     */
    public function __construct($farm_roleid)
    {
        $this->DB = \Scalr::getDb();

        $this->ID = $farm_roleid;
    }

    public function __sleep()
    {
        return array_values(self::$FieldPropertyMap);
    }

    public function __wakeup()
    {
        $this->DB = \Scalr::getDb();
    }

    public function applyDefinition($definition, $reset = false)
    {
        $resetSettings = array(
            Entity\FarmRoleSetting::BALANCING_USE_ELB,
            Entity\FarmRoleSetting::BALANCING_HOSTNAME,
            Entity\FarmRoleSetting::BALANCING_NAME,
            Entity\FarmRoleSetting::BALANCING_HC_TIMEOUT,
            Entity\FarmRoleSetting::BALANCING_HC_TARGET,
            Entity\FarmRoleSetting::BALANCING_HC_INTERVAL,
            Entity\FarmRoleSetting::BALANCING_HC_UTH,
            Entity\FarmRoleSetting::BALANCING_HC_HTH,
            Entity\FarmRoleSetting::BALANCING_HC_HASH,
            Entity\FarmRoleSetting::BALANCING_AZ_HASH,

            Entity\FarmRoleSetting::CLOUDSTACK_STATIC_NAT_MAP,
            Entity\FarmRoleSetting::AWS_ELASIC_IPS_MAP,

            Entity\FarmRoleSetting::AWS_S3_BUCKET,
            Entity\FarmRoleSetting::MYSQL_PMA_USER,
            Entity\FarmRoleSetting::MYSQL_PMA_PASS,
            Entity\FarmRoleSetting::MYSQL_PMA_REQUEST_ERROR,
            Entity\FarmRoleSetting::MYSQL_PMA_REQUEST_TIME,
            Entity\FarmRoleSetting::MYSQL_LAST_BCP_TS,
            Entity\FarmRoleSetting::MYSQL_LAST_BUNDLE_TS,
            Entity\FarmRoleSetting::MYSQL_IS_BCP_RUNNING,
            Entity\FarmRoleSetting::MYSQL_IS_BUNDLE_RUNNING,
            Entity\FarmRoleSetting::MYSQL_BCP_SERVER_ID,
            Entity\FarmRoleSetting::MYSQL_BUNDLE_SERVER_ID,
            Entity\FarmRoleSetting::MYSQL_SLAVE_TO_MASTER,
            Entity\FarmRoleSetting::MYSQL_ROOT_PASSWORD,
            Entity\FarmRoleSetting::MYSQL_REPL_PASSWORD,
            Entity\FarmRoleSetting::MYSQL_STAT_PASSWORD,
            Entity\FarmRoleSetting::MYSQL_LOG_FILE,
            Entity\FarmRoleSetting::MYSQL_LOG_POS,
            Entity\FarmRoleSetting::MYSQL_SCALR_SNAPSHOT_ID,
            Entity\FarmRoleSetting::MYSQL_SCALR_VOLUME_ID,
            Entity\FarmRoleSetting::MYSQL_SNAPSHOT_ID,
            Entity\FarmRoleSetting::MYSQL_MASTER_EBS_VOLUME_ID,

            Entity\FarmRoleSetting::AWS_ELB_ID,
            Entity\FarmRoleSetting::AWS_ELB_ENABLED,

            Scalr_Db_Msr::DATA_BACKUP_IS_RUNNING,
            Scalr_Db_Msr::DATA_BACKUP_LAST_TS,
            Scalr_Db_Msr::DATA_BACKUP_SERVER_ID,
            Scalr_Db_Msr::DATA_BUNDLE_IS_RUNNING,
            Scalr_Db_Msr::DATA_BUNDLE_LAST_TS,
            Scalr_Db_Msr::DATA_BUNDLE_SERVER_ID,
            Scalr_Db_Msr::SLAVE_TO_MASTER,
            Scalr_Db_Msr::SNAPSHOT_ID,
            Scalr_Db_Msr::VOLUME_ID,


            Scalr_Role_Behavior_RabbitMQ::ROLE_COOKIE_NAME,
            Scalr_Role_Behavior_RabbitMQ::ROLE_PASSWORD,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_SERVER_ID,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUESTED,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_REQUEST_TIME,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_ERROR_MSG,
            Scalr_Role_Behavior_RabbitMQ::ROLE_CP_URL,

            Scalr_Role_Behavior_MongoDB::ROLE_PASSWORD,
            Scalr_Role_Behavior_MongoDB::ROLE_KEYFILE,
            Scalr_Role_Behavior_MongoDB::ROLE_CLUSTER_STATUS,
            Scalr_Role_Behavior_MongoDB::ROLE_CLUSTER_IS_REMOVING_SHARD_INDEX,
            Scalr_Role_Behavior_MongoDB::DATA_BUNDLE_IS_RUNNING,
            Scalr_Role_Behavior_MongoDB::DATA_BUNDLE_SERVER_ID,
            Scalr_Role_Behavior_MongoDB::DATA_BUNDLE_LAST_TS,

            Scalr_Role_Behavior_Router::ROLE_VPC_NID,
            Scalr_Role_Behavior_Router::ROLE_VPC_IP,
            Scalr_Role_Behavior_Router::ROLE_VPC_AID,
            Scalr_Role_Behavior_Router::ROLE_VPC_ROUTER_CONFIGURED
        );

        // Set settings
        foreach ($definition->settings as $key => $value) {
            if ($reset && in_array($key, $resetSettings))
                continue;
            $this->SetSetting($key, $value, Entity\FarmRoleSetting::TYPE_CFG);
        }

        //Farm Global Variables
        $variables = new Scalr_Scripting_GlobalVariables($this->GetFarmObject()->ClientID, $this->GetFarmObject()->EnvID, ScopeInterface::SCOPE_FARMROLE);
        $variables->setValues($definition->globalVariables, $this->RoleID, $this->FarmID, $this->ID);

        //Storage
        $this->getStorage()->setConfigs($definition->storage);

        // Scripts
        $scripts = array();
        foreach ($definition->scripts as $script) {
            $scripts[] = array(
                'params' => $script->params,
                'target' => $script->target,
                'order_index' => $script->orderIndex,
                'version' => (int) $script->version,
                'isSync' => (int) $script->isSync,
                'timeout' => $script->timeout,
                'event' => $script->event,
                'script_id' => (int) $script->scriptId,
                'script_path' => $script->scriptPath,
                'script_type' => $script->scriptType,
                'run_as' => $script->runAs,
                'target_roles' => $script->targetRoles,
                'target_farmroles' => $script->targetFarmroles,
                'target_behaviors' => $script->targetBehaviors
            );
        }
        $this->SetScripts($scripts);

        // Scaling times
        $this->DB->Execute("DELETE FROM farm_role_scaling_times WHERE farm_roleid=?",
            array($this->ID)
        );

        foreach ($definition->scalingTimes as $scalingPeriod) {
            $this->DB->Execute("INSERT INTO farm_role_scaling_times SET
                farm_roleid     = ?,
                start_time      = ?,
                end_time        = ?,
                days_of_week    = ?,
                instances_count = ?
            ", array(
                $this->ID,
                $scalingPeriod->startTime,
                $scalingPeriod->endTime,
                $scalingPeriod->daysOfWeek,
                $scalingPeriod->instanceCount
            ));
        }

        // metrics
        $scalingManager = new Scalr_Scaling_Manager($this);
        $metrics = array();
        foreach ($definition->scalingMetrics as $metric)
            $metrics[$metric->metricId] = $metric->settings;

        $scalingManager->setFarmRoleMetrics($metrics);

        return true;
    }

    public function getDefinition()
    {
        $roleDefinition = new stdClass();
        $roleDefinition->roleId = $this->RoleID;
        $roleDefinition->platform = $this->Platform;
        $roleDefinition->cloudLocation = $this->CloudLocation;
        $roleDefinition->alias = $this->Alias;
        $roleDefinition->launchIndex = $this->LaunchIndex;

        // Settings
        $roleDefinition->settings = array();
        foreach ($this->GetAllSettings() as $k=>$v) {
            $roleDefinition->settings[$k] = $v;
        }

        //Farm Global Variables
        $variables = new Scalr_Scripting_GlobalVariables($this->GetFarmObject()->ClientID, $this->GetFarmObject()->EnvID, ScopeInterface::SCOPE_FARMROLE);
        $roleDefinition->globalVariables = $variables->getValues($this->RoleID, $this->FarmID, $this->ID);

        //Storage
        $storage = $this->getStorage();
        $configs = $storage->getConfigs();
        if (!empty($configs)) {
            foreach ($configs as $cfg) {
                $cfg = (array)$cfg;
                unset($cfg['id']);
                unset($cfg['status']);
                $roleDefinition->storage[] = $cfg;
            }
        }

        // Scripts
        $scripts = $this->DB->GetAll("SELECT * FROM farm_role_scripts WHERE farm_roleid=? AND issystem='1'", array($this->ID));
        $roleDefinition->scripts = array();
        foreach ($scripts as $script) {
            $itm = new stdClass();
            $itm->event = $script['event_name'];
            $itm->scriptId = (int) $script['scriptid'];
            $itm->params = unserialize($script['params']);
            $itm->target = $script['target'];
            $itm->version = (int) $script['version'];
            $itm->timeout = $script['timeout'];
            $itm->isSync = $script['issync'];
            $itm->isMenuItem = $script['ismenuitem'];
            $itm->orderIndex = $script['order_index'];
            $itm->scriptPath = $script['script_path'];
            $itm->scriptType = $script['script_type'];
            $itm->runAs = $script['run_as'];

            switch ($script['target']) {
                case $script['target'] == Script::TARGET_ROLES:
                    $varName = 'targetRoles';
                    break;
                case $script['target'] == Script::TARGET_FARMROLES:
                    $varName = 'targetFarmroles';
                    break;
                case $script['target'] == Script::TARGET_BEHAVIORS:
                    $varName = 'targetBehaviors';
                    break;
            }

            $targets = $this->DB->GetAll("SELECT target FROM `farm_role_scripting_targets` WHERE farm_role_script_id = ?", array($script['id']));
            foreach ($targets as $target)
                $itm->{$varName}[] = $target['target'];

            $roleDefinition->scripts[] = $itm;
        }

        // Scaling times
        $scalingTimes = $this->DB->GetAll("SELECT * FROM farm_role_scaling_times WHERE farm_roleid = ?", array($this->ID));
        $roleDefinition->scalingTimes = array();
        foreach ($scalingTimes as $time) {
            $itm = new stdClass();
            $itm->startTime = $time['start_time'];
            $itm->endTime = $time['end_time'];
            $itm->daysOfWeek = $time['days_of_week'];
            $itm->instanceCount = $time['instances_count'];

            $roleDefinition->scalingTimes[] = $itm;
        }

        // Scaling metrics
        $scalingMetrics = $this->DB->GetAll("SELECT * FROM farm_role_scaling_metrics WHERE farm_roleid = ?", array($this->ID));
        $roleDefinition->scalingMetrics = array();
        foreach ($scalingMetrics as $metric) {
            $itm = new stdClass();
            $itm->metricId = $metric['metric_id'];
            $itm->settings = unserialize($metric['settings']);

            $roleDefinition->scalingMetrics[] = $itm;
        }

        return $roleDefinition;
    }

    /**
     *
     * Returns DBFarmRole object by id
     * @param $id
     * @return DBFarmRole
     */
    public static function LoadByID($id)
    {
        $db = \Scalr::getDb();

        $farm_role_info = $db->GetRow("SELECT * FROM farm_roles WHERE id=?", array($id));
        if (!$farm_role_info)
            throw new Exception(sprintf(_("Farm Role ID #%s not found"), $id));

        $DBFarmRole = new DBFarmRole($farm_role_info['id']);
        foreach (self::$FieldPropertyMap as $k=>$v)
            $DBFarmRole->{$v} = $farm_role_info[$k];

        return $DBFarmRole;
    }

    /**
     * Load DBInstance by database id
     * @param $id
     * @return DBFarmRole
     */
    public static function Load($farmid, $roleid, $cloudLocation)
    {
        $db = \Scalr::getDb();

        $farm_role_info = $db->GetRow("SELECT * FROM farm_roles WHERE farmid=? AND role_id=? AND cloud_location=? LIMIT 1", array($farmid, $roleid, $cloudLocation));
        if (!$farm_role_info)
            throw new Exception(sprintf(_("Role #%s is not assigned to farm #%s"), $roleid, $farmid));

        $DBFarmRole = new DBFarmRole($farm_role_info['id']);
        foreach (self::$FieldPropertyMap as $k=>$v)
            $DBFarmRole->{$v} = $farm_role_info[$k];

        return $DBFarmRole;
    }

    /**
     * Returns DBFarm Object
     * @return DBFarm
     */
    public function GetFarmObject()
    {
        if (!$this->DBFarm)
            $this->DBFarm = DBFarm::LoadByID($this->FarmID);

        return $this->DBFarm;
    }

    /**
     *
     * @return DBRole
     */
    public function GetRoleObject()
    {
        if (!$this->dbRole)
            $this->dbRole = DBRole::loadById($this->RoleID);

        return $this->dbRole;
    }

    /**
     * Returns role prototype
     * @return string
     */
    public function GetRoleID()
    {
        return $this->RoleID;
    }

    public function GetPendingInstancesCount()
    {
        return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status IN(?,?,?,?) AND farm_roleid=? LIMIT 1",
            array(\SERVER_STATUS::INIT, \SERVER_STATUS::PENDING, \SERVER_STATUS::PENDING_LAUNCH, \SERVER_STATUS::RESUMING, $this->ID)
        );
    }

    public function GetSuspendedInstancesCount()
    {
        return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status IN(?,?) AND farm_roleid=? LIMIT 1",
            array(\SERVER_STATUS::SUSPENDED, \SERVER_STATUS::PENDING_SUSPEND, $this->ID)
        );
    }

    public function GetRunningInstancesCount()
    {
        $considerSuspendedServers = $this->GetSetting(Scalr_Role_Behavior::ROLE_BASE_CONSIDER_SUSPENDED);

        if (!$considerSuspendedServers || $considerSuspendedServers == 'running')
            return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status IN (?,?,?) AND farm_roleid=? LIMIT 1",
                array(\SERVER_STATUS::RUNNING, \SERVER_STATUS::PENDING_SUSPEND, \SERVER_STATUS::SUSPENDED, $this->ID)
            );
        else
            return $this->DB->GetOne("SELECT COUNT(*) FROM servers WHERE status = ? AND farm_roleid=? LIMIT 1",
                array(\SERVER_STATUS::RUNNING, $this->ID)
            );
    }

    public function GetServersByFilter($filter_args = array(), $ufilter_args = array())
    {
        $sql = "SELECT server_id FROM servers WHERE `farm_roleid`=?";
        $args = array($this->ID);
        foreach ((array)$filter_args as $k=>$v)
        {
            if (is_array($v))
            {
                foreach ($v as $vv)
                    array_push($args, $vv);

                $sql .= " AND `{$k}` IN (".implode(",", array_fill(0, count($v), "?")).")";
            }
            else
            {
                $sql .= " AND `{$k}`=?";
                array_push($args, $v);
            }
        }

        foreach ((array)$ufilter_args as $k=>$v)
        {
            if (is_array($v))
            {
                foreach ($v as $vv)
                    array_push($args, $vv);

                $sql .= " AND `{$k}` NOT IN (".implode(",", array_fill(0, count($v), "?")).")";
            }
            else
            {
                $sql .= " AND `{$k}`!=?";
                array_push($args, $v);
            }
        }

        $res = $this->DB->GetAll($sql, $args);

        $retval = array();
        foreach ((array)$res as $i)
        {
            if ($i['server_id'])
                $retval[] = DBServer::LoadByID($i['server_id']);
        }

        return $retval;
    }

    public function GetServiceConfiguration2($behavior)
    {
        $config = array();
        $cfg = $this->DB->Execute("SELECT * FROM farm_role_config_presets WHERE farm_roleid = ? AND behavior = ?", array($this->ID, $behavior));
        while ($c = $cfg->FetchRow())
            $config[$c['cfg_filename']][$c['cfg_key']] = $c['cfg_value'];

        return $config;
    }

    public function SetServiceConfiguration($behavior, $config) {
        $this->DB->BeginTrans();
        try  {
            $this->DB->Execute("DELETE FROM farm_role_config_presets WHERE farm_roleid = ? AND behavior = ?", array($this->ID, $behavior));
            foreach ($config as $configFile => $cfg) {
                foreach ($cfg as $k => $v) {
                    $this->DB->Execute("INSERT INTO farm_role_config_presets SET
                        farm_roleid = ?,
                        behavior = ?,
                        cfg_filename = ?,
                        cfg_key = ?,
                        cfg_value =?
                    ", array($this->ID, $behavior, $configFile, $k, $v));
                }
            }
        } catch (Exception $e) {
            $this->DB->RollbackTrans();
            throw $e;
        }
        $this->DB->CommitTrans();

        return true;
    }

    public function SetScripts(array $scripts, array $params = array())
    {

        if (count($params) > 0) {
            foreach ($params as $param) {
                if (isset($param['hash']) && count($param['params']) > 0) {
                    $roleId = $this->RoleID;
                    $roleParams = $this->DB->GetOne("SELECT params FROM role_scripts WHERE role_id = ? AND `hash` = ? LIMIT 1", array($roleId, $param['hash']));
                    $newParams = serialize($param['params']);
                    if ($newParams != $roleParams) {
                        //UNIQUE KEY `uniq` (`farm_role_id`,`hash`,`farm_role_script_id`),
                        $this->DB->Execute("
                            INSERT INTO farm_role_scripting_params
                            SET farm_role_id = ?,
                                `hash` = ?,
                                farm_role_script_id = ?,
                                role_script_id = ?,
                                params = ?
                            ON DUPLICATE KEY UPDATE
                                role_script_id = ?,
                                params = ?
                        ", array(
                            $this->ID,
                            $param['hash'],
                            0,
                            0, $newParams,
                            0, $newParams,
                        ));
                    }
                }
            }
        }

        $this->DB->Execute("DELETE FROM farm_role_scripts WHERE farm_roleid=?", array($this->ID));

        if (count($scripts) > 0) {
            foreach ($scripts as $script) {

                $timeout = empty($script['timeout']) ? 0 : intval($script['timeout']);

                if (!$timeout)
                    $timeout = \Scalr::config('scalr.script.timeout.sync');

                $event_name = isset($script['event']) ? $script['event'] : null;

                if ($event_name == 'AllEvents')
                    $event_name = '*';

                if ($event_name && (
                    !empty($script['script_id']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_SCALR ||
                    !empty($script['script_path']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_LOCAL ||
                    !empty($script['params']) && $script['script_type'] == Scalr_Scripting_Manager::ORCHESTRATION_SCRIPT_TYPE_CHEF))
                {
                    $this->DB->Execute("INSERT INTO farm_role_scripts SET
                        scriptid    = ?,
                        farmid      = ?,
                        farm_roleid = ?,
                        params      = ?,
                        event_name  = ?,
                        target      = ?,
                        version     = ?,
                        timeout     = ?,
                        issync      = ?,
                        order_index = ?,
                        issystem    = '1',
                        script_path = ?,
                        run_as      = ?,
                        script_type = ?
                    ", array(
                        $script['script_id'],
                        $this->FarmID,
                        $this->ID,
                        serialize($script['params']),
                        $event_name,
                        $script['target'],
                        $script['version'],
                        $timeout,
                        $script['isSync'],
                        (int)$script['order_index'],
                        $script['script_path'],
                        $script['run_as'],
                        $script['script_type']
                    ));

                    $farmRoleScriptId = $this->DB->Insert_ID();

                    if ($script['target'] == Script::TARGET_ROLES || $script['target'] == Script::TARGET_BEHAVIORS || $script['target'] == Script::TARGET_FARMROLES) {
                        switch ($script['target']) {
                            case $script['target'] == Script::TARGET_ROLES:
                                $targetType = OrchestrationRule::TARGET_ROLES;
                                $varName = 'target_roles';
                                break;
                            case $script['target'] == Script::TARGET_FARMROLES:
                                $targetType = OrchestrationRule::TARGET_ROLES;
                                $varName = 'target_farmroles';
                                break;
                            case $script['target'] == Script::TARGET_BEHAVIORS:
                                $targetType = OrchestrationRule::TARGET_BEHAVIOR;
                                $varName = 'target_behaviors';
                                break;
                        }

                        if (is_array($script[$varName])) {
                            foreach ($script[$varName] as $t) {

                                // Workaround to be able to specify self role when it's not yet saved in farm.
                                if ($t == '*self*')
                                    $t = $this->ID;

                                $this->DB->Execute("INSERT INTO farm_role_scripting_targets SET
                                    `farm_role_script_id` = ?,
                                    `target_type` = ?,
                                    `target` =?
                                ", array(
                                    $farmRoleScriptId, $targetType, $t
                                ));
                            }
                        }
                    }
                }
            }
        }
    }

    public function isOpenstack()
    {
        return PlatformFactory::isOpenstack($this->Platform);
    }

    public function isCloudstack()
    {
        return PlatformFactory::isCloudstack($this->Platform);
    }

    /**
     * @return Scalr\Farm\Role\FarmRoleStorage
     */
    public function getStorage()
    {
        $storage = new \Scalr\Farm\Role\FarmRoleStorage($this);
        return $storage;
    }

    /**
     * Apply FarmRole global variables to a value
     *
     * @return string
     */
    public function applyGlobalVarsToValue($value)
    {
        if (empty($this->globalVariablesCache)) {
            $formats = \Scalr::config("scalr.system.global_variables.format");

            $systemVars = array(
                'env_id'            => $this->GetFarmObject()->EnvID,
                'env_name'          => $this->GetFarmObject()->GetEnvironmentObject()->name,
                'farm_team'         => $this->GetFarmObject()->teamId ? (new Scalr_Account_Team())->loadById($this->GetFarmObject()->teamId)->name : '',
                'farm_id'           => $this->GetFarmObject()->ID,
                'farm_name'         => $this->GetFarmObject()->Name,
                'farm_hash'         => $this->GetFarmObject()->Hash,
                'farm_owner_email'  => $this->GetFarmObject()->createdByUserEmail,
                'farm_role_id'      => $this->ID,
                'farm_role_alias'   => $this->Alias,
                'cloud_location'    => $this->CloudLocation
            );

            if (\Scalr::getContainer()->analytics->enabled) {
                $projectId = $this->GetFarmObject()->GetSetting(Entity\FarmSetting::PROJECT_ID);

                if ($projectId) {
                    /* @var $project ProjectEntity */
                    $project = ProjectEntity::findPk($projectId);

                    $systemVars['project_id'] = $projectId;
                    $systemVars['project_bc'] = $project->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE);
                    $systemVars['project_name'] = $project->name;

                    $ccId = $project->ccId;
                }

                if ($ccId) {
                    /* @var $cc CostCentreEntity */
                    $cc = CostCentreEntity::findPk($ccId);

                    if ($cc) {
                        $systemVars['cost_center_id'] = $ccId;
                        $systemVars['cost_center_bc'] = $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE);
                        $systemVars['cost_center_name'] = $cc->name;
                    } else {
                        throw new Exception("Cost center {$ccId} not found");
                    }
                }
            }

            // Get list of Server system vars
            foreach ($systemVars as $name => $val) {
                $name = "SCALR_".strtoupper($name);
                $val = trim($val);

                if (isset($formats[$name])) {
                    $val = @sprintf($formats[$name], $val);
                }

                $this->globalVariablesCache[$name] = $val;
            }

            // Add custom variables
            $gv = new Scalr_Scripting_GlobalVariables($this->GetFarmObject()->ClientID, $this->GetFarmObject()->EnvID, ScopeInterface::SCOPE_FARMROLE);

            $vars = $gv->listVariables($this->RoleID, $this->FarmID, $this->ID);

            foreach ($vars as $v) {
                $this->globalVariablesCache[$v['name']] = $v['value'];
            }
        }

        //Parse variable
        $keys = array_map(function ($item) {
            return "{" . $item . "}";
        }, array_keys($this->globalVariablesCache));

        $values = array_values($this->globalVariablesCache);

        $retval = str_replace($keys, $values, $value);

        // Strip undefined variables & return value
        return preg_replace("/{[A-Za-z0-9_-]+}/", "", $retval);
    }

    /**
     * Returns all role settings
     * @return unknown_type
     */
    public function GetAllSettings()
    {
        return $this->GetSettingsByFilter();
    }

    /**
     * Set farm role setting
     *
     * @param   string       $name   The name of the setting
     * @param   string|null  $value  The value of the setting
     * @param   string       $type   optional
     * @return  void
     */
    public function SetSetting($name, $value, $type = null)
    {
        if ($value === "" || $value === null) {
            $this->DB->Execute("DELETE FROM farm_role_settings WHERE name=? AND farm_roleid=?", array(
                $name, $this->ID
            ));
        } else {
            $this->DB->Execute("
                INSERT INTO farm_role_settings
                SET `name`=?,
                    `value`=?,
                    `farm_roleid`=?,
                    `type`=?
                ON DUPLICATE KEY UPDATE
                    `value`=?,
                    `type`=?
            ", array(
                $name,
                $value,
                $this->ID,
                $type,

                $value,
                $type)
            );
        }

        $this->SettingsCache[$name] = $value;

        return true;
    }

    /**
     * Get Role setting by name
     *
     * @param string $name
     * @return mixed
     */
    public function GetSetting($name)
    {
        if (!isset($this->SettingsCache[$name])) {
            $this->SettingsCache[$name] = $this->DB->GetOne("
                SELECT value
                FROM farm_role_settings
                WHERE name=? AND farm_roleid=?
                LIMIT 1
            ",[$name, $this->ID]);
        }

        return $this->SettingsCache[$name];
    }

    public function GetSettingsByFilter($filter = "")
    {
        $settings = $this->DB->GetAll("SELECT * FROM farm_role_settings WHERE farm_roleid=? AND name LIKE '%{$filter}%'", array($this->ID));
        $retval = array();
        foreach ($settings as $setting)
            $retval[$setting['name']] = $setting['value'];

        $this->SettingsCache = array_merge($this->SettingsCache, $retval);

        return $retval;
    }

    public function ClearSettings($filter = "")
    {
        $this->DB->Execute("DELETE FROM farm_role_settings WHERE name LIKE '%{$filter}%' AND farm_roleid=?",
            array($this->ID)
        );

        $this->SettingsCache = array();
    }

    private function Unbind()
    {
        $row = array();
        foreach (self::$FieldPropertyMap as $field => $property) {
            $row[$field] = $this->{$property};
        }

        return $row;
    }

    function Save()
    {
        $row = $this->Unbind();

        unset($row['id']);

        // Prepare SQL statement
        $set = array();
        $bind = array();
        foreach ($row as $field => $value) {
            $set[] = "`$field` = ?";
            $bind[] = $value;
        }
        $set = join(', ', $set);

        try {
            // Perform Update
            $bind[] = $this->ID;
            $this->DB->Execute("UPDATE farm_roles SET $set WHERE id = ?", $bind);
        } catch (Exception $e) {
            throw new Exception("Cannot save farm role. Error: " . $e->getMessage(), $e->getCode());
        }

        if (!empty($this->ID) && !empty($this->FarmID) && \Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                $this->GetFarmObject()->ClientID, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_FARM_ROLE, $this->ID,
                sprintf('%s', $this->Alias)
            );
        }
    }

    public function getReplacementRoles($includeSelf = false)
    {
        $dbRole = $this->GetRoleObject();

        $roles_sql = "
            SELECT r.id
            FROM roles r
            INNER JOIN role_images ri ON r.id = ri.role_id
            INNER JOIN os ON os.id = r.os_id
            LEFT JOIN role_environments re ON re.role_id = r.id
            WHERE r.generation = '2' AND (r.client_id IS NULL OR r.client_id = ? AND r.env_id IS NULL AND (re.env_id IS NULL OR re.env_id = ?) OR r.env_id = ?)
            AND ri.platform = ? " .
            (in_array($this->Platform, array(SERVER_PLATFORMS::GCE)) ? '' : "AND ri.cloud_location = ? ") .
            "AND os.family = ? " .
            ($dbRole->isScalarized == 1 ? ' AND r.is_scalarized = 1 ' : '') .
            ($includeSelf ? '' : "AND r.id != ? ") .
            "GROUP BY r.id"
        ;
        $args = array($this->GetFarmObject()->ClientID, $this->GetFarmObject()->EnvID, $this->GetFarmObject()->EnvID, $this->Platform);

        if (!in_array($this->Platform, array(SERVER_PLATFORMS::GCE)))
            $args[] = $this->CloudLocation;

        $args[] = $dbRole->getOs()->family;

        if ($includeSelf)
            $args[] = $dbRole->id;


        $behaviors = $dbRole->getBehaviors();

        if (in_array(ROLE_BEHAVIORS::CHEF, $behaviors) &&
            $dbRole->getProperty(Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP) != 1 &&
            $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP) != 1
        ) {
            // role has chef behavior, but doesn't use chef, remove chef from list of behaviors
            $behaviors = array_diff($behaviors, [ROLE_BEHAVIORS::CHEF]);
        }
        sort($behaviors);
        $hasChef = in_array(ROLE_BEHAVIORS::CHEF, $behaviors);

        $result = array();
        foreach ($this->DB->GetCol($roles_sql, $args) as $roleId) {
            $role = DBRole::loadById($roleId);
            $behaviors2 = $role->getBehaviors();

            if (!$hasChef) {
                $behaviors2 = array_diff($behaviors2, [ROLE_BEHAVIORS::CHEF]);
            }

            sort($behaviors2);

            if ($behaviors == $behaviors2 || $dbRole->isScalarized == 0) {
                $image = $role->__getNewRoleObject()->getImage($this->Platform, $this->CloudLocation)->getImage();
                if ($image) {
                    $result[] = array(
                        'id'            => $role->id,
                        'name'          => $role->name,
                        'osId'          => $role->osId,
                        'behaviors'     => $role->getBehaviors(),
                        'scope'         => $role->__getNewRoleObject()->getScope(),
                        'image'         => [
                            'id' => $image->id,
                            'type' => $image->type,
                            'architecture' => $image->architecture
                        ]
                    );
                }
            } else {
                //var_dump($behaviors2);
            }
        }

        return $result;
    }

    public function getChefSettings()
    {
        if ($this->GetRoleObject()->getProperty(Scalr_Role_Behavior_Chef::ROLE_CHEF_BOOTSTRAP)) {
            $result = $this->GetRoleObject()->getProperties('chef.');
            if ($this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ATTRIBUTES)) {
                $result[Scalr_Role_Behavior_Chef::ROLE_CHEF_ATTRIBUTES] = $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ATTRIBUTES);
            }
            if ($this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_LOG_LEVEL)) {
                $result[Scalr_Role_Behavior_Chef::ROLE_CHEF_LOG_LEVEL] = $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_LOG_LEVEL);
            }
            if ($this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT)) {
                $result[Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT] = $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_ENVIRONMENT);
            }
            if ($result[Scalr_Role_Behavior_Chef::ROLE_CHEF_ALLOW_TO_APPEND_RUNLIST] == 1) {
                $result[Scalr_Role_Behavior_Chef::ROLE_CHEF_RUNLIST_APPEND] = $this->GetSetting(Scalr_Role_Behavior_Chef::ROLE_CHEF_RUNLIST_APPEND);
            }
        } else {
            $result = $this->GetSettingsByFilter('chef.');
        }

        return $result;
    }

    /**
     * Gets the status of the farm which corresponds to the farm role
     *
     * @return  int|null  Returns the status of the farm which corresponds to the farm role.
     *                    It returns NULL if farm does not exist.
     */
    public function getFarmStatus()
    {
        return $this->DB->GetOne("SELECT f.status FROM `farms` f WHERE f.id = ? LIMIT 1", [
            $this->FarmID,
        ]);
    }

    /**
     * Returns instance type id
     *
     * @return mixed
     */
    public function getInstanceType()
    {
        return $this->GetSetting(Entity\FarmRoleSetting::INSTANCE_TYPE);
    }

}
