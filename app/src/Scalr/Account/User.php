<?php

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;

/**
 * @deprecated  This class has been deprecated since version 5.4.0. Please use new Scalr\Model\Entity\Account\User entity.
 * @see         \Scalr\Model\Entity\Account\User
 */
class Scalr_Account_User extends Scalr_Model
{
    protected $dbTableName = 'account_users';
    protected $dbPrimaryKey = "id";
    protected $dbMessageKeyNotFound = "User #%s not found in database";

    const STATUS_ACTIVE = 'Active';
    const STATUS_INACTIVE = 'Inactive';

    const TYPE_SCALR_ADMIN = 'ScalrAdmin';
    const TYPE_FIN_ADMIN = 'FinAdmin';
    const TYPE_ACCOUNT_OWNER = 'AccountOwner';
    const TYPE_ACCOUNT_ADMIN = 'AccountAdmin';
    const TYPE_ACCOUNT_SUPER_ADMIN = 'AccountSuperAdmin';
    const TYPE_TEAM_USER = 'TeamUser';

    const SETTING_API_ACCESS_KEY 	= 'api.access_key';
    const SETTING_API_SECRET_KEY 	= 'api.secret_key';
    const SETTING_API_ENABLED 		= 'api.enabled';

    const SETTING_UI_ENVIRONMENT = 'ui.environment'; // last used
    const SETTING_UI_TIMEZONE = 'ui.timezone';
    const SETTING_UI_STORAGE_TIME = 'ui.storage.time';
    const SETTING_UI_CHANGELOG_TIME = 'ui.changelog.time';

    const SETTING_GRAVATAR_EMAIL = 'gravatar.email';
    
    const SETTING_LDAP_EMAIL = 'ldap.email';
    const SETTING_LDAP_USERNAME = 'ldap.username';
    
    const SETTING_LEAD_VERIFIED = 'lead.verified';
    const SETTING_LEAD_HASH = 'lead.hash';

    const SETTING_SECURITY_2FA_GGL = 'security.2fa.ggl';
    const SETTING_SECURITY_2FA_GGL_KEY = 'security.2fa.ggl.key';
    const SETTING_SECURITY_2FA_GGL_RESET_CODE = 'security.2fa.ggl.reset_code';

    const VAR_UI_STORAGE = 'ui.storage';
    const VAR_SECURITY_IP_WHITELIST = 'security.ip.whitelist';
    const VAR_API_IP_WHITELIST = 'api.ip.whitelist';

    const VAR_SSH_CONSOLE_LAUNCHER = 'ssh.console.launcher';
    const VAR_SSH_CONSOLE_USERNAME = 'ssh.console.username';
    const VAR_SSH_CONSOLE_IP = 'ssh.console.ip';
    const VAR_SSH_CONSOLE_PORT = 'ssh.console.port';
    const VAR_SSH_CONSOLE_KEY_NAME = 'ssh.console.key_name';
    const VAR_SSH_CONSOLE_DISABLE_KEY_AUTH = 'ssh.console.disable_key_auth';
    const VAR_SSH_CONSOLE_ENABLE_AGENT_FORWARDING = 'ssh.console.enable_agent_forwarding';
    const VAR_SSH_CONSOLE_LOG_LEVEL = 'ssh.console.log_level';
    const VAR_SSH_CONSOLE_PREFERRED_PROVIDER = 'ssh.console.preferred_provider';

    protected $dbPropertyMap = array(
        'id'			=> 'id',
        'account_id'	=> 'accountId',
        'status'		=> 'status',
        'email'			=> array('property' => 'email', 'is_filter' => true),
        'fullname'		=> 'fullname',
        'password' 		=> array('property' => 'password'),
        'type'			=> 'type',
        'dtcreated'		=> array('property' => 'dtCreated', 'createSql' => 'NOW()', 'type' => 'datetime', 'update' => false),
        'dtlastlogin'	=> array('property' => 'dtLastLogin', 'type' => 'datetime'),
        'comments'		=> 'comments',
        'loginattempts' => 'loginattempts',
    );

    public
        $status,
        $fullname,
        $dtCreated,
        $dtLastLogin,
        $type,
        $comments,
        $loginattempts;

    protected
        $email,
        $password,
        $accountId;


    protected $account;
    protected $permissions;
    protected $settingsCache = array();
    protected $varCache = array();

    /**
     * Misc. cache
     *
     * @var array
     */
    private $_cache = array();

