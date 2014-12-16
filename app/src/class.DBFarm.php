<?php

use Scalr\Modules\Platforms\Cloudstack\Helpers\CloudstackHelper;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Exception\AnalyticsException;

class DBFarm
{
    const SETTING_CRYPTO_KEY				= 'crypto.key';

    const SETTING_SZR_UPD_REPOSITORY		= 'szr.upd.repository';
    const SETTING_SZR_UPD_SCHEDULE			= 'szr.upd.schedule';

    const SETTING_LOCK                      = 'lock';
    const SETTING_LOCK_COMMENT              = 'lock.comment';
    const SETTING_LOCK_BY                   = 'lock.by';
    const SETTING_LOCK_RESTRICT             = 'lock.restrict';
    const SETTING_LOCK_UNLOCK_BY            = 'lock.unlock.by';

    const SETTING_EC2_VPC_REGION            = 'ec2.vpc.region';
    const SETTING_EC2_VPC_ID                = 'ec2.vpc.id';

    const SETTING_LEASE_STATUS              = 'lease.status';
    const SETTING_LEASE_TERMINATE_DATE      = 'lease.terminate.date';
    const SETTING_LEASE_EXTEND_CNT          = 'lease.extend.cnt';
    const SETTING_LEASE_NOTIFICATION_SEND   = 'lease.notification.send';

    const SETTING_TIMEZONE                  = 'timezone';

    const SETTING_PROJECT_ID                = 'project_id';

    const SETTING_OWNER_HISTORY             = 'owner.history';

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
        $changedTime
    ;

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
        'changed_time'      => 'changedTime'
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

        $dbFarm->SetSetting(DBFarm::SETTING_CRYPTO_KEY, Scalr::GenerateRandomKey(40));

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
            if (!stristr($definition->name, "clone")) {
                $template = $definition->name . ' (clone #%s)';
                $i = 1;
            } else {
                preg_match("/^(.*?)\(clone \#([0-9]*)\)$/si", $definition->name, $matches);
                $template = trim($matches[1])." (clone #%s)";
                $i = $matches[2]+1;
            }

