<?php

use Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Exception\AnalyticsException;
use Scalr\Util\CryptoTool;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Model\Entity;
use Scalr\DataType\ScopeInterface;

/**
 * Class DBFarm
 *
 * @deprecated
 * @see Scalr\Model\Entity\Farm
 */
class DBFarm
{
    public
        $ID,
        $ClientID,
        $EnvID,
        $Name,
        $Hash,
        $Status,
        $Comments,
        $RolesLaunchOrder,
        $ScalarizrCertificate,
        $TermOnSyncFail,

        $createdByUserId,
        $createdByUserEmail,
        $changedByUserId,
        $changedTime,
        $teamId;

    private $DB,
            $environment;

    private $SettingsCache = array();

    private static $FieldPropertyMap = array(
        'id' 			=> 'ID',
        'clientid'		=> 'ClientID',
        'env_id'		=> 'EnvID',
        'name'			=> 'Name',
        'hash'			=> 'Hash',
        'status'		=> 'Status',
        'comments'		=> 'Comments',
        'scalarizr_cert'=> 'ScalarizrCertificate',
        'farm_roles_launch_order'	=> 'RolesLaunchOrder',
        'term_on_sync_fail'	=> 'TermOnSyncFail',

        'created_by_id' 	=> 'createdByUserId',
        'created_by_email'	=> 'createdByUserEmail',
        'changed_by_id'     => 'changedByUserId',
        'changed_time'      => 'changedTime',
        'team_id'     => 'teamId',
    );

    /**
     * Constructor
     * @param $instance_id
     * @return void
     */
    public function __construct($id = null)
    {
        $this->ID = $id;
        $this->DB = \Scalr::getDb();
    }

    public function __sleep()
    {
        return array_values(self::$FieldPropertyMap);
    }

    public function __wakeup()
    {
        $this->DB = \Scalr::getDb();
    }

    /**
     * Initializes a new farm
     *
     * TODO: Rewrite this terrible code.
     *
     * @param   string             $name  The name of the farm
     * @param   Scalr_Account_User $user  The user
     * @param   int                $envId The identifier of the environment
     * @return  DBFarm
     */
    public static function create($name, Scalr_Account_User $user, $envId)
    {
        $account = $user->getAccount();
        $account->validateLimit(Scalr_Limits::ACCOUNT_FARMS, 1);

        $dbFarm = new self();
        $dbFarm->Status = FARM_STATUS::TERMINATED;
        $dbFarm->ClientID = $account->id;
        $dbFarm->EnvID = $envId;

        $dbFarm->createdByUserId = $user->getId();
        $dbFarm->createdByUserEmail = $user->getEmail();
        $dbFarm->changedByUserId = $user->getId();
        $dbFarm->changedTime = microtime();

        $dbFarm->Name = $name;
        $dbFarm->RolesLaunchOrder = 0;
        $dbFarm->Comments = "";

        $dbFarm->save();

        $dbFarm->SetSetting(Entity\FarmSetting::CRYPTO_KEY, Scalr::GenerateRandomKey(40));

        return $dbFarm;
    }