    /**
     *
     * @return Scalr_Account_User
     */
    public static function init($className = null)
    {
        return parent::init();
    }

    /**
     *
     * @return Scalr_Account_User
     */
    public function loadBySetting($name, $value)
    {
        $id = $this->db->GetOne("SELECT user_id FROM account_user_settings WHERE name = ? AND value = ? LIMIT 1",
            array($name, $value)
        );
        if (!$id)
            return false;
        else
            return $this->loadById($id);
    }

    /**
     * @param $accessKey
     * @return Scalr_Account_User
     */
    public function loadByApiAccessKey($accessKey)
    {
        return $this->loadBySetting(Scalr_Account_User::SETTING_API_ACCESS_KEY, $accessKey);
    }

    /**
     *
     * @return Scalr_Account_User
     */
    public function loadByEmail($email, $accountId = null)
    {
        if ($accountId)
            $info = $this->db->GetRow("SELECT * FROM account_users WHERE `email` = ? AND account_id = ? LIMIT 1",
                array($email, $accountId)
            );
        else
            $info = $this->db->GetRow("SELECT * FROM account_users WHERE `email` = ? LIMIT 1",
                array($email)
            );

        if (!$info)
            return false;
        else
            return $this->loadBy($info);
    }

    /**
     *
     * @return Scalr_Permissions
     */
    public function getPermissions()
    {
        if (!$this->permissions)
            $this->permissions = new Scalr_Permissions($this);

        return $this->permissions;
    }