            while (true) {
                $name = sprintf($template, $i);
                if (!$this->DB->GetOne("SELECT id FROM farms WHERE name = ? AND env_id = ? LIMIT 1", array($name, $this->EnvID))) {
                    break;
                }
                else {
                    $i++;
                }
            }
        }

        $dbFarm = self::create($name, $user, $envId);

        $dbFarm->createdByUserId = $user->id;
        $dbFarm->createdByUserEmail = $user->getEmail();
        $dbFarm->RolesLaunchOrder = $definition->rolesLaunchOrder;

        $dbFarm->SetSetting(DBFarm::SETTING_TIMEZONE, $definition->settings[DBFarm::SETTING_TIMEZONE]);
        $dbFarm->SetSetting(DBFarm::SETTING_EC2_VPC_ID, $definition->settings[DBFarm::SETTING_EC2_VPC_ID]);
        $dbFarm->SetSetting(DBFarm::SETTING_EC2_VPC_REGION, $definition->settings[DBFarm::SETTING_EC2_VPC_REGION]);
        $dbFarm->SetSetting(DBFarm::SETTING_SZR_UPD_REPOSITORY, $definition->settings[DBFarm::SETTING_SZR_UPD_REPOSITORY]);
        $dbFarm->SetSetting(DBFarm::SETTING_SZR_UPD_SCHEDULE, $definition->settings[DBFarm::SETTING_SZR_UPD_SCHEDULE]);
        $dbFarm->SetSetting(DBFarm::SETTING_LEASE_STATUS, $definition->settings[DBFarm::SETTING_LEASE_STATUS]);

        $variables = new Scalr_Scripting_GlobalVariables($dbFarm->ClientID, $envId, Scalr_Scripting_GlobalVariables::SCOPE_FARM);
        $variables->setValues($definition->globalVariables, 0, $dbFarm->ID, 0);

        foreach ($definition->roles as $index => $role) {
            $dbFarmRole = $dbFarm->AddRole(DBRole::loadById($role->roleId), $role->platform, $role->cloudLocation, $index+1, $role->alias);
            $oldRoleSettings = $dbFarmRole->GetAllSettings();
            $dbFarmRole->applyDefinition($role, true);
            $newSettings = $dbFarmRole->GetAllSettings();

            Scalr_Helpers_Dns::farmUpdateRoleSettings($dbFarmRole, $oldRoleSettings, $newSettings);

            // Platfrom specified updates
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

        if ($usedPlatforms[SERVER_PLATFORMS::EC2])
            \Scalr\Modules\Platforms\Ec2\Helpers\Ec2Helper::farmSave($dbFarm, $dbFarmRolesList);

        if ($usedPlatforms[SERVER_PLATFORMS::EUCALYPTUS])
            \Scalr\Modules\Platforms\Eucalyptus\Helpers\EucalyptusHelper::farmSave($dbFarm, $dbFarmRolesList);

        if ($usedPlatforms[SERVER_PLATFORMS::CLOUDSTACK])
            CloudstackHelper::farmSave($dbFarm, $dbFarmRolesList);

        $dbFarm->save();

        return $dbFarm;
    }

    public function getDefinition()
    {
        $farmDefinition = new stdClass();
        $farmDefinition->name = $this->Name;
        $farmDefinition->rolesLaunchOrder = $this->RolesLaunchOrder;

        // Farm Roles
        $farmDefinition->roles = array();
        foreach ($this->GetFarmRoles() as $dbFarmRole) {
            $farmDefinition->roles[] = $dbFarmRole->getDefinition();
        }

        //Farm Global Variables
        $variables = new Scalr_Scripting_GlobalVariables($this->ClientID, $this->EnvID, Scalr_Scripting_GlobalVariables::SCOPE_FARM);
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

    /**
     *
     * @param integer $role_id
     * @return DBFarmRole
     */
    public function GetFarmRoleByRoleID($role_id)
    {
        $db_role = $this->DB->GetRow("SELECT id FROM farm_roles WHERE farmid=? AND role_id=? LIMIT 1", array($this->ID, $role_id));
        if (!$db_role)
            throw new Exception(sprintf(_("Role #%s not assigned to farm #%"), $role_id, $this->ID));

        return DBFarmRole::LoadByID($db_role['id']);
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
     * @return DBFarmRole[]
     */
    public function GetFarmRoles()
    {
        $db_roles = $this->DB->GetAll("SELECT id FROM farm_roles WHERE farmid=? ORDER BY launch_index ASC", array($this->ID));
        $retval = array();
        foreach ($db_roles as $db_role)
            $retval[] = DBFarmRole::LoadByID($db_role['id']);

        return $retval;
    }

    public function GetMySQLInstances($only_master = false, $only_slaves = false)
    {
        $mysql_farm_role_id = $this->DB->GetOne("SELECT id FROM farm_roles WHERE role_id IN (SELECT role_id FROM role_behaviors WHERE behavior=?) AND farmid=? LIMIT 1",
            array(ROLE_BEHAVIORS::MYSQL, $this->ID)
        );
        if ($mysql_farm_role_id)
        {
            $servers = $this->GetServersByFilter(array('status' => array(SERVER_STATUS::RUNNING, SERVER_STATUS::INIT), 'farm_roleid' => $mysql_farm_role_id));
            $retval = array();
            foreach ($servers as $DBServer)
            {
                if ($only_master && $DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER))
                    $retval[] = $DBServer;
                elseif ($only_slaves && !$DBServer->GetProperty(SERVER_PROPERTIES::DB_MYSQL_MASTER))
                    $retval[] = $DBServer;
                elseif (!$only_master && !$only_slaves)
                    $retval[] = $DBServer;
            }
        }
        else
            $retval = array();

        return $retval;
    }

    public function GetServersByFilter($filter_args = array(), $ufilter_args = array())
    {
        $sql = "SELECT server_id FROM servers WHERE `farm_id`=?";
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
            DBFarmRole::SETTING_SCALING_MIN_INSTANCES => 1,
            DBFarmRole::SETTING_SCALING_MAX_INSTANCES => 1
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
        $Reflect = new ReflectionClass($this);
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
     * @param   bool                  $ignoreAutomatic optional Should it ignore auto assignment of the default project
     * @return  string                Returns identifier of the associated project
     * @throws  InvalidArgumentException
     * @throws  AnalyticsException
     */
    public function setProject($project, $ignoreAutomatic = false)
    {
        if (Scalr::getContainer()->analytics->enabled) {
            if ($project instanceof ProjectEntity) {
                $projectId = $project->projectId;
            } else {
                $projectId = $project;
                unset($project);
            }

            $analytics = Scalr::getContainer()->analytics;

            if (!$ignoreAutomatic && Scalr::isHostedScalr() && !Scalr::isAllowedAnalyticsOnHostedScalrAccount($this->ClientID)) {
                //Overrides project with automatic one
                $projectId = $analytics->usage->autoProject($this->EnvID, $this->ID);
            } else if (!empty($projectId)) {
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

                $oldProjectId = $this->GetSetting(DBFarm::SETTING_PROJECT_ID);

                $this->SetSetting(DBFarm::SETTING_PROJECT_ID, $project->projectId);

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
     * @param $throwException
     * @return bool
     * @throws Exception
     */
    public function isLocked($throwException = true)
    {
        if ($this->GetSetting(DBFarm::SETTING_LOCK)) {
            $message = $this->GetSetting(DBFarm::SETTING_LOCK_COMMENT);

            try {
                $userName = Scalr_Account_User::init()->loadById($this->getSetting(DBFarm::SETTING_LOCK_BY))->getEmail();
            } catch(Exception $e) {
                $userName = $this->getSetting(DBFarm::SETTING_LOCK_BY);
            }

            if ($message)
                $message = sprintf(' with comment: \'%s\'', $message);

            if ($throwException)
                throw new Exception(sprintf('Farm was locked by %s%s. Please unlock it first.', $userName, $message));
            else
                return sprintf('Farm was locked by %s%s.', $userName, $message);
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
        $this->SetSetting(DBFarm::SETTING_LOCK, 1);
        $this->SetSetting(DBFarm::SETTING_LOCK_BY, $userId);
        $this->SetSetting(DBFarm::SETTING_LOCK_COMMENT, $comment);
        $this->SetSetting(DBFarm::SETTING_LOCK_UNLOCK_BY, '');

        if ($this->createdByUserId && $restrict)
            $this->SetSetting(DBFarm::SETTING_LOCK_RESTRICT, 1);
    }

    /**
     * @param $userId integer
     */
    public function unlock($userId)
    {
        $this->SetSetting(DBFarm::SETTING_LOCK, '');
        $this->SetSetting(DBFarm::SETTING_LOCK_BY, '');
        $this->SetSetting(DBFarm::SETTING_LOCK_UNLOCK_BY, $userId);
        $this->SetSetting(DBFarm::SETTING_LOCK_COMMENT, '');
        $this->SetSetting(DBFarm::SETTING_LOCK_RESTRICT, '');
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
            $this->Hash = substr(Scalr_Util_CryptoTool::hash(uniqid(rand(), true)),0, 14);

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
                    changed_time = ?
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
}