    /**
     * Creates clone for the farm
     *
     * @param   string             $name   The name of the farm
     * @param   Scalr_Account_User $user   The user object
     * @param   int                $envId  The identifier of the environment
     * @return  DBFarm             Returns clone
     */
    public function cloneFarm($name = false, Scalr_Account_User $user, $envId)
    {
        $account = $user->getAccount();
        $account->validateLimit(Scalr_Limits::ACCOUNT_FARMS, 1);

        $definition = $this->getDefinition();

        if (!$name) {
            $template = "";
            if (!preg_match('/^(.*?)\(clone \#([0-9]*)\)$/si', $definition->name)) {
                $name = $definition->name;
            } else {
                preg_match('/^(.*?)\(clone \#([0-9]*)\)$/si', $definition->name, $matches);
                $name = trim($matches[1]);
            }

            $name = preg_replace('/[^A-Za-z0-9_\. -]+/', '', $name);
            $lastUsedIndex = $this->DB->GetOne('SELECT MAX(CAST((SUBSTR(@p:=SUBSTRING_INDEX(name, \'#\', -1), 1, LENGTH(@p) - 1)) AS UNSIGNED)) as lastUsedCloneNumber FROM farms WHERE name REGEXP \''. str_replace('.', '\.', $name) .' \\\\(clone #[0-9]+\\\\)\' AND env_id = ?', array(
                $envId
            ));
            $name = $name . ' (clone #' . ($lastUsedIndex + 1) . ')';
        }

        $dbFarm = self::create($name, $user, $envId);

        $dbFarm->createdByUserId = $user->id;
        $dbFarm->createdByUserEmail = $user->getEmail();
        $dbFarm->RolesLaunchOrder = $definition->rolesLaunchOrder;
        $dbFarm->teamId = $definition->teamId;

        $dbFarm->SetSetting(Entity\FarmSetting::TIMEZONE, $definition->settings[Entity\FarmSetting::TIMEZONE]);
        $dbFarm->SetSetting(Entity\FarmSetting::EC2_VPC_ID, $definition->settings[Entity\FarmSetting::EC2_VPC_ID]);
        $dbFarm->SetSetting(Entity\FarmSetting::EC2_VPC_REGION, $definition->settings[Entity\FarmSetting::EC2_VPC_REGION]);
        $dbFarm->SetSetting(Entity\FarmSetting::SZR_UPD_REPOSITORY, $definition->settings[Entity\FarmSetting::SZR_UPD_REPOSITORY]);
        $dbFarm->SetSetting(Entity\FarmSetting::SZR_UPD_SCHEDULE, $definition->settings[Entity\FarmSetting::SZR_UPD_SCHEDULE]);
        $dbFarm->SetSetting(Entity\FarmSetting::LEASE_STATUS, $definition->settings[Entity\FarmSetting::LEASE_STATUS]);
        if ($definition->settings[Entity\FarmSetting::PROJECT_ID]) {
            $dbFarm->SetSetting(Entity\FarmSetting::PROJECT_ID, $definition->settings[Entity\FarmSetting::PROJECT_ID]);
        }

        $variables = new Scalr_Scripting_GlobalVariables($dbFarm->ClientID, $envId, ScopeInterface::SCOPE_FARM);
        $variables->setValues($definition->globalVariables, 0, $dbFarm->ID, 0);

        foreach ($definition->roles as $index => $role) {
            $dbFarmRole = $dbFarm->AddRole(DBRole::loadById($role->roleId), $role->platform, $role->cloudLocation, $role->launchIndex, $role->alias);
            $oldRoleSettings = $dbFarmRole->GetAllSettings();
            $dbFarmRole->applyDefinition($role, true);
            $newSettings = $dbFarmRole->GetAllSettings();

            // Platform specified updates
            if ($dbFarmRole->Platform == SERVER_PLATFORMS::EC2) {
                \Scalr\Modules\Platforms\Ec2\Helpers\EbsHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $newSettings);
                \Scalr\Modules\Platforms\Ec2\Helpers\EipHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $newSettings);
                \Scalr\Modules\Platforms\Ec2\Helpers\ElbHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $newSettings);
            }

            if (in_array($dbFarmRole->Platform, array(SERVER_PLATFORMS::IDCF, SERVER_PLATFORMS::CLOUDSTACK))) {
                CloudstackHelper::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $newSettings);
            }

            $dbFarmRolesList[] = $dbFarmRole;
            $usedPlatforms[$role->platform] = 1;
        }

        $dbFarm->save();

        return $dbFarm;
    }

    public function applyGlobalVarsToValue($value)
    {
        if (empty($this->globalVariablesCache)) {
            $formats = \Scalr::config("scalr.system.global_variables.format");

            $systemVars = array(
                'env_id'		=> $this->EnvID,
                'env_name'		=> $this->GetEnvironmentObject()->name,
                'farm_team'     => $this->teamId ? (new Scalr_Account_Team())->loadById($this->teamId)->name : '',
                'farm_id'       => $this->ID,
                'farm_name'     => $this->Name,
                'farm_hash'     => $this->Hash,
                'farm_owner_email' => $this->createdByUserEmail
            );

            if (\Scalr::getContainer()->analytics->enabled) {
                $projectId = $this->GetSetting(Entity\FarmSetting::PROJECT_ID);
                if ($projectId) {
                    $project = ProjectEntity::findPk($projectId);
                    /* @var $project ProjectEntity */
                    $systemVars['project_id'] = $projectId;
                    $systemVars['project_bc'] = $project->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE);
                    $systemVars['project_name'] = $project->name;

                    $ccId = $project->ccId;
                }

                if ($ccId) {
                    $cc = CostCentreEntity::findPk($ccId);
                    if ($cc) {
                        /* @var $cc CostCentreEntity */
                        $systemVars['cost_center_id'] = $ccId;
                        $systemVars['cost_center_bc'] = $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE);
                        $systemVars['cost_center_name'] = $cc->name;
                    } else
                        throw new Exception("Cost center {$ccId} not found");
                }
            }

            // Get list of Server system vars
            foreach ($systemVars as $name => $val) {
                $name = "SCALR_".strtoupper($name);
                $val = trim($val);

                if (isset($formats[$name]))
                    $val = @sprintf($formats[$name], $val);

                $this->globalVariablesCache[$name] = $val;
            }

            // Add custom variables
            $gv = new Scalr_Scripting_GlobalVariables($this->ClientID, $this->EnvID, ScopeInterface::SCOPE_FARM);
            $vars = $gv->listVariables(0, $this->ID);
            foreach ($vars as $v)
                $this->globalVariablesCache[$v['name']] = $v['value'];
        }

        //Parse variable
        $keys = array_keys($this->globalVariablesCache);
        $f = create_function('$item', 'return "{".$item."}";');
        $keys = array_map($f, $keys);
        $values = array_values($this->globalVariablesCache);

        $retval = str_replace($keys, $values, $value);

        // Strip undefined variables & return value
        return preg_replace("/{[A-Za-z0-9_-]+}/", "", $retval);
    }

    public function getDefinition()
    {
        $farmDefinition = new stdClass();
        $farmDefinition->name = $this->Name;
        $farmDefinition->rolesLaunchOrder = $this->RolesLaunchOrder;
        $farmDefinition->teamId = $this->teamId;

        // Farm Roles
        $farmDefinition->roles = array();
        foreach ($this->GetFarmRoles() as $dbFarmRole) {
            $farmDefinition->roles[] = $dbFarmRole->getDefinition();
        }

        //Farm Global Variables
        $variables = new Scalr_Scripting_GlobalVariables($this->ClientID, $this->EnvID, ScopeInterface::SCOPE_FARM);
        $farmDefinition->globalVariables = $variables->getValues(0, $this->ID, 0);

        //Farm Settings
        $farmDefinition->settings = $this->GetAllSettings();

        return $farmDefinition;
    }

    /**
     * @return Scalr_Environment
     */
    public function GetEnvironmentObject()
    {
        if (!$this->environment)
            $this->environment = Scalr_Model::init(Scalr_Model::ENVIRONMENT)->loadById($this->EnvID);

        return $this->environment;
    }

    public function GetFarmRoleIdByAlias ($alias) {
        $dbFarmRoleId = $this->DB->GetOne("SELECT id FROM farm_roles WHERE farmid=? AND alias=? LIMIT 1", array($this->ID, $alias));
        return $dbFarmRoleId;
    }

    /**
     *
     * @param integer $role_id
     * @return DBFarmRole
     */
    public function GetFarmRoleByRoleID($role_id)
    {
        $dbFarmRoleId = $this->DB->GetOne("SELECT id FROM farm_roles WHERE farmid=? AND role_id=? LIMIT 1", array($this->ID, $role_id));
        if (!$dbFarmRoleId)
            throw new Exception(sprintf(_("Role #%s not assigned to farm #%"), $role_id, $this->ID));

        return DBFarmRole::LoadByID($dbFarmRoleId);
    }

    /**
     *
     * @param string $behavior
     * @return <boolean, DBFarmRole>
     */
    public function GetFarmRoleByBehavior($behavior)
    {
        $farmRoleId = $this->DB->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? LIMIT 1",
            array($behavior, $this->ID)
        );

        return ($farmRoleId) ? DBFarmRole::LoadByID($farmRoleId) : false;
    }


    /**
     * Gets the list of the FarmRoles ordered by launch index.
     *
     * @return DBFarmRole[]  Returns the list of the FarmRoles
     */
    public function GetFarmRoles()
    {
        $retval = [];

        foreach ($this->DB->Execute("SELECT `id` FROM `farm_roles` WHERE `farmid` = ? ORDER BY `launch_index` ASC", [$this->ID]) as $row) {
            $retval[] = DBFarmRole::LoadByID($row['id']);
        }

        return $retval;
    }

    public function GetMySQLInstances($only_master = false, $only_slaves = false)
    {
        $mysql_farm_role_id = $this->DB->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? LIMIT 1",
            array(ROLE_BEHAVIORS::MYSQL, $this->ID)
        );

        if ($mysql_farm_role_id) {
            $servers = $this->GetServersByFilter(array('status' => array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT), 'farm_roleid' => $mysql_farm_role_id));
            $retval = array();
            foreach ($servers as $DBServer) {
                if ($only_master && $DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER))
                    $retval[] = $DBServer;
                elseif ($only_slaves && !$DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER))
                    $retval[] = $DBServer;
                elseif (!$only_master && !$only_slaves)
                    $retval[] = $DBServer;
            }
        } else {
            $retval = array();
        }

        return $retval;
    }

    /**
     * Gets the list of the servers by specified filter
     *
     * @param    array    $filter_args  optional Positive logic of filtering
     * @param    array    $ufilter_args optional Negation logic of filtering
     * @return   DBServer[] Returns the list of the DBServers
     */
    public function GetServersByFilter($filter_args = array(), $ufilter_args = array())
    {
        $sql = "SELECT server_id FROM servers WHERE `farm_id`=?";

        $args = array($this->ID);

        foreach ((array)$filter_args as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    array_push($args, $vv);
                }

                $sql .= " AND `{$k}` IN (".implode(",", array_fill(0, count($v), "?")).")";
            } else {
                $sql .= " AND `{$k}`=?";

                array_push($args, $v);
            }
        }

        foreach ((array)$ufilter_args as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    array_push($args, $vv);
                }

                $sql .= " AND `{$k}` NOT IN (".implode(",", array_fill(0, count($v), "?")).")";
            } else {
                $sql .= " AND `{$k}`!=?";

                array_push($args, $v);
            }
        }

        $res = $this->DB->GetAll($sql, $args);

        $retval = array();

        foreach ((array)$res as $i) {
            if ($i['server_id']) {
                $retval[] = DBServer::LoadByID($i['server_id']);
            }
        }

        return $retval;
    }

    /**
     * Returns all farm settings
     * @return unknown_type
     */
    public function GetAllSettings()
    {
        $settings = $this->DB->GetAll("SELECT * FROM farm_settings WHERE farmid=?", array($this->ID));

        $retval = array();
        foreach ($settings as $setting)
            $retval[$setting['name']] = $setting['value'];

        $this->SettingsCache = array_merge($this->SettingsCache, $retval);

        return $retval;
    }

    /**
     * Adds a role to farm
     *
     * @param   DBRole     $DBRole        The role object
     * @param   string     $platform      The cloud platform
     * @param   string     $cloudLocation The cloud location
     * @param   int        $launchIndex   Launch index
     * @param   string     $alias         optional
     * @return  DBFarmRole
     */
    public function AddRole(DBRole $DBRole, $platform, $cloudLocation, $launchIndex, $alias = "")
    {
        if (empty($alias))
            $alias = $DBRole->name;

        $this->DB->Execute("
            INSERT INTO farm_roles
            SET farmid=?,
                role_id=?,
                reboot_timeout=?,
                launch_timeout=?,
                status_timeout = ?,
                launch_index = ?,
                platform = ?,
                cloud_location=?,
                `alias` = ?
        ", [
            $this->ID,
            $DBRole->id,
            300,
            300,
            600,
            $launchIndex,
            $platform,
            $cloudLocation,
            $alias
        ]);

        $farm_role_id = $this->DB->Insert_ID();

        $DBFarmRole = new DBFarmRole($farm_role_id);
        $DBFarmRole->FarmID = $this->ID;
        $DBFarmRole->RoleID = $DBRole->id;
        $DBFarmRole->Platform = $platform;
        $DBFarmRole->CloudLocation = $cloudLocation;
        $DBFarmRole->Alias = $alias;

        $default_settings = [
            Entity\FarmRoleSetting::SCALING_MIN_INSTANCES => 1,
            Entity\FarmRoleSetting::SCALING_MAX_INSTANCES => 1
        ];

        foreach ($default_settings as $k => $v) {
            $DBFarmRole->SetSetting($k, $v);
        }

        if ($farm_role_id && $DBFarmRole && \Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                $this->ClientID, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_FARM_ROLE, $farm_role_id,
                sprintf('%s', $DBFarmRole->Alias)
            );
        }

        return $DBFarmRole;
    }

    /**
     * Set farm setting
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function SetSetting($name, $value)
    {
        $Reflect = new ReflectionClass('Scalr\Model\Entity\FarmSetting');
        $consts = array_values($Reflect->getConstants());
        if (in_array($name, $consts)) {
            //UNIQUE KEY `farmid_name` (`farmid`,`name`)
            $this->DB->Execute("
                INSERT INTO farm_settings
                SET `farmid` = ?,
                    `name`=?,
                    `value`=?
                ON DUPLICATE KEY UPDATE
                    `value` = ?
            ", array(
                $this->ID, $name, $value,
                $value,
            ));

            $this->SettingsCache[$name] = $value;
        } else {
            throw new Exception("Unknown farm setting '{$name}'");
        }

        return true;
    }

    /**
     * Associates cost analytics project with the farm
     *
     * It does not perform any actions if cost analytics is disabled
     *
     * @param   ProjectEntity|string  $project         The project entity or its identifier
     * @return  string                Returns identifier of the associated project
     * @throws  InvalidArgumentException
     * @throws  AnalyticsException
     */
    public function setProject($project)
    {
        if (Scalr::getContainer()->analytics->enabled) {
            if ($project instanceof ProjectEntity) {
                $projectId = $project->projectId;
            } else {
                $projectId = $project;
                unset($project);
            }

            $analytics = Scalr::getContainer()->analytics;

            if ($projectId === null) {
                $ccId = $this->GetEnvironmentObject()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);
                if (!empty($ccId)) {
                    //Assigns Project automatically only if it is the one withing the Cost Center
                    $projects = ProjectEntity::findByCcId($ccId);
                    if (count($projects) == 1) {
                        $project = $projects->getArrayCopy()[0];
                        $projectId = $project->projectId;
                    }
                }
            } elseif (!empty($projectId)) {
                //Validates specified project's identifier
                if (!preg_match('/^[[:xdigit:]-]{36}$/', $projectId)) {
                    throw new InvalidArgumentException(sprintf(
                        "Identifier of the cost analytics Project must have valid UUID format. '%s' given.",
                        strip_tags($projectId)
                    ));
                }

                $project = isset($project) ? $project : $analytics->projects->get($projectId);

                if (!$project) {
                    throw new AnalyticsException(sprintf(
                        "Could not find Project with specified identifier %s.", strip_tags($projectId)
                    ));
                } else if ($project->ccId !== $this->GetEnvironmentObject()->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID)) {
                    throw new AnalyticsException(sprintf(
                        "Invalid project identifier. Parent Cost center of the Project should correspond to the Environment's cost center."
                    ));
                }
            } else {
                $projectId = null;
            }

            //Sets project to the farm object only if it has been provided
            if (isset($projectId)) {
                $project = isset($project) ? $project : $analytics->projects->get($projectId);

                $oldProjectId = $this->GetSetting(Entity\FarmSetting::PROJECT_ID);

                $this->SetSetting(Entity\FarmSetting::PROJECT_ID, $project->projectId);

                //Server property SERVER_PROPERTIES::FARM_PROJECT_ID should be updated
                //for all running servers associated with the farm.
                $this->DB->Execute("
                    INSERT `server_properties` (`server_id`, `name`, `value`)
                    SELECT s.`server_id`, ? AS `name`, ? AS `value`
                    FROM `servers` s
                    WHERE s.`farm_id` = ?
                    ON DUPLICATE KEY UPDATE `value` = ?
                ", [
                    SERVER_PROPERTIES::FARM_PROJECT_ID,
                    $project->projectId,
                    $this->ID,
                    $project->projectId
                ]);

                //Cost centre should correspond to Project's CC
                $this->DB->Execute("
                    INSERT `server_properties` (`server_id`, `name`, `value`)
                    SELECT s.`server_id`, ? AS `name`, ? AS `value`
                    FROM `servers` s
                    WHERE s.`farm_id` = ?
                    ON DUPLICATE KEY UPDATE `value` = ?
                ", [
                    SERVER_PROPERTIES::ENV_CC_ID,
                    $project->ccId,
                    $this->ID,
                    $project->ccId
                ]);

                if (empty($oldProjectId)) {
                    $analytics->events->fireAssignProjectEvent($this, $project->projectId);
                } elseif ($oldProjectId !== $projectId) {
                    $analytics->events->fireReplaceProjectEvent($this, $project->projectId, $oldProjectId);
                }
            }
        }

        return $projectId;
    }

    /**
     * Get Farm setting by name
     * @param string $name
     * @return mixed
     */
    public function GetSetting($name)
    {
        if (!isset($this->SettingsCache[$name])) {
            $this->SettingsCache[$name] = $this->DB->GetOne("
                SELECT `value` FROM `farm_settings` WHERE `farmid`=? AND `name` = ? LIMIT 1
            ", array(
                $this->ID,
                $name
            ));
        }

        return $this->SettingsCache[$name];
    }

    /**
     * Check if farm is locked
     *
     * @param  $throwException
     * @return bool
     * @throws Exception
     */
    public function isLocked($throwException = true)
    {
        if ($this->GetSetting(Entity\FarmSetting::LOCK)) {
            $message = $this->GetSetting(Entity\FarmSetting::LOCK_COMMENT);

            try {
                $userName = Scalr_Account_User::init()->loadById($this->getSetting(Entity\FarmSetting::LOCK_BY))->getEmail();
            } catch (Exception $e) {
                $userName = $this->getSetting(Entity\FarmSetting::LOCK_BY);
            }

            if ($message) {
                $message = sprintf(' with comment: \'%s\'', $message);
            }

            if ($throwException) {
                throw new Exception(sprintf('Farm was locked by %s%s. Please unlock it first.', $userName, $message));
            } else {
                return sprintf('Farm was locked by %s%s.', $userName, $message);
            }
        }

        return false;
    }

    /**
     * @param $userId integer
     * @param $comment string
     * @param $restrict bool
     */
    public function lock($userId, $comment, $restrict)
    {
        $this->SetSetting(Entity\FarmSetting::LOCK, 1);
        $this->SetSetting(Entity\FarmSetting::LOCK_BY, $userId);
        $this->SetSetting(Entity\FarmSetting::LOCK_COMMENT, $comment);
        $this->SetSetting(Entity\FarmSetting::LOCK_UNLOCK_BY, '');

        if ($this->createdByUserId && $restrict) {
            $this->SetSetting(Entity\FarmSetting::LOCK_RESTRICT, $restrict);
        }
    }

    /**
     * @param $userId integer
     */
    public function unlock($userId)
    {
        $this->SetSetting(Entity\FarmSetting::LOCK, '');
        $this->SetSetting(Entity\FarmSetting::LOCK_BY, '');
        $this->SetSetting(Entity\FarmSetting::LOCK_UNLOCK_BY, $userId);
        $this->SetSetting(Entity\FarmSetting::LOCK_COMMENT, '');
        $this->SetSetting(Entity\FarmSetting::LOCK_RESTRICT, '');
    }

    /**
     * Load DBInstance
     *
     * @param array $record Array of farm fields
     * @return \DBFarm
     */
    public static function loadFields($record)
    {
        $DBFarm = new DBFarm($record['id']);

        foreach (self::$FieldPropertyMap as $k => $v) {
            if (isset($record[$k])) {
                $DBFarm->{$v} = $record[$k];
            }
        }

        return $DBFarm;
    }

    /**
     * Load DBInstance by database id
     * @param $id
     * @return DBFarm
     */
    static public function LoadByID($id)
    {
        $db = \Scalr::getDb();

        $farm_info = $db->GetRow("SELECT * FROM farms WHERE id=?", array($id));

        if (!$farm_info) {
            throw new Exception(sprintf(_("Farm ID#%s not found in database"), $id));
        }

        $DBFarm = new DBFarm($id);

        foreach (self::$FieldPropertyMap as $k => $v) {
            if (isset($farm_info[$k])) {
                $DBFarm->{$v} = $farm_info[$k];
            }
        }

        return $DBFarm;
    }

    static public function LoadByIDOnlyName($id)
    {
        $db = \Scalr::getDb();

        $farm_info = $db->GetRow("SELECT name FROM farms WHERE id=?", array($id));

        return $farm_info['name'] ? $farm_info['name'] : '*removed farm*';
    }

    public function save()
    {
        $container = \Scalr::getContainer();
        if (!$this->ID) {
            $this->ID = 0;
            $this->Hash = substr(CryptoTool::hash(uniqid(rand(), true)),0, 14);

            if (!$this->ClientID && $container->initialized('environment')) {
                $this->ClientID = $container->environment->clientId;
            }

            if (!$this->EnvID && $container->initialized('environment')) {
                $this->EnvID = $container->environment->id;
            }
        }

        if ($this->DB->GetOne("
                SELECT id FROM farms
                WHERE name = ?
                AND env_id = ?
                AND id != ?
                LIMIT 1
            ", array(
                $this->Name, $this->EnvID, $this->ID
            ))) {
            throw new Exception(sprintf('The name "%s" is already used.', $this->Name));
        }

        if (!$this->ID) {
            $this->DB->Execute("
                INSERT INTO farms
                SET status = ?,
                    name = ?,
                    clientid = ?,
                    env_id = ?,
                    hash = ?,
                    created_by_id = ?,
                    created_by_email = ?,
                    changed_by_id = ?,
                    changed_time = ?,
                    team_id = ?,
                    dtadded = NOW(),
                    farm_roles_launch_order = ?,
                    comments = ?
            ", array(
                FARM_STATUS::TERMINATED,
                $this->Name,
                $this->ClientID,
                $this->EnvID,
                $this->Hash,
                $this->createdByUserId,
                $this->createdByUserEmail,
                $this->changedByUserId,
                $this->changedTime,
                $this->teamId,
                $this->RolesLaunchOrder,
                $this->Comments
            ));

            $this->ID = $this->DB->Insert_ID();
        } else {
            $this->DB->Execute("
                UPDATE farms
                SET name = ?,
                    status = ?,
                    farm_roles_launch_order = ?,
                    term_on_sync_fail = ?,
                    comments = ?,
                    created_by_id = ?,
                    created_by_email = ?,
                    changed_by_id = ?,
                    changed_time = ?,
                    team_id = ?
                WHERE id = ?
                LIMIT 1
            ", array(
                $this->Name,
                $this->Status,
                $this->RolesLaunchOrder,
                $this->TermOnSyncFail,
                $this->Comments,
                $this->createdByUserId,
                $this->createdByUserEmail,
                $this->changedByUserId,
                $this->changedTime,
                $this->teamId,
                $this->ID
            ));
        }

        if (Scalr::getContainer()->analytics->enabled) {
            //Farm tag
            Scalr::getContainer()->analytics->tags->syncValue(
                $this->ClientID, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_FARM, $this->ID, $this->Name
            );
            //Farm owner tag
            Scalr::getContainer()->analytics->tags->syncValue(
                $this->ClientID, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_FARM_OWNER, $this->ID, $this->createdByUserId
            );
        }
    }

    /**
     * Gets AWS tags that should be applied to the resource
     *
     * @return  array  Returns list of the AWS tags
     */
    public function getAwsTags()
    {
        $tags = [[
            'key'   => \Scalr_Governance::SCALR_META_TAG_NAME,
            'value' => $this->applyGlobalVarsToValue(\Scalr_Governance::SCALR_META_TAG_VALUE)
        ]];

        //Tags governance
        $governance = new \Scalr_Governance($this->EnvID);
        $gTags = (array) $governance->getValue('ec2', \Scalr_Governance::AWS_TAGS);

        if (count($gTags) > 0) {
            foreach ($gTags as $tKey => $tValue) {
                $tags[] = [
                    'key'   => $tKey,
                    'value' => $this->applyGlobalVarsToValue($tValue)
                ];
            }
        }

        return $tags;
    }
}