    public function create($email, $accountId)
    {
        $this->id = 0;
        $this->accountId = $accountId;

        if ($this->isEmailExists($email))
            throw new Exception('Uh oh. Seems like that email is already in use. Try another?');

        $this->email = $email;

        $this->save();
        $this->setSetting(Scalr_Account_User::SETTING_GRAVATAR_EMAIL, $email);
        return $this;
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::delete()
     */
    public function delete($id = null)
    {
        if ($this->type == Scalr_Account_User::TYPE_ACCOUNT_OWNER)
            throw new Exception('You cannot remove Account Owner');

        parent::delete();

        $this->db->Execute('DELETE FROM `account_team_users` WHERE user_id = ?', array($this->id));
        $this->db->Execute('DELETE FROM `account_user_settings` WHERE user_id = ?', array($this->id));
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     *
     * @return Scalr_Account
     */
    public function getAccount()
    {
        if (!$this->account)
            $this->account = Scalr_Account::init()->loadById($this->accountId);

        return $this->account;
    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function getType()
    {
        return $this->type;
    }

    /**
     * Gets email address of the User
     *
     * @return string Returns user email address
     */
    public function getEmail()
    {
        return $this->email;
    }
    
    /**
     * Gets LDAP username of the User
     *
     * @return string Returns user LDAP username
     */
    public function getLdapUsername()
    {
        $ldapUsername = $this->getSetting(self::SETTING_LDAP_USERNAME);
        if (!$ldapUsername)
            $ldapUsername = strtok($this->getEmail(), '@');
        
        return $ldapUsername;
    }

    public function getGravatarHash()
    {
        $email = trim($this->getSetting(Scalr_Account_User::SETTING_GRAVATAR_EMAIL));
        return $email && strpos($email, '@') !== FALSE ? md5(strtolower($email)) : '';
    }

    public function updateEmail($email)
    {
        if ($email && ($email == $this->email || !$this->isEmailExists($email)))
            $this->email = $email;
        else
            throw new Exception('Uh oh. Seems like that email is already in use. Try another?');
    }

    /**
     * Returns user setting value by name
     *
     * @param string $name
     * @param bool $ignoreCache
     * @return mixed $value
     */
    public function getSetting($name, $ignoreCache = false)
    {
        if (!array_key_exists($name, $this->settingsCache) || $ignoreCache) {
            $value = $this->db->GetOne("SELECT value FROM account_user_settings WHERE user_id=? AND `name`=? LIMIT 1",
                array($this->id, $name)
            );

            // TODO: fix bug with false returning when empty result
            $this->settingsCache[$name] = $value == 'false' ? '' : $value;
        }

        return $this->settingsCache[$name];
    }

    /**
     * Set user setting
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setSetting($name, $value)
    {
        //UNIQUE KEY `userid_name` (`user_id`,`name`),
        $this->db->Execute("
            INSERT account_user_settings
            SET `user_id` = ?,
                `name` = ?,
                `value` = ?
            ON DUPLICATE KEY UPDATE
                `value` = ?
        ", array(
            $this->id, $name, $value, $value
        ));

        $this->settingsCache[$name] = $value;
    }

    /**
     * Returns user var value by name
     *
     * @param string $name
     * @param bool $ignoreCache
     * @return mixed $value
     */
    public function getVar($name, $ignoreCache = false)
    {
        if (!array_key_exists($name, $this->settingsCache) || $ignoreCache) {
            $value = $this->db->GetOne("SELECT value FROM account_user_vars WHERE user_id=? AND `name`=? LIMIT 1",
                array($this->id, $name)
            );

            $this->varCache[$name] = $value == 'false' ? '' : $value;
        }

        return $this->varCache[$name];
    }

    /**
     * Set user var
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setVar($name, $value)
    {
        //UNIQUE KEY `userid_name` (`user_id`,`name`),
        $this->db->Execute("
            INSERT account_user_vars
            SET `user_id`=?,
                `name`=?,
                `value`=?
            ON DUPLICATE KEY UPDATE
                `value`=?
        ", array(
            $this->id, $name, $value, $value
        ));

        $this->varCache[$name] = $value;
    }

    /**
     * Get user dashboard
     * @param $envId
     * @return array
     */
    public function getDashboard($envId)
    {
        if ($envId) {
            $obj = unserialize($this->db->GetOne("SELECT value FROM account_user_dashboard WHERE `user_id` = ? AND `env_id` = ? LIMIT 1",
                array($this->id, $envId)
            ));
        } else {
            $obj = unserialize($this->db->GetOne("SELECT value FROM account_user_dashboard WHERE `user_id` = ? AND `env_id` IS NULL LIMIT 1",
                array($this->id)
            ));
        }

        if (! is_array($obj)) {
            $obj = array('configuration' => array(), 'flags' => array(), 'widgets' => array());
            $this->setDashboard($envId, $obj);
            $obj = $this->getDashboard($envId);
            $obj['widgets'] = array_values($obj['widgets']); // it should be array, not object
        }

        return $obj;
    }

    /**
     * Set user dashboard
     * @param integer $envId
     * @param array $value
     * @throws Scalr_Exception_Core
     */
    public function setDashboard($envId, $value)
    {
        // check consistency
        $usedWidgets = array();
        if (is_array($value) &&
            isset($value['configuration']) && is_array($value['configuration']) &&
            isset($value['flags']) && is_array($value['flags'])
        ) {
            $configuration = array();
            foreach ($value['configuration'] as $col) {
                if (is_array($col)) {
                    $column = array();
                    foreach ($col as $wid) {
                        if (is_array($wid) && isset($wid['name'])) {

                            // deprecated widgets
                            if (in_array($wid['name'], [
                                'dashboard.usagelaststat',
                                'dashboard.uservoice'
                            ]))
                                continue;

                            $usedWidgets[] = $wid['name'];
                            array_push($column, $wid);
                        }
                    }
                    array_push($configuration, $column);
                }
            }

            $value['configuration'] = $configuration;
            $value['widgets'] = array_values(array_unique($usedWidgets));
        } else {
            throw new Scalr_Exception_Core('Invalid configuration for dashboard');
        }

        $srlvalue = serialize($value);
        //UNIQUE KEY `user_id` (`user_id`,`env_id`)
        if (! $envId) {
            // if envId is NULL, foreign key doesn't work, remove possible record (todo: refactor)
            $this->db->Execute('DELETE FROM account_user_dashboard WHERE user_id = ? AND env_id IS NULL', [$this->id]);
        }

        $this->db->Execute("
            INSERT account_user_dashboard
            SET `user_id` = ?, `env_id` = ?, `value` = ?
            ON DUPLICATE KEY UPDATE
                `value` = ?
        ", array(
            $this->id, $envId, $srlvalue, $srlvalue
        ));
    }

    /**
     * Add widget to dashboard
     * @param int $envId
     * @param array $widgetConfig
     * @param int $columnNumber
     * @param int $position
     */
    public function addDashboardWidget($envId, $widgetConfig, $columnNumber = 0, $position = 0)
    {
        $dashboard = $this->getDashboard($envId);

        // we could use maximum only last column, do not create new one
        $columnNumber = $columnNumber >= count($dashboard['configuration']) ? count($dashboard['configuration']) - 1 : $columnNumber;
        array_splice($dashboard['configuration'][$columnNumber], min($position, count($dashboard['configuration'][$columnNumber])), 0, array($widgetConfig));
        $this->setDashboard($envId, $dashboard);
    }

    public function updatePassword($pwd)
    {
        $this->password = $this->getCrypto()->hash(trim($pwd));
    }

    /**
     * @param $pwd
     * @return bool
     */
    public function checkPassword($pwd, $updateLoginAttempt = true)
    {
        if ($this->password != $this->getCrypto()->hash($pwd)) {
            if ($updateLoginAttempt) {
                $this->updateLoginAttempt(1);
            }
            return false;
        }
        else {
            if ($updateLoginAttempt) {
                $this->updateLoginAttempt();
            }
            return true;
        }
    }

    public function updateLoginAttempt($loginattempt = NULL)
    {
        if ($loginattempt) {
            $this->db->Execute('UPDATE `account_users` SET loginattempts = loginattempts + ? WHERE id = ?', array($loginattempt, $this->id));
            $this->loginattempts++;
        } else {
            $this->db->Execute('UPDATE `account_users` SET loginattempts = 0 WHERE id = ?', array($this->id));
            $this->loginattempts = 0;
        }
    }

    public function updateLastLogin()
    {
        $this->db->Execute('UPDATE `account_users` SET dtlastlogin = NOW() WHERE id = ?', array($this->id));
    }

    public function isEmailExists($email)
    {
        //TODO please use unique key (account_id,email)
        return $this->db->getOne('SELECT * FROM `account_users` WHERE email = ? AND account_id = ? LIMIT 1', array($email, $this->accountId)) ? true : false;
    }

    public function getTeams()
    {
        return $this->db->getAll('
            SELECT at.id, at.name
            FROM account_teams at
            JOIN account_team_users tu ON at.id = tu.team_id
            WHERE tu.user_id = ?
        ', array($this->id));
    }

    /**
     * Check if user is included in team
     *
     * @param   int     $teamId
     * @return  bool
     */
    public function isInTeam($teamId)
    {
        return !!$this->db->getOne('
            SELECT 1 FROM account_team_users WHERE user_id = ? AND team_id = ?
        ', [$this->id, $teamId]);
    }

    /**
     * Gets roles by specified ID of environment
     *
     * @param   int   $envId       The ID of the client's environment
     * @param   bool  $ingoreCache optional Ignore cache.
     * @return  \Scalr\Acl\Role\AccountRoleSuperposition Returns the list of the roles of account level by specified environment
     */
    public function getAclRolesByEnvironment($envId, $ignoreCache = false)
    {
        $cid = 'roles.env';

        if (!isset($this->_cache[$cid][$envId]) || $ignoreCache) {
            $this->_cache[$cid][$envId] = \Scalr::getContainer()->acl->getUserRolesByEnvironment($this, $envId, $this->accountId);
        }

        return $this->_cache[$cid][$envId];
    }

    /**
     * Gets account level roles for the user
     *
     * @param    bool   $ignoreCache
     * @return   \Scalr\Acl\Role\AccountRoleSuperposition Returns the list of the roles of account level
     */
    public function getAclRoles($ignoreCache = false)
    {
        if (!isset($this->_cache['roles.account'])) {
            $this->_cache['roles.account'] = \Scalr::getContainer()->acl->getUserRoles($this);
        }

        return $this->_cache['roles.account'];
    }

    /**
     * Gets roles by specified ID of team
     *
     * @param   int                      $teamId The ID of the team
     * @return  \Scalr\Acl\Role\AccountRoleSuperposition Returns the list of the roles of account level by specified team
     */
    public function getAclRolesByTeam($teamId)
    {
        return \Scalr::getContainer()->acl->getUserRolesByTeam($this, $teamId, $this->getAccountId());
    }

    /**
     * Sets ACL roles to this user
     *
     * This method modifies resords of two tables
     * `account_team_users` and `account_team_user_acls`.
     *
     * Attention! It expects full list of the ACL roles relations for user.
     * All missing relations will be removed.
     *
     * @param   array   $data ACL roles array which looks like
     *                        array(teamId => array(accountRoleId1, accountRoleId2, ...))
     */
    public function setAclRoles(array $data = array())
    {
        if (empty($this->id)) {
            throw new \Scalr\Acl\Exception\AclException(
                "Object hasn't been initialized. Identifier of the user is expected."
            );
        }

        \Scalr::getContainer()->acl->setAllRolesForUser($this->id, $data, $this->getAccountId());
    }

    /**
     * Special method for LDAP auth
     * sync LDAP groups to Scalr groups
     *
     * @param $groups
     */
    public function applyLdapGroups($groups)
    {
        // get current teams
        $currentTeamIds = array();
        foreach ($this->getTeams() as $t) {
            $currentTeamIds[$t['id']] = $t['name'];
        }

        if (count($groups)) {
            // create all links between LDAP user and teams ( == LDAP group)
            $groups[] = $this->getAccountId();

            $teams = $this->db->GetCol('SELECT id FROM account_teams
            WHERE name IN(' . join(',', array_fill(0, count($groups) - 1, '?')) . ') AND account_id = ?', $groups);

            // team exists in DB, so we can save link
            foreach ($teams as $id) {
                $team = new Scalr_Account_Team();
                $team->loadById($id);

                if (! $team->isTeamUser($this->id))
                    $team->addUser($this->id);

                unset($currentTeamIds[$id]);
            }
        }

        // remove old teams
        foreach ($currentTeamIds as $id => $name) {
            $team = new Scalr_Account_Team();
            $team->loadById($id);
            $team->removeUser($this->id);
        }
    }

    /**
     * Gets environments of the current user filtered by name
     *
     * @param string $filter optional Filter string
     * @return array
     */
    public function getEnvironments($filter = null)
    {
        $like = '';

        if (isset($filter)) {
            $like = " AND ce.name LIKE '%" . $this->db->escape($filter) . "%'";
        }

        if ($this->canManageAcl()) {
            return $this->db->getAll('SELECT ce.id, ce.name FROM client_environments ce WHERE ce.client_id = ?' . $like, array(
                $this->getAccountId()
            ));
        } else {
            $teams = array();
            foreach ($this->getTeams() as $team)
                $teams[] = $team['id'];

            if (count($teams)) {
                return $this->db->getAll('
                    SELECT ce.id, ce.name FROM client_environments ce
                    JOIN account_team_envs te ON ce.id = te.env_id
                    WHERE te.team_id IN (' . implode(',', $teams) . ')'
                    . $like . '
                    GROUP BY ce.id
                ');
            }
        }

        return array();
    }

    /**
     * Get default environment (or given) and check access to it
     * @param integer $envId
     * @return Scalr_Environment
     * @throws Scalr_Exception_Core
     */
    public function getDefaultEnvironment($envId = 0)
    {
        if ($envId || ($envId = (int)$this->getSetting(Scalr_Account_User::SETTING_UI_ENVIRONMENT))) {
            try {
                $environment = Scalr_Environment::init()->loadById($envId);
                $this->getPermissions()->validate($environment);
            } catch (Exception $e) {
                $environment = null;
            }
        }

        if (empty($environment)) {
            $envs = $this->getEnvironments();
            if (count($envs)) {
                $envId = $envs[0]['id'];
                $environment = Scalr_Environment::init()->loadById($envId);
            } else {
                throw new Scalr_Exception_Core('You don\'t have access to any environment.');
            }
        }

        $this->getPermissions()->validate($environment);
        return $environment;
    }

    /**
     * Checks wheter this user is considered to be the team owner for the specified team.
     *
     * @param   int     $teamId  The identifier of the team.
     * @return  boolean Returns true if the user is considered to be the team owner for the specified team.
     * @deprecated This function has been deprecated since new ACL
     */
    public function isTeamOwner($teamId = null)
    {
        $ret = false;
        if ($teamId) {
            try {
                $team = Scalr_Account_Team::init();
                $team->loadById($teamId);
                $ret = $team->isTeamOwner($this->id);
            } catch (\Exception $e) {
            }
        } else {
            $ret = $this->canManageAcl();
        }

        return $ret;
    }

    /**
     * Checks if the user is both AccountAdmin and member of the
     * specified environment
     *
     * @param   int      $envId  The identifier of the environment
     * @return  boolean
     * @deprecated This function has been deprecated since new ACL
     */
    public function isTeamOwnerInEnvironment($envId)
    {
        if (!$this->isAccountAdmin()) return false;

        $ret = $this->db->GetOne("
            SELECT 1
            FROM account_team_users tu
            JOIN account_team_envs te ON te.team_id = tu.team_id
            JOIN account_teams t ON t.id = tu.team_id
            JOIN client_environments e ON e.id = te.env_id
            WHERE tu.user_id = ? AND e.client_id = ?
            AND t.account_id = ? AND te.env_id = ?
            LIMIT 1
        ", array(
            $this->id, $this->accountId, $this->accountId, $envId
        ));

        return (bool) $ret;
    }

    public function getUserInfo()
    {
        $info['id'] = $this->id;
        $info['status'] = $this->status;
        $info['email'] = $this->getEmail();
        $info['fullname'] = $this->fullname;
        $info['dtcreated'] = Scalr_Util_DateTime::convertTz($this->dtCreated);
        $info['dtlastlogin'] = $this->dtLastLogin ? Scalr_Util_DateTime::convertTz($this->dtLastLogin) : 'Never';
        $info['dtlastloginhr'] = $this->dtLastLogin ? Scalr_Util_DateTime::getFuzzyTimeString($this->dtLastLogin) : 'Never';
        $info['gravatarhash'] = $this->getGravatarHash();
        $info['type'] = $this->type;
        $info['comments'] = $this->comments;

        $info['is2FaEnabled'] = $this->getSetting(Scalr_Account_User::SETTING_SECURITY_2FA_GGL) == '1' ? true : false;
        $info['password'] = $this->password ? true : false;

        return $info;

    }

    /**
     * Checks whether the user is allowed to manage ACL
     *
     * @return  boolean Returns true if user is allowed to manage ACL
     */
    public function canManageAcl()
    {
        return $this->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER ||
               $this->getType() == Scalr_Account_User::TYPE_ACCOUNT_SUPER_ADMIN ||
               $this->getType() == Scalr_Account_User::TYPE_ACCOUNT_ADMIN;
    }

    /**
     * Checks if the user is account owher
     *
     * @return  boolean Returns true if user is account owner.
     */
    public function isAccountOwner()
    {
        return $this->getType() == Scalr_Account_User::TYPE_ACCOUNT_OWNER;
    }

    /**
     * Checks if the user is account admin
     *
     * @return  boolean Returns true if user is account admin
     */
    public function isAccountAdmin()
    {
        return $this->getType() == Scalr_Account_User::TYPE_ACCOUNT_ADMIN || $this->getType() == Scalr_Account_User::TYPE_ACCOUNT_SUPER_ADMIN;
    }

    /**
     * Checks if the user is account admin
     *
     * @return  boolean Returns true if user is account super admin
     */
    public function isAccountSuperAdmin()
    {
        return $this->getType() == Scalr_Account_User::TYPE_ACCOUNT_SUPER_ADMIN;
    }

    /**
     * Checks if the user is Scalr admin
     *
     * @return  boolean Returns true if user is Scalr Admin
     */
    public function isScalrAdmin()
    {
        return $this->getType() == Scalr_Account_User::TYPE_SCALR_ADMIN;
    }

    /**
     * Checks if the user is Financial admin
     *
     * @return  boolean Returns true if user is Financial Admin
     */
    public function isFinAdmin()
    {
        return $this->getType() == Scalr_Account_User::TYPE_FIN_ADMIN;
    }

    /**
     * Checks if the user is team user
     *
     * @return  boolean  Returns true if user is team user
     */
    public function isTeamUser()
    {
        return $this->getType() == Scalr_Account_User::TYPE_TEAM_USER;
    }

    /**
     * Checks if the user is account user (owner, admin, team user)
     *
     * @return  boolean
     */
    public function isUser()
    {
        return in_array($this->getType(), [self::TYPE_ACCOUNT_OWNER, self::TYPE_ACCOUNT_SUPER_ADMIN, self::TYPE_ACCOUNT_ADMIN, self::TYPE_TEAM_USER]);
    }

    /**
     * Checks if the user is admin user (scalr, financial). It means user doesn't have current environment
     *
     * @return  boolean
     */
    public function isAdmin()
    {
        return in_array($this->getType(), [self::TYPE_SCALR_ADMIN, self::TYPE_FIN_ADMIN]);
    }

    /**
     * Checks whether the user is allowed to remove specified user
     *
     * @param   \Scalr_Account_User $user The user to remove
     * @return  boolean   Returns true if the user is allowed to remove specified user
     */
    public function canRemoveUser($user)
    {
        return !$this->isTeamUser() &&
               $user->getAccountId() == $this->getAccountId() &&
               !$user->isAccountOwner() &&
               $this->getId() != $user->getId() &&
               ($this->isAccountOwner() || $this->isAccountSuperAdmin() || !$user->isAccountSuperAdmin()) ;
    }

    /**
     * Checks whether the user is allowed to edit specified user
     *
     * @param   \Scalr_Account_User  $user The user to edit
     * @return  boolean              Returns true if the user is allowed to edit specified user
     */
    public function canEditUser($user)
    {
        return
            !$this->isTeamUser() &&
            $user->getAccountId() == $this->getAccountId() &&
            (
                $this->getId() == $user->getId() ||
                $this->isAccountOwner() ||
                $this->isAccountSuperAdmin() && !$user->isAccountOwner() ||
                $this->isAccountAdmin() && !$user->isAccountOwner() && !$user->isAccountSuperAdmin()
            );
    }

    /**
     * {@inheritdoc}
     * @see Scalr_Model::save()
     */
    public function save($forceInsert = false)
    {
        $ret = parent::save($forceInsert);

        if ($this->id && \Scalr::getContainer()->analytics->enabled) {
            \Scalr::getContainer()->analytics->tags->syncValue(
                $this->accountId, \Scalr\Stats\CostAnalytics\Entity\TagEntity::TAG_ID_USER, $this->id,
                ($this->fullname ?: $this->email)
            );
        }

        return $ret;
    }

    public static function getList($accountId)
    {
        return Scalr::getDb()->GetAll('SELECT id, email FROM account_users WHERE account_id = ?', [$accountId]);
    }

    public function setSshConsoleSettings($settings)
    {
        $list = array(
            Scalr_Account_User::VAR_SSH_CONSOLE_LAUNCHER,
            Scalr_Account_User::VAR_SSH_CONSOLE_USERNAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_IP,
            Scalr_Account_User::VAR_SSH_CONSOLE_PORT,
            Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH,
            Scalr_Account_User::VAR_SSH_CONSOLE_LOG_LEVEL,
            Scalr_Account_User::VAR_SSH_CONSOLE_PREFERRED_PROVIDER,
            Scalr_Account_User::VAR_SSH_CONSOLE_ENABLE_AGENT_FORWARDING
        );
        foreach ($list as $name) {
            $this->setVar($name, $settings[$name]);
        }
    }

    public function getSshConsoleSettings($ignoreCache = false, $gvi = false, $serverId = null)
    {
        $result = array();
        $list = array(
            Scalr_Account_User::VAR_SSH_CONSOLE_LAUNCHER,
            Scalr_Account_User::VAR_SSH_CONSOLE_USERNAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_IP,
            Scalr_Account_User::VAR_SSH_CONSOLE_PORT,
            Scalr_Account_User::VAR_SSH_CONSOLE_KEY_NAME,
            Scalr_Account_User::VAR_SSH_CONSOLE_DISABLE_KEY_AUTH,
            Scalr_Account_User::VAR_SSH_CONSOLE_LOG_LEVEL,
            Scalr_Account_User::VAR_SSH_CONSOLE_PREFERRED_PROVIDER,
            Scalr_Account_User::VAR_SSH_CONSOLE_ENABLE_AGENT_FORWARDING
        );

        if ($serverId) {
            $dbServer = DBServer::LoadByID($serverId);
            $this->permissions->validate($dbServer);
        }

        foreach ($list as $name) {
            $result[$name] = $this->getVar($name, $ignoreCache);
            if ($gvi && $dbServer)
                $result[$name] = $dbServer->applyGlobalVarsToValue($result[$name]);
        }
        return $result;
    }

    /**
     * @return bool Returns true if lead is verified
     */
    public function isLeadVerified()
    {
        return ($this->getSetting(self::SETTING_LEAD_VERIFIED) != 1) ? false : true;
    }

    /**
     * Checks if user has access to project or cost center
     *
     * @param string $projectId optional Id of the project
     * @param string $ccId      optional Id of the cost center
     * @return boolean          Returns false if user is not lead of the subject
     */
    public function isSubjectLead($projectId = null, $ccId = null)
    {
        if (!empty($projectId)) {
            $project = ProjectEntity::findPk($projectId);

            if (empty($project) || $project->getProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL) !== $this->getEmail()) {
                return false;
            }
        } else if (!empty($ccId)) {
            $ccs = CostCentreEntity::findPk($ccId);

            if (empty($ccs) || $ccs->getProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL) !== $this->getEmail()) {
                return false;
            }
        }

        return true;
    }

}
